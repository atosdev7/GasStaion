<?php

namespace App\Support\Supplements;

use App\Support\Tickets\Barcode;

// The rules about which tickets make one supply: all of one kind (press, spot sale, self use, other use, ticket loan, card: the kind of a ticket is the second digit
// of its reserved code, a ticket with any other code is a press ticket); a remainder ticket may also go with press tickets or with spot sale tickets, not with the
// others; and the tickets of a card are all of one card. The Smart210 keeps to the same rules (SupplyQueue::mixProblem).
class SupplyTickets
{
    // The kind of a ticket: press, direct (spot sale), self, other, ticket_loan, card or remainder.
    public static function kindOf($ticket)
    {
        $kind = Barcode::posType($ticket->code);

        return $kind === null ? 'press' : $kind;
    }

    /**
     * Why these tickets cannot be one supply, as the key of the message in lang/supplements.php; null when they can.
     *
     * @param \Illuminate\Support\Collection|array $tickets tickets with their `code` and `card_id`
     */
    public static function problem($tickets)
    {
        $tickets = collect($tickets);
        $kinds = $tickets->map(function ($ticket) {
            return self::kindOf($ticket);
        });
        $bases = $kinds->reject(function ($kind) {
            return $kind === 'remainder';
        })->unique()->values();

        if ($bases->count() > 1) {
            return 'tickets_mixed_kind';
        }
        if ($bases->count() === 1 && $kinds->contains('remainder') && !in_array($bases->first(), ['press', 'direct'], true)) {
            return 'tickets_remainder_with';
        }
        if ($bases->first() === 'card' && $tickets->filter(function ($ticket) {
            return self::kindOf($ticket) === 'card';
        })->pluck('card_id')->unique()->count() > 1) {
            return 'tickets_mixed_card';
        }
        // only remainder tickets: nothing to say
        return null;
    }
}
