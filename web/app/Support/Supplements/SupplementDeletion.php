<?php

namespace App\Support\Supplements;

use App\Models\Supplements\Remainder;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Deleting a supply (the admin only; the supplies are made on the Smart210). It undoes what the supply did: the tickets it used are sold again, the
// remainder ticket it made (a paper ticket may exist) can never be used and its remainder row goes, the outbound and the card ledger follow the deleted
// supply. It is refused while the supply is confirmed (a manager takes the confirmation back first) or when its remainder ticket
// was already used.
class SupplementDeletion
{
    // null when it may be deleted, otherwise why not: confirmed or remainder_used.
    public static function blocker(Supplement $supplement)
    {
        if ($supplement->confirmed_at) {
            return 'confirmed';
        }

        $remainder = Remainder::where('supplement_id', $supplement->id)->first();
        if ($remainder) {
            $ticket = Ticket::find($remainder->ticket_barcode);
            if ($ticket && (int) $ticket->status !== (int) config('ticket_statuses.sale')) {
                return 'remainder_used';
            }
        }

        return null;
    }

    public static function delete($supplementId)
    {
        return DB::transaction(function () use ($supplementId) {
            $supplement = Supplement::lockForUpdate()->findOrFail($supplementId); // an already deleted supply is not found

            if ($reason = self::blocker($supplement)) {
                throw ValidationException::withMessages(['supplement' => __('supplements.blocked_' . $reason)]);
            }

            Ticket::where('supplement_id', $supplement->id)->update([
                'status' => config('ticket_statuses.sale'), 'supplement_id' => null, 'supply_date' => null, 'synced' => false,
            ]);

            Remainder::where('supplement_id', $supplement->id)->get()->each(function ($remainder) {
                Ticket::where('barcode', $remainder->ticket_barcode)->update(['status' => config('ticket_statuses.restrict'), 'synced' => false]);
                $remainder->delete();
            });

            $supplement->delete();

            return $supplement;
        });
    }
}