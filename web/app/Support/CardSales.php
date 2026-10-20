<?php

namespace App\Support;

use App\Models\Card;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Deleting a card sale (a top-up entered wrong). Only the manager may (permission card_sales.delete, the route). Refused when the sale is confirmed,
// older than config('sales.delete_days'), or when the card has been used since: the balance would go below zero. A top-up is never refunded, so
// there is no return of a card sale.
class CardSales
{
    // null when the sale may be deleted, otherwise why not: confirmed, too_old or used.
    public static function blocker(Sale $sale)
    {
        if ($sale->confirmed_at) {
            return 'confirmed';
        }
        if (Carbon::parse($sale->sale_date)->lt(today()->subDays(config('sales.delete_days')))) {
            return 'too_old';
        }

        $card = Card::withTrashed()->find($sale->card_id);
        if ($card && $card->balance() - (float) $sale->amount_kg < -0.004) {
            return 'used';
        }

        return null;
    }

    public static function delete($saleId)
    {
        return DB::transaction(function () use ($saleId) {
            $sale = Sale::where('sale_type', 'card')->lockForUpdate()->findOrFail($saleId);

            if ($reason = self::blocker($sale)) {
                throw ValidationException::withMessages(['sale' => __('sales.blocked_' . $reason)]);
            }

            $sale->delete();
            Card::where('id', $sale->card_id)->toBase()->update(['synced' => false, 'updated_at' => now()]);   // the Smart210 pulls the card with its balance

            return $sale;
        });
    }
}
