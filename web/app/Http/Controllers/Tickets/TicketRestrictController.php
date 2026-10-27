<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Sales\Sale;
use App\Models\Tickets\Ticket;
use App\Support\Admin\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Blocks a published or sold ticket (lost, fraud, non-payment). Nothing else needs to check for
// it: selling needs status=publish, and supplying or settling a loan needs status=sale.
class TicketRestrictController extends Controller
{
    public function restrict(Request $request, $barcode)
    {
        $restricted = DB::transaction(function () use ($barcode) {
            $ticket = Ticket::lockForUpdate()->findOrFail($barcode);

            if (!in_array($ticket->status, [config('ticket_statuses.publish'), config('ticket_statuses.sale')], true)) {
                return false;
            }

            $ticket->forceFill(['status' => config('ticket_statuses.restrict')])->save();

            return true;
        });

        return $this->back($request, $barcode, $restricted, 'restrict', 'tickets.ticket_restricted', 'tickets.ticket_not_restrictable');
    }

    public function lift(Request $request, $barcode)
    {
        $lifted = DB::transaction(function () use ($barcode) {
            $ticket = Ticket::lockForUpdate()->findOrFail($barcode);

            if ($ticket->status !== config('ticket_statuses.restrict')) {
                return false;
            }

            // A ticket whose sale was deleted for wrong input must never be usable again: lifting its restriction would
            // hand out fuel that was never paid for.
            if ($ticket->sale_id !== null && !Sale::whereKey($ticket->sale_id)->exists()) {
                return 'sale_deleted';
            }

            // The sale link survives a restriction, so the previous status can be derived from it. A remainder ticket
            // has no sale but is already paid for, so it goes back to sold, never to blank stock.
            // (so has a ticket made for a card: the card paid for it)
            $sold = $ticket->sale_id !== null || in_array(\App\Support\Tickets\Barcode::posType($ticket->code), ['remainder', 'card'], true) || $ticket->card_id !== null;
            $previous = $sold ? 'sale' : 'publish';
            $ticket->forceFill(['status' => config('ticket_statuses.' . $previous)])->save();

            return true;
        });

        return $this->back(
            $request, $barcode, $lifted === true, 'restrict_lift', 'tickets.restriction_lifted',
            $lifted === 'sale_deleted' ? 'tickets.ticket_sale_deleted' : 'tickets.ticket_not_restricted'
        );
    }

    // Back to the lookup page of the ticket. When the action came from the table of tickets, its filters (and page) come back too.
    private function back(Request $request, $barcode, $done, $action, $okMessage, $errorMessage)
    {
        parse_str((string) $request->input('back', ''), $filters);
        $filters = array_intersect_key($filters, array_flip(['tab', 'type', 'printed', 'status', 'fuel_type', 'spec', 'batch', 'published', 'sold', 'supplied', 'q', 'page']));
        $redirect = redirect()->route('tickets.lookup', array_filter($filters, 'is_scalar'));

        if (!$done) {
            return $redirect->withErrors(['barcode' => __($errorMessage)]);
        }

        Audit::log('ticket_' . $action, $barcode);

        return $redirect->with('status', __($okMessage));
    }
}
