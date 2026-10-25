<?php

namespace App\Http\Controllers\Cards;

use App\Http\Controllers\Controller;
use App\Models\Cards\Card;
use App\Models\Fuel\FuelType;
use App\Models\Sales\Sale;
use App\Models\Admin\User;
use App\Support\Admin\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// A top-up gives a card kg of its fuel type. It is an ordinary sale (type card): always cash, no customer, no debt, the price free to type (the price
// of the fuel is the default). The cashier tops up on the Smart210; the manager can top up an existing card here.
class TopUpController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'sale_date' => 'nullable|date_format:Y-m-d',
            'card' => 'nullable|uuid',
        ]);

        $query = Sale::where('sale_type', 'card')->orderByDesc('sale_date')->orderByDesc('created_at');
        if (!empty($filters['sale_date'])) {
            $query->where('sale_date', $filters['sale_date']);
        }
        if (!empty($filters['card'])) {
            $query->where('card_id', $filters['card']);
        }
        $totalKg = (clone $query)->sum('amount_kg');
        $topUps = $query->paginate(50)->appends($request->query());

        return view('card.topups', [
            'topUps' => $topUps,
            'filters' => $filters,
            'totalKg' => $totalKg,
            'cards' => Card::withTrashed()->orderBy('holder_name')->get()->keyBy('id'),
            'users' => User::pluck('name', 'id'),
            'fuels' => config('fuel_types'),
        ]);
    }

    public function create(Request $request)
    {
        return view('card.topup_create', [
            // a blocked (lost) card cannot be topped up
            'cards' => Card::where('status', '<>', 'blocked')->orderBy('holder_name')->get(),
            'balances' => Card::balances(),
            'prices' => FuelType::pluck('price', 'fuel_type'),
            'fuels' => config('fuel_types'),
            'chosen' => $request->query('card'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'card_id' => ['required', 'uuid', Rule::exists('cards', 'id')->whereNull('deleted_at')],
            'amount_kg' => 'required|numeric|min:0.01|max:99999999',
            'price' => 'required|numeric|min:0|max:9999999',
        ]);

        $sale = DB::transaction(function () use ($data) {
            $card = Card::lockForUpdate()->findOrFail($data['card_id']);
            if ($card->status === 'blocked') {
                throw ValidationException::withMessages(['card_id' => __('cards.card_blocked')]);
            }

            $kg = round((float) $data['amount_kg'], 2);
            $total = round($kg * (float) $data['price'], 2);
            \App\Http\Controllers\Sales\SaleController::checkTotal($total);

            $sale = new Sale;
            $sale->forceFill([
                'customer_id' => null,
                'card_id' => $card->id,
                'sale_type' => 'card',
                'fuel_type' => $card->fuel_type,
                'price' => $data['price'],
                'amount_kg' => $kg,
                'payment_method' => 'cash',        // a card is only paid in cash: no bank, no debt
                'total_amount' => $total,
                'paid_amount' => $total,
                'unpaid_amount' => 0,
                'receipt' => false,
                'usage' => 'sale',
                'sale_date' => today()->toDateString(),
                'synced' => false,
                'user_id' => auth()->id(),
            ])->save();

            // the Smart210 pulls the card again with its new balance
            Card::where('id', $card->id)->toBase()->update(['synced' => false, 'updated_at' => now()]);

            return $sale;
        });

        Audit::log('card_topup', '#' . $sale->id . ' card ' . $sale->card_id . ' ' . $sale->amount_kg . 'kg, total ' . $sale->total_amount);

        $card = Card::find($sale->card_id);

        return redirect()->route('card-topups.index')->with('status', __('cards.topped_up', ['kg' => number_format($sale->amount_kg, 2, '.', ''), 'name' => $card->holder_name]));
    }
}
