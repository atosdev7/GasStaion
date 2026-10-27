<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Cards\Card;
use App\Models\Sales\Customer;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Admin\User;
use App\Support\Admin\Audit;
use App\Support\Fuel\CostChoices;
use App\Support\Sales\SaleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Editing a sale that was entered with a wrong date, comment or receipt: only those three can be changed; the weight, the price, the payment,
// the tickets and the card balance stay as they were entered (a wrong sale is deleted and entered again, a sale made right is returned). A
// confirmed sale is locked until a manager takes its confirmation back (see UnconfirmController). A ticket or spot sale is edited with the
// permission sales.edit, a card sale (a top-up) with card_sales.edit.
class SaleEditController extends Controller
{
    private function sale($id)
    {
        $sale = Sale::findOrFail($id);
        abort_unless(Gate::allows($sale->sale_type === 'card' ? 'card_sales.edit' : 'sales.edit'), 403);
        SaleAccess::authorizeChange(auth()->user(), $sale); // the kind of sale, and whose it is

        return $sale;
    }

    public function edit(Request $request, $id)
    {
        $sale = $this->sale($id);
        $back = $this->backUrl($request, $sale);

        if ($sale->confirmed_at) {
            return redirect($back)->withErrors(['sale' => __('sale_edit.confirmed')]);
        }

        $card = $sale->card_id ? Card::withTrashed()->find($sale->card_id) : null;
        $customer = Customer::withTrashed()->find($sale->customer_id);
        $user = User::find($sale->user_id);

        return view('sale.edit', [
            'sale' => $sale,
            'customer' => $card ? $card->label : ($customer ? $customer->name : ''),
            'user' => $user ? $user->name : '',
            'fuels' => config('fuel_types'),
            'items' => SaleItem::where('sale_id', $sale->id)->orderBy('id')->get(),
            'back' => $back,
            'costChoices' => $this->costEditable($sale) ? CostChoices::forFuel($sale->fuel_type, $sale->cost) : null,
        ]);
    }

    // The cost of a sale of fuel can be chosen again (from the costs of the fuel in the tanks) by a user who may see costs; a top-up of a card has none, and a
    // return keeps the cost of the sale it takes back.
    private function costEditable(Sale $sale)
    {
        return auth()->user() && auth()->user()->can('costs.view') && $sale->sale_type !== 'card' && !$sale->isReturn();
    }

    public function update(Request $request, $id)
    {
        $sale = $this->sale($id);
        $back = $this->backUrl($request, $sale);

        if ($sale->confirmed_at) {
            return redirect($back)->withErrors(['sale' => __('sale_edit.confirmed')]);
        }

        // (the limit of backdate_days is for a day the sale is moved to: a sale that is old already keeps its day when only the comment changes)
        $moving = substr((string) $sale->sale_date, 0, 10) !== (string) $request->input('sale_date');
        $rules = [
            'sale_date' => $moving ? SaleAccess::dateRules($request->user(), ['required']) : 'required|date_format:Y-m-d|before_or_equal:today',
            'comment' => 'nullable|string|max:255',
            'receipt' => 'nullable|boolean',
        ];
        $costEditable = $this->costEditable($sale);
        if ($costEditable) {
            $rules['cost'] = 'nullable|numeric|min:0|max:9999999';
        }
        $data = $request->validate($rules, SaleAccess::dateMessages());

        $before = '#' . $sale->id . ' ' . $sale->sale_type . ' ' . $sale->sale_date . ' / ' . $sale->comment . ' / receipt ' . (int) $sale->receipt . ' / cost ' . $sale->cost;
        $moved = substr((string) $sale->sale_date, 0, 10) !== $data['sale_date'];

        // A return takes back a sale, so it cannot be dated before that sale, and a sale cannot be moved to a day after its first return.
        if ($moved) {
            if ($sale->isReturn()) {
                $original = Sale::withTrashed()->find($sale->reverses_sale_id);
                if ($original && $data['sale_date'] < substr((string) $original->sale_date, 0, 10)) {
                    throw ValidationException::withMessages(['sale_date' => __('sale_edit.before_original', ['date' => substr((string) $original->sale_date, 0, 10)])]);
                }
            } else {
                $firstReturn = Sale::where('reverses_sale_id', $sale->id)->min('sale_date');
                if ($firstReturn && $data['sale_date'] > substr((string) $firstReturn, 0, 10)) {
                    throw ValidationException::withMessages(['sale_date' => __('sale_edit.after_return', ['date' => substr((string) $firstReturn, 0, 10)])]);
                }
            }
        }

        DB::transaction(function () use ($sale, $data, $moved, $costEditable) {
            $sale->forceFill([
                'sale_date' => $data['sale_date'],
                'comment' => isset($data['comment']) && $data['comment'] !== '' ? $data['comment'] : null,
                'receipt' => !empty($data['receipt']),
            ]);
            if ($costEditable && array_key_exists('cost', $data) && $data['cost'] !== null) {
                $sale->cost = $data['cost'];
            }
            $sale->save();

            if ($moved) {
                // the tickets it sold and a ticket loan it made keep the moment of the sale: they move to the new day, at the same time of day
                Ticket::where('sale_id', $sale->id)->whereNotNull('sale_date')->update(['sale_date' => DB::raw('TIMESTAMP(' . DB::getPdo()->quote($data['sale_date']) . ', TIME(sale_date))')]);
                TicketLoan::where('sale_id', $sale->id)->update([
                    'date' => $data['sale_date'],
                    'loaned_at' => DB::raw('TIMESTAMP(' . DB::getPdo()->quote($data['sale_date']) . ', TIME(loaned_at))'),
                ]);
            }
        });

        Audit::log('sale_edit', $before . ' -> ' . $data['sale_date'] . ' / ' . ($data['comment'] ?? '') . ' / receipt ' . (int) !empty($data['receipt']) . ' / cost ' . $sale->cost);

        $message = __('sale_edit.saved');
        if ($moved) {
            $message .= ' ' . __('sale_edit.moved', ['date' => $data['sale_date']]);
        }

        return redirect($back)->with('status', $message);
    }

    // Where to go back to: the page the edit was opened from (an address of this site), or the list of that kind of sale.
    private function backUrl(Request $request, Sale $sale)
    {
        $back = (string) ($request->input('back') ?: url()->previous());
        $own = rtrim(url('/'), '/') . '/';

        if ($back === '' || !Str::startsWith($back, $own) || Str::contains($back, '/edit')) {
            return route(SaleController::listRoute($sale->sale_type));
        }

        return $back;
    }
}
