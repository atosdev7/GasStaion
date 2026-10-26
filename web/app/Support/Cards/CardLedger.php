<?php

namespace App\Support\Cards;

use App\Models\Cards\Card;
use App\Models\Cards\CardTransaction;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;

// The ledger of the cards (card_transactions): one entry for every top-up (a sale of type card) and every fill made with a card (a ticket made for the
// card, tickets.card_id; before that, a supplement with a card_id), each with the running balance of the card after it, in the order of the moments they happened. The entries are made by the
// model events of Sale and Supplement, so every way a record is made (the Smart210's push, a top-up on the web, a hand entry) is covered, and the
// same record never counts twice (type + source_id is unique). A deleted top-up or fill is not removed from the ledger: a reversal entry undoes
// it. The balance of a card is the sum of its ledger; the records it came from may be deleted or cleared and the ledger still holds the history.
class CardLedger
{
    // The top-up of a card sale: made once, updated when the sale's weight or price changes.
    public static function syncSale(Sale $sale)
    {
        if ($sale->sale_type !== 'card' || !$sale->card_id) {
            return;
        }

        $entry = CardTransaction::where('type', 'topup')->where('source_id', $sale->id)->first() ?: new CardTransaction;
        $entry->forceFill([
            'type' => 'topup', 'source_id' => $sale->id,
            'card_id' => $sale->card_id, 'kg' => round((float) $sale->amount_kg, 2), 'price' => $sale->price, 'total_amount' => $sale->total_amount,
            'occurred_at' => $sale->created_at ?: now(), 'user_id' => $sale->user_id,
        ]);
        $entry->save();
        self::recompute($sale->card_id);
    }

    // A fill with a card: the kg that really came out of the nozzle.
    public static function syncSupplement(Supplement $supplement)
    {
        if (!$supplement->card_id) {
            return;
        }

        $entry = CardTransaction::where('type', 'fill')->where('source_id', $supplement->id)->first() ?: new CardTransaction;
        $entry->forceFill([
            'type' => 'fill', 'source_id' => $supplement->id,
            'card_id' => $supplement->card_id, 'kg' => -round((float) $supplement->out_amount_kg, 2),
            'occurred_at' => $supplement->supplied_at ?: now(),
        ]);
        $entry->save();
        self::recompute($supplement->card_id);
    }

    // A ticket made for a card (tickets.card_id): the card paid for it with its kg, so a fill entry for its weight is made when the ticket is, once. A
    // restricted ticket gives the kg back (a reversal entry); when the restriction is lifted that entry keeps its place and goes to 0, so the terminals
    // get the change like any other entry.
    public static function syncTicket(Ticket $ticket)
    {
        if (!$ticket->card_id) {
            return;
        }

        $kg = round((float) $ticket->amount_kg, 2);
        $entry = CardTransaction::where('type', 'fill')->where('source_id', $ticket->barcode)->first() ?: new CardTransaction;
        $entry->forceFill([
            'type' => 'fill', 'source_id' => $ticket->barcode,
            'card_id' => $ticket->card_id, 'kg' => -$kg,
            'occurred_at' => $ticket->sale_date ?: now(), 'comment' => 'ticket ' . $ticket->barcode,
        ]);
        $entry->save();

        $restricted = (int) $ticket->status === (int) config('ticket_statuses.restrict');
        $reversal = CardTransaction::where('type', 'reversal')->where('source_id', $ticket->barcode)->first();
        if ($restricted && !$reversal) {
            CardTransaction::forceCreate([
                'card_id' => $ticket->card_id, 'type' => 'reversal', 'source_id' => $ticket->barcode, 'kg' => $kg, 'occurred_at' => now(),
                'comment' => 'ticket restricted',
            ]);
        } elseif ($reversal && round((float) $reversal->kg, 2) !== ($restricted ? $kg : 0.0)) {
            $reversal->forceFill(['kg' => $restricted ? $kg : 0.0, 'comment' => $restricted ? 'ticket restricted' : 'restriction lifted'])->save();
        }
        self::recompute($ticket->card_id);
    }

    // The sale of a top-up was deleted: an entry that takes its kg back (once).
    public static function reverseSale(Sale $sale)
    {
        if ($sale->sale_type === 'card' && $sale->card_id) {
            self::reverse('topup', $sale->id, $sale->card_id, $sale->deleted_at ?: now());
        }
    }

    public static function reverseSupplement(Supplement $supplement)
    {
        if ($supplement->card_id) {
            self::reverse('fill', $supplement->id, $supplement->card_id, $supplement->deleted_at ?: now());
        }
    }

    private static function reverse($type, $sourceId, $cardId, $when)
    {
        $original = CardTransaction::where('type', $type)->where('source_id', $sourceId)->first();
        if (!$original || CardTransaction::where('type', 'reversal')->where('source_id', $sourceId)->exists()) {
            return;
        }

        CardTransaction::forceCreate([
            'card_id' => $cardId, 'type' => 'reversal', 'source_id' => $sourceId, 'kg' => -$original->kg, 'occurred_at' => $when,
            'comment' => $type === 'topup' ? 'top-up deleted' : 'fill deleted',
        ]);
        self::recompute($cardId);
    }

    // The running balance of every entry of a card, in the order of the moments (then of their making: seq). An entry whose balance changed is sent to
    // the terminals again, and so is the card (its balance goes down with it).
    public static function recompute($cardId)
    {
        $balance = 0.0;
        $changed = false;
        foreach (CardTransaction::where('card_id', $cardId)->orderBy('occurred_at')->orderBy('seq')->get() as $entry) {
            $balance = round($balance + (float) $entry->kg, 2);
            if ($entry->balance_kg === null || round((float) $entry->balance_kg, 2) !== $balance) {
                $entry->balance_kg = $balance;
                $entry->save();
                $changed = true;
            }
        }
        if ($changed) {
            Card::where('id', $cardId)->toBase()->update(['synced' => false, 'updated_at' => now()]);
        }
    }

    // [card id => balance] of every card that has an entry.
    public static function balances()
    {
        return CardTransaction::selectRaw('card_id, SUM(kg) AS kg')->groupBy('card_id')->pluck('kg', 'card_id')
            ->map(function ($kg) {
                return round((float) $kg, 2);
            });
    }

    public static function balance($cardId)
    {
        return round((float) CardTransaction::where('card_id', $cardId)->sum('kg'), 2);
    }

    // Makes the entries of the records that exist: the top-ups and fills of every card, the deleted ones with their reversal. Safe to run again
    // (nothing is made twice). Used by the migration that made the ledger and as a repair (php artisan cards:rebuild-ledger).
    public static function rebuild()
    {
        $made = CardTransaction::count();
        foreach (Sale::withTrashed()->where('sale_type', 'card')->whereNotNull('card_id')->orderBy('created_at')->get() as $sale) {
            self::syncSale($sale);
            if ($sale->trashed()) {
                self::reverseSale($sale);
            }
        }
        foreach (Supplement::withTrashed()->whereNotNull('card_id')->orderBy('supplied_at')->get() as $supplement) {
            self::syncSupplement($supplement);
            if ($supplement->trashed()) {
                self::reverseSupplement($supplement);
            }
        }

        // (the migration that made the ledger runs this before tickets had a card_id)
        if (\Illuminate\Support\Facades\Schema::hasColumn('tickets', 'card_id')) {
            foreach (Ticket::whereNotNull('card_id')->orderBy('sale_date')->get() as $ticket) {
                self::syncTicket($ticket);
            }
        }

        return CardTransaction::count() - $made;
    }
}
