<?php

namespace App\Support\Sales;

use App\Models\Debts\Debt;
use App\Models\Debts\DebtAdjustment;
use App\Models\Debts\DebtPayment;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleReturnTicket;
use App\Models\Tickets\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// A return is a counter entry, not an edit: the sale stays as it was and a new sale row with negative amounts, dated
// today and pointing at it (reverses_sale_id), takes the returned part back. That keeps every confirmed day's figures
// as they were, so returns work on confirmed sales too. The value returned first cancels what the customer still owes
// on that sale (its debt) and the rest is refunded in the sale's payment method, so nothing is refunded that was not
// received. Tickets come back to stock (a spot sale's ticket just becomes unusable). A spot sale or a ticket-loan sale
// can only be returned whole, and a loan only while nothing was settled against it.
class SaleReturn
{
    const CHUNK = 500;

    /**
     * @param array $barcodes the tickets handed back (ignored when $all)
     * @param bool $all every unused ticket of the sale
     * @return object with: sale (the new return entry), tickets, kg, debt (owed amount cancelled), refund
     */
    public static function create($saleId, array $barcodes, $all, $comment = null)
    {
        return DB::transaction(function () use ($saleId, $barcodes, $all, $comment) {
            $sale = Sale::lockForUpdate()->findOrFail($saleId); // a deleted sale is not found
            if ($sale->isReturn()) {
                throw self::refuse('is_return');
            }

            // Only tickets of this sale that are still sold and unused can come back.
            $query = Ticket::where('sale_id', $sale->id)->where('status', config('ticket_statuses.sale'))->lockForUpdate();
            if (!$all) {
                $query->whereIn('barcode', $barcodes);
            }
            $tickets = $query->get(['barcode', 'spec', 'amount_kg']);

            if (!$all) {
                $missing = array_values(array_diff($barcodes, $tickets->pluck('barcode')->all()));
                if ($missing) {
                    throw ValidationException::withMessages([
                        'ticket_barcodes' => __('sale_return.tickets_not_returnable', ['barcodes' => implode(', ', array_slice($missing, 0, 10))]),
                    ]);
                }
            }
            if ($tickets->isEmpty()) {
                throw self::refuse('nothing_to_return');
            }

            $isLoan = $sale->usage === 'ticket_loan';
            if ($sale->sale_type === 'spot' || $isLoan) {
                // Whole only: every ticket still linked to the sale must be coming back.
                if (Ticket::where('sale_id', $sale->id)->count() !== $tickets->count()) {
                    throw self::refuse('whole_only');
                }
            }
            if ($isLoan) {
                $loanIds = TicketLoan::where('sale_id', $sale->id)->pluck('id');
                if ($loanIds->isNotEmpty() && TicketLoanSettlement::whereIn('loan_id', $loanIds)->exists()) {
                    throw self::refuse('loan_settled');
                }
            }

            $specs = config('ticket_specs');
            $kg = round($tickets->sum(function ($ticket) use ($specs) {
                return $ticket->amount_kg !== null ? $ticket->amount_kg : ($specs[$ticket->spec] ?? 0);
            }), 2);

            // What is left of the sale after earlier returns (their amounts are negative).
            $remainingKg = round($sale->amount_kg + Sale::where('reverses_sale_id', $sale->id)->sum('amount_kg'), 2);
            $remainingTotal = round($sale->total_amount + Sale::where('reverses_sale_id', $sale->id)->sum('total_amount'), 2);
            if ($kg > $remainingKg + 0.005) {
                throw self::refuse('more_than_remaining');
            }
            // Returning what is left takes back exactly what is left, so rounding never leaves a remainder.
            $value = abs($kg - $remainingKg) < 0.005 ? $remainingTotal : min($remainingTotal, round($kg * $sale->price, 2));

            // The returned value first cancels what is still owed on this sale, the rest is refunded.
            $debtCut = 0;
            $debt = Debt::with('adjustments')->where('sale_id', $sale->id)->lockForUpdate()->first();
            if ($debt && $value > 0) {
                $balance = round($debt->net_amount - DebtPayment::where('debt_id', $debt->id)->sum('amount'), 2);
                $debtCut = round(min($value, max(0, $balance)), 2);
            }
            $refund = round($value - $debtCut, 2);

            $return = new Sale;
            $return->forceFill([
                'customer_id' => $sale->customer_id, 'sale_type' => $sale->sale_type, 'fuel_type' => $sale->fuel_type,
                'cost' => $sale->cost, 'price' => $sale->price, 'amount_kg' => 0 - $kg, 'payment_method' => $sale->payment_method,
                'total_amount' => 0 - $value, 'paid_amount' => 0 - $refund, 'unpaid_amount' => 0 - $debtCut,
                'usage' => $sale->usage, 'comment' => $comment, 'sale_date' => today()->toDateString(),
                'reverses_sale_id' => $sale->id, 'synced' => false, 'user_id' => auth()->id(),
            ])->save();

            // The cut is a counter entry dated today; the debt keeps the amount the sale made, so closed days are not rewritten.
            if ($debt && $debtCut > 0) {
                DebtAdjustment::forceCreate([
                    'debt_id' => $debt->id, 'customer_id' => $debt->customer_id, 'amount' => 0 - $debtCut, 'adjusted_date' => today()->toDateString(),
                    'sale_id' => $return->id, 'user_id' => auth()->id(),
                ]);
            }

            $now = now();
            foreach ($tickets->chunk(self::CHUNK) as $chunk) {
                $chunkBarcodes = $chunk->pluck('barcode')->all();

                if ($sale->sale_type === 'spot') {
                    Ticket::whereIn('barcode', $chunkBarcodes)->update(['status' => config('ticket_statuses.restrict'), 'synced' => false]);
                } else {
                    Ticket::whereIn('barcode', $chunkBarcodes)->update([
                        'status' => config('ticket_statuses.publish'), 'sale_id' => null, 'sale_date' => null, 'synced' => false,
                    ]);
                }

                SaleReturnTicket::insert($chunk->map(function ($ticket) use ($return, $specs, $now) {
                    return [
                        'sale_id' => $return->id, 'ticket_barcode' => $ticket->barcode,
                        'amount_kg' => $ticket->amount_kg !== null ? $ticket->amount_kg : ($specs[$ticket->spec] ?? 0),
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                })->all());
            }

            if ($isLoan) {
                TicketLoan::where('sale_id', $sale->id)->get()->each(function ($loan) {
                    $loan->delete();
                });
            }

            return (object) ['sale' => $return, 'original' => $sale, 'tickets' => $tickets->count(), 'kg' => $kg, 'debt' => $debtCut, 'refund' => $refund];
        });
    }

    private static function refuse($reason)
    {
        return ValidationException::withMessages(['sale' => __('sale_return.' . $reason)]);
    }
}
