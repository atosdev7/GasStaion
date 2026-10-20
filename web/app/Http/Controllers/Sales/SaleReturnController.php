<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Sales\Sale;
use App\Models\Tickets\Ticket;
use App\Support\Admin\Audit;
use App\Support\Sales\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// Return tickets of a sale as a counter entry (see App\Support\Sales\SaleReturn). Works on any sale that is not itself a
// return, confirmed or not; the physical tickets handed back are entered by barcode.
class SaleReturnController extends Controller
{
    public function create($id)
    {
        $sale = Sale::findOrFail($id);
        abort_if($sale->isReturn() || $sale->sale_type === 'card', 404); // a top-up of a card is never refunded

        $debt = Debt::where('sale_id', $sale->id)->first();

        return view('sale.return', [
            'sale' => $sale,
            'customer' => optional(Customer::withTrashed()->find($sale->customer_id))->name,
            'unused' => Ticket::where('sale_id', $sale->id)->where('status', config('ticket_statuses.sale'))
                ->selectRaw('spec, amount_kg, COUNT(*) AS n')->groupBy('spec', 'amount_kg')->orderBy('spec')->get(),
            'returnedKg' => abs(Sale::where('reverses_sale_id', $sale->id)->sum('amount_kg')),
            'debtBalance' => $debt ? round($debt->amount - DebtPayment::where('debt_id', $debt->id)->sum('amount'), 2) : null,
            'wholeOnly' => $sale->sale_type === 'spot' || $sale->usage === 'ticket_loan',
            'fuels' => config('fuel_types'),
        ]);
    }

    public function store(Request $request, $id)
    {
        // The barcodes arrive as free text, one per line as a scanner types them.
        $request->merge([
            'ticket_barcodes' => preg_split('/[\s,;]+/', trim((string) $request->input('ticket_barcodes')), -1, PREG_SPLIT_NO_EMPTY),
        ]);

        $data = $request->validate([
            'all' => 'nullable|boolean',
            'ticket_barcodes' => 'array|max:2000',
            'ticket_barcodes.*' => 'string|size:16|distinct',
            'comment' => 'nullable|string|max:255',
        ]);
        $all = !empty($data['all']);

        if (!$all && !$data['ticket_barcodes']) {
            throw ValidationException::withMessages(['ticket_barcodes' => __('sale_return.nothing_entered')]);
        }

        abort_if(Sale::where('id', $id)->where('sale_type', 'card')->exists(), 404);

        $result = SaleReturn::create($id, $data['ticket_barcodes'], $all, $data['comment'] ?? null);

        $money = function ($value) {
            return number_format($value, 2, '.', '');
        };
        Audit::log('sale_return', '#' . $result->sale->id . ' of sale ' . $result->original->id . ': ' . $result->tickets . ' tickets, '
            . $money($result->kg) . 'kg, owed cancelled ' . $money($result->debt) . ', refund ' . $money($result->refund));

        return redirect()->route(SaleController::listRoute($result->original->sale_type))->with('status', __('sale_return.done', [
            'count' => $result->tickets, 'kg' => $money($result->kg), 'debt' => $money($result->debt), 'refund' => $money($result->refund),
            'method' => __('sales.payment_' . $result->original->payment_method),
        ]));
    }
}
