<?php

namespace App\Models\Tickets;

use App\Models\Concerns\ResetsSynced;
use App\Models\Debts\TicketLoanSettlement;
use App\Support\Cards\CardLedger;
use App\Support\Tickets\Barcode;
use App\Models\Sales\Sale;
use App\Models\Supplements\Remainder;
use App\Models\Supplements\Supplement;
use Illuminate\Database\Eloquent\Model;

// Table created via raw SQL (see the create_tickets_table migration — the non-auto-increment
// `barcode` primary key crashes the local MariaDB if built through Schema::create), but it's a
// normal Eloquent model otherwise.
class Ticket extends Model
{
    use ResetsSynced;

    // A remainder ticket that is used up (supplied, or used for a loan) completes its remainder: the day is complete_date. A ticket that
    // goes back to another state takes it away again.
    protected static function boot()
    {
        parent::boot();

        // a ticket made for a card is an entry of the card's ledger (made once, and undone when the ticket is restricted)
        static::saved(function (Ticket $ticket) {
            if ($ticket->card_id) {
                CardLedger::syncTicket($ticket);
            }
        });

        // a ticket that joins a supply or leaves it (the supply is deleted) changes what the supply gives back to a card
        static::saved(function (Ticket $ticket) {
            if (!$ticket->wasChanged('supplement_id')) {
                return;
            }
            foreach (array_unique(array_filter([$ticket->supplement_id, $ticket->getOriginal('supplement_id')])) as $id) {
                $supplement = Supplement::withTrashed()->find($id);
                if ($supplement) {
                    CardLedger::syncRefund($supplement);
                }
            }
        });

        // (a ticket that is created already supplied counts too: a Smart210 that made a remainder ticket and used it again between two uploads reports it
        // for the first time in its final status, and Eloquent reports no change of status on a create)
        static::saved(function (Ticket $ticket) {
            if (!$ticket->wasChanged('status') && !$ticket->wasRecentlyCreated) {
                return;
            }
            if ((int) $ticket->status === (int) config('ticket_statuses.supply')) {
                $day = $ticket->supply_date ? substr((string) $ticket->supply_date, 0, 10) : today()->toDateString();
                foreach (Remainder::where('ticket_barcode', $ticket->barcode)->whereNull('complete_date')->get() as $remainder) {
                    $remainder->forceFill(['complete_date' => $day])->save();
                }
            } elseif (!$ticket->wasRecentlyCreated) {
                foreach (Remainder::where('ticket_barcode', $ticket->barcode)->whereNotNull('complete_date')->get() as $remainder) {
                    $remainder->forceFill(['complete_date' => null])->save();
                }
            }
        });
    }

    protected $primaryKey = 'barcode';
    protected $keyType = 'string'; // a zero-padded 16-digit code, not a quantity — see App\Support\Tickets\Barcode
    public $incrementing = false;

    // What a ticket issued by a POS is (the second digit of its code): direct (a spot sale), self, other, ticket_loan, card (made for a card) or remainder; null for a press ticket.
    public function posType()
    {
        return Barcode::posType($this->code);
    }

    // The batch the ticket was published in.
    public function batch()
    {
        return $this->belongsTo(TicketPublish::class, 'publish_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function loanSettlement()
    {
        return $this->hasOne(TicketLoanSettlement::class, 'ticket_barcode', 'barcode');
    }

    public function supplement()
    {
        return $this->belongsTo(Supplement::class);
    }
}
