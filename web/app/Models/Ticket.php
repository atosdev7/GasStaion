<?php

namespace App\Models;

use App\Models\Concerns\ResetsSynced;
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

        static::saved(function (Ticket $ticket) {
            if (!$ticket->wasChanged('status')) {
                return;
            }
            if ((int) $ticket->status === (int) config('ticket_statuses.supply')) {
                $day = $ticket->supply_date ? substr((string) $ticket->supply_date, 0, 10) : today()->toDateString();
                foreach (Remainder::where('ticket_barcode', $ticket->barcode)->whereNull('complete_date')->get() as $remainder) {
                    $remainder->forceFill(['complete_date' => $day])->save();
                }
            } else {
                foreach (Remainder::where('ticket_barcode', $ticket->barcode)->whereNotNull('complete_date')->get() as $remainder) {
                    $remainder->forceFill(['complete_date' => null])->save();
                }
            }
        });
    }

    protected $primaryKey = 'barcode';
    protected $keyType = 'string'; // a zero-padded 16-digit code, not a quantity — see App\Support\Barcode
    public $incrementing = false;

    // The batch the ticket was published in (kept even when the batch is deleted: the ticket then no longer exists, but a backup may have
    // removed the used tickets of an old batch and not the batch).
    public function batch()
    {
        return $this->belongsTo(TicketPublish::class, 'publish_id')->withTrashed();
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
