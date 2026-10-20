<?php

namespace App\Support;

use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketLoan;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

// The six tables the Smart210 pulls page by page (synced flag + ack). Small config goes whole instead: PullController::config().
class Pull
{
    const TABLES = ['users', 'customers', 'tickets', 'ticket_loans', 'cards', 'card_transactions'];

    private static $models = [
        'users' => User::class,
        'customers' => Customer::class,
        'tickets' => Ticket::class,
        'ticket_loans' => TicketLoan::class,
        'cards' => Card::class,
        'card_transactions' => CardTransaction::class,
    ];

    public static function pattern()
    {
        return implode('|', self::TABLES);
    }

    public static function key($table)
    {
        $class = self::$models[$table];

        return (new $class)->getKeyName();
    }

    // Every row, soft-deleted ones included: a deletion is a change the Smart210 must receive.
    public static function all($table)
    {
        $class = self::$models[$table];

        // withTrashed() is a scope macro, so method_exists() cannot see it: look for the trait.
        return in_array(SoftDeletes::class, class_uses_recursive($class), true) ? $class::withTrashed() : $class::query();
    }

    public static function unsynced($table)
    {
        return self::all($table)->where('synced', false)->orderBy(self::key($table));
    }

    // A new or re-registered Smart210 has none of the data: everything is pulled again, except tickets that are already
    // finished. It never had those, so they count as synced; if one changes later (say a restriction is lifted) the
    // model resets the flag and the change is sent like any other.
    public static function resetAll()
    {
        $finished = [config('ticket_statuses.supply'), config('ticket_statuses.restrict')];

        foreach (self::TABLES as $table) {
            if ($table === 'tickets') {
                self::all($table)->whereNotIn('status', $finished)->toBase()->update(['synced' => false]);
                self::all($table)->whereIn('status', $finished)->toBase()->update(['synced' => true]);
            } else {
                self::all($table)->toBase()->update(['synced' => false]);
            }
        }
    }

    public static function row($table, $m)
    {
        switch ($table) {
            case 'users':
                return [
                    'id' => (int) $m->id, 'username' => $m->username, 'name' => $m->name, 'role' => $m->role,
                    'pos' => in_array($m->role, config('permissions.pos_roles'), true), 'active' => (bool) $m->active,
                    'pos_salt' => $m->pos_salt, 'pos_hash' => $m->pos_hash, 'deleted' => $m->trashed(),
                ];
            case 'customers':
                return [
                    'id' => (int) $m->id, 'name' => $m->name, 'phone' => $m->phone,
                    'is_self' => (bool) $m->is_self, 'deleted' => $m->trashed(),
                ];
            case 'tickets':
                // Press tickets store no amount: it comes from their spec.
                $specs = config('ticket_specs');
                $kg = $m->amount_kg !== null ? $m->amount_kg : ($specs[$m->spec] ?? 0);

                return [
                    'barcode' => $m->barcode, 'fuel_type' => $m->fuel_type,
                    'spec' => $m->spec === null ? null : (int) $m->spec,
                    'amount_kg' => number_format((float) $kg, 2, '.', ''), 'status' => (int) $m->status,
                    'sale_id' => $m->sale_id, 'sale_date' => $m->sale_date,
                ];
            case 'cards':
                // the card as the web has it: the PIN hash for the Smart210 to check, the status, and the balance (what was topped up less what was supplied,
                // from the rows the web already holds; the terminal adds what it has not sent yet)
                return [
                    'id' => $m->id, 'uid' => $m->uid, 'holder_name' => $m->holder_name, 'holder_phone' => $m->holder_phone, 'fuel_type' => $m->fuel_type,
                    'pin_salt' => $m->pin_salt, 'pin_hash' => $m->pin_hash, 'failed_attempts' => (int) $m->failed_attempts, 'status' => $m->status,
                    'balance_kg' => number_format($m->balance(), 2, '.', ''), 'deleted' => $m->trashed(),
                ];
            case 'card_transactions':
                // the ledger of a card: what came in or went out, and the balance after it (the terminal shows the history from it)
                return [
                    'id' => $m->id, 'card_id' => $m->card_id, 'type' => $m->type, 'kg' => number_format((float) $m->kg, 2, '.', ''),
                    'balance_kg' => number_format((float) $m->balance_kg, 2, '.', ''),
                    'price' => $m->price === null ? null : number_format((float) $m->price, 2, '.', ''),
                    'total_amount' => $m->total_amount === null ? null : number_format((float) $m->total_amount, 2, '.', ''),
                    'source_id' => $m->source_id, 'occurred_at' => (string) $m->occurred_at, 'seq' => (int) $m->seq, 'comment' => $m->comment,
                ];
            case 'ticket_loans':
                return [
                    'id' => $m->id, 'customer_id' => (int) $m->customer_id, 'fuel_type' => $m->fuel_type,
                    'amount_kg' => $m->amount_kg, 'settled_amount_kg' => $m->settled_amount_kg,
                    'loaned_at' => $m->loaned_at, 'sale_id' => $m->sale_id, 'deleted' => $m->trashed(),
                ];
        }
    }
}
