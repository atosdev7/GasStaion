<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Cards\Card;
use App\Models\Sales\Sale;
use App\Models\Admin\User;
use App\Support\Admin\Audit;
use App\Support\Sales\CardSales;
use Illuminate\Http\Request;

// Card Sales (Sales Management): the sales of type card, that is the top-ups of the cards, in the layout of the other sales lists. A card sale is
// cash only, has no customer (the card has the name) and cannot be returned; only the manager can delete one.
class CardSaleController extends Controller
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
        $sales = $query->paginate(50)->appends($request->query());

        // why a sale cannot be deleted (null = it can), only worked out for the people who may delete
        $blockers = collect();
        if ($request->user()->can('card_sales.delete')) {
            $blockers = $sales->mapWithKeys(function ($sale) {
                return [$sale->id => CardSales::blocker($sale)];
            });
        }

        return view('card.sales', [
            'sales' => $sales,
            'filters' => $filters,
            'blockers' => $blockers,
            'cards' => Card::withTrashed()->orderBy('holder_name')->get()->keyBy('id'),
            'users' => User::pluck('name', 'id'),
            'fuels' => config('fuel_types'),
        ]);
    }

    public function destroy($id)
    {
        $sale = CardSales::delete($id);

        Audit::log('card_sale_delete', '#' . $sale->id . ' card ' . $sale->card_id . ' ' . $sale->amount_kg . 'kg, total ' . $sale->total_amount);

        return redirect()->route('card-sales.index')->with('status', __('sales.cards_deleted'));
    }
}
