<?php

namespace App\Support\Sales;

use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Tickets\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Deleting a sale is for wrong input only, so it is refused as soon as the sale has moved on: confirmed by the
// accountant, older than config('sales.delete_days'), a ticket used or restricted, a payment tied to its debt, or a loan
// settlement. A deleted sale is soft-deleted and kept for review; confirming its date erases it for good (purge()).
class SaleDeletion
{
    // null when the sale may be deleted, otherwise why not: card, is_return, confirmed, returned, too_old, ticket_used, paid or settled.
    public static function blocker(Sale $sale, $lock = false)
    {
        if ($sale->sale_type === 'card') {
            return 'card'; // a top-up is deleted on the Card Sales page, by the manager only (see CardSales)
        }
        if ($sale->isReturn()) {
            return 'is_return'; // a return entry is itself the correction and stays
        }
        if ($sale->confirmed_at) {
            return 'confirmed';
        }
        if (Sale::where('reverses_sale_id', $sale->id)->exists()) {
            return 'returned';
        }
        if (Carbon::parse($sale->sale_date)->lt(today()->subDays(config('sales.delete_days')))) {
            return 'too_old';
        }

        // Every ticket must still be sold and unused. No tickets at all (a backup cleared them) cannot be verified.
        $tickets = Ticket::where('sale_id', $sale->id)->selectRaw('COUNT(*) AS total, SUM(status <> ?) AS moved', [config('ticket_statuses.sale')]);
        $tickets = ($lock ? $tickets->lockForUpdate() : $tickets)->first();
        if (!$tickets->total || $tickets->moved) {
            return 'ticket_used';
        }

        $debtIds = Debt::where('sale_id', $sale->id)->pluck('id');
        if ($debtIds->isNotEmpty() && round((float) DebtPayment::whereIn('debt_id', $debtIds)->sum('amount'), 2) > 0) { // a payment that was reversed no longer counts
            return 'paid';
        }

        $loanIds = TicketLoan::where('sale_id', $sale->id)->pluck('id');
        if ($loanIds->isNotEmpty() && round((float) TicketLoanSettlement::whereIn('loan_id', $loanIds)->sum('amount_kg'), 2) > 0) {
            return 'settled';
        }

        return null;
    }

    // Undoes everything the sale did, then soft-deletes it. Throws a ValidationException (shown to the user) when refused.
    public static function delete($saleId)
    {
        return DB::transaction(function () use ($saleId) {
            $sale = Sale::lockForUpdate()->findOrFail($saleId); // an already deleted sale is not found

            if ($reason = self::blocker($sale, true)) {
                throw ValidationException::withMessages(['sale' => __('sale_delete.blocked_' . $reason)]);
            }

            $tickets = Ticket::where('sale_id', $sale->id);
            if ($sale->sale_type === 'spot') {
                // Minted for this sale, so it never goes back to stock: it just becomes unusable.
                $tickets->update(['status' => config('ticket_statuses.restrict'), 'synced' => false]);
            } else {
                $tickets->update([
                    'status' => config('ticket_statuses.publish'), 'sale_id' => null, 'sale_date' => null, 'synced' => false,
                ]);
            }

            Debt::where('sale_id', $sale->id)->get()->each(function ($debt) {
                $debt->delete();
            });
            TicketLoan::where('sale_id', $sale->id)->get()->each(function ($loan) {
                $loan->delete();
            });

            $sale->delete();

            return $sale;
        });
    }

    // Erases for good the deleted sales of a date, with the debts and ticket loans cancelled along with them.
    // Called when that date is confirmed. Returns how many sales were erased.
    public static function purge($date)
    {
        $erased = 0;

        Sale::onlyTrashed()->where('sale_date', $date)->get()->each(function ($sale) use (&$erased) {
            Debt::onlyTrashed()->where('sale_id', $sale->id)->forceDelete();
            TicketLoan::onlyTrashed()->where('sale_id', $sale->id)->forceDelete();
            SaleItem::where('sale_id', $sale->id)->delete();
            $sale->forceDelete();
            $erased++;
        });

        return $erased;
    }
}
