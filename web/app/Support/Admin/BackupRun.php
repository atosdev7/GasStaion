<?php

namespace App\Support\Admin;

use App\Models\Admin\Backup;
use App\Models\Admin\Device;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtAdjustment;
use App\Models\Debts\DebtPayment;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelOutbound;
use App\Models\Fuel\TankBalance;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketInbound;
use App\Models\Tickets\TicketPublish;
use App\Support\Fuel\TankCostLayers;
use App\Support\Tickets\LegacyTickets;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

// Backup = export everything to a zip, then clear finished daily records up to a date.
// Cleared: sales, supplements (by date — rows without a date are never
// cleared), fuel inbounds (by inbound_date), supplied/restricted tickets, and the debts and ticket loans that are settled in full by that day with the payments and
// settlements that settled them (settledDebts, settledLoans: a settled debt owes nothing, so clearing it changes no balance). Kept: the debts and ticket loans that
// still owe something, and the payments and settlements made against them — their balances are computed from those rows, and their age is what the aging shows; the remainders (a remainder outlives its
// supply: its supplement_id may then point at a supply that was cleared, and an opening remainder has none); and the sales, supplements and tickets of the cards
// (the top-ups, the supplies made with a card and the tickets made for a card, `card_id` set), because the balance of a card, its summary and a rebuilt ledger are
// computed from them.
// A backup is refused while a sale or supplement it would clear has not been confirmed (the accountant could no longer review it), nor the ticket
// inbounds and publishes of those days (they are not cleared, but the day is not finished until they are confirmed), and while a
// terminal has not uploaded since the end of that business day (it may still hold records of those days that the web does not know yet).
// What the cleared inbound/outbound rows leave in each tank is written as its tank_balances rows, one per cost (tankBalancesAfter), so live tank
// stock does not change. The net kg per tank is also noted on the backup row (tank_carry), for the history screen only.
class BackupRun
{
    // Not exported: framework tables and the live server's own backup history.
    // The opening balances are not exported: they are entered on the live server at go-live and the backup server is copied from it afterwards (the whole site and its database), so both hold the same rows with the same ids. They are the tank balances
    // (tank_balances: the backup server has every fuel inbound and outbound from the live start, so the ones a backup wrote would be counted twice), the opening debts and
    // ticket loans (debts and ticket_loans without a sale), the old paper tickets at customers (opening_tickets) and the opening remainder tickets (remainders without a
    // supply, and their tickets). A backup rolls the tank balances forward before it clears (tankBalancesAfter); the others keep their rows on the live server, which
    // never clears them, so there is nothing to roll forward.
    // settings and devices: the go-live date, the default costs, the restore marker and the terminals' tokens belong to the server that holds them.
    const NOT_EXPORTED = ['migrations', 'password_resets', 'failed_jobs', 'backups', 'tank_balances', 'opening_tickets', 'settings', 'devices'];

    /** Rows that a backup up to $upTo would clear, per table (soft-deleted rows included). */
    public static function preview($upTo)
    {
        return collect(static::clearable($upTo))->map(function ($query) {
            return $query->count();
        })->all();
    }

    /**
     * What stops a backup up to $upTo, as sentences for the screen (empty: it may run): sales and supplements it would clear that nobody
     * confirmed, and terminals that have not uploaded since the business day of $upTo ended. Deleted records are not waiting for anything.
     */
    public static function blockers($upTo)
    {
        $blockers = [];

        $sales = static::clearable($upTo)['sales']->whereNull('deleted_at')->whereNull('confirmed_at');
        $supplements = static::clearable($upTo)['supplements']->whereNull('deleted_at')->whereNull('confirmed_at');
        $saleCount = (clone $sales)->count();
        $supplementCount = (clone $supplements)->count();
        if ($saleCount || $supplementCount) {
            $oldest = array_filter([$sales->min('sale_date'), $supplements->min('supply_date')]);
            $blockers[] = __('admin.backup_blocked_unconfirmed', [
                'sales' => $saleCount, 'supplements' => $supplementCount, 'date' => substr((string) min($oldest), 0, 10),
            ]);
        }

        // the blank ticket stock of those days is confirmed too (it stays in the tables, but the day is not finished until it is confirmed)
        $inbounds = TicketInbound::where('inbound_date', '<=', $upTo)->whereNull('confirmed_at');
        $publishes = TicketPublish::where('date', '<=', $upTo)->whereNull('confirmed_at');
        $ticketCount = (clone $inbounds)->count() + (clone $publishes)->count();
        if ($ticketCount) {
            $oldest = array_filter([$inbounds->min('inbound_date'), $publishes->min('date')]);
            $blockers[] = __('admin.backup_blocked_tickets', ['count' => $ticketCount, 'date' => substr((string) min($oldest), 0, 10)]);
        }

        // the fuel that came in and went out by hand (cleared with the backup), the money received and the tickets given back for a loan (kept, but the day is not finished
        // until they are confirmed)
        $payments = DebtPayment::where('paid_date', '<=', $upTo)->whereNull('confirmed_at');
        $settlements = TicketLoanSettlement::whereNotNull('date')->where('date', '<=', $upTo)->whereNull('confirmed_at');
        $fuelInbounds = FuelInbound::where('inbound_date', '<=', $upTo)->whereNull('confirmed_at');
        $fuelOutbounds = FuelOutbound::whereNull('supplement_id')->where('outbound_date', '<=', $upTo)->whereNull('confirmed_at');
        $moneyCount = (clone $payments)->count() + (clone $settlements)->count() + (clone $fuelInbounds)->count() + (clone $fuelOutbounds)->count();
        if ($moneyCount) {
            $oldest = array_filter([$payments->min('paid_date'), $settlements->min('date'), $fuelInbounds->min('inbound_date'), $fuelOutbounds->min('outbound_date')]);
            $blockers[] = __('admin.backup_blocked_money', ['count' => $moneyCount, 'date' => substr((string) min($oldest), 0, 10)]);
        }

        list($silent, $dayEnds) = static::silentDevices($upTo);
        if ($silent->isNotEmpty()) {
            $blockers[] = __('admin.backup_blocked_devices', ['devices' => $silent->implode(', '), 'time' => $dayEnds->format('Y-m-d H:i')]);
        }

        return $blockers;
    }

    /**
     * The terminals that have not uploaded since the business day of $date ended (they may still hold records of it that the web does not know yet), and the moment
     * that day ended. [names, moment]
     */
    public static function silentDevices($date)
    {
        $dayEnds = Carbon::parse($date)->addDay()->setTime((int) config('backup.business_day_start_hour'), 0, 0);
        $names = Device::where('initial_sync_done', true)
            ->where(function ($q) use ($dayEnds) {
                $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $dayEnds);
            })->orderBy('name')->pluck('name');

        return [$names, $dayEnds];
    }

    /** Net kg per tank in the rows a backup up to $upTo would clear: inbound minus outbound (the supplies' rows and the ones made by hand). */
    public static function tankCarryForward($upTo)
    {
        $inbound = FuelInbound::where('inbound_date', '<=', $upTo)
            ->selectRaw('tank_id, SUM(amount_kg) AS kg')->groupBy('tank_id')->pluck('kg', 'tank_id');
        // the supplies made with a card are not cleared, so their rows are not carried either; a deleted row counts for nothing
        $supplied = static::outboundsUpTo($upTo)->whereNull('deleted_at')->selectRaw('tank_id, SUM(amount_kg) AS kg')->groupBy('tank_id')->pluck('kg', 'tank_id');

        $result = [];
        foreach ($inbound->keys()->merge($supplied->keys())->unique() as $tankId) {
            $result[$tankId] = round((float) ($inbound[$tankId] ?? 0) - (float) ($supplied[$tankId] ?? 0), 2);
        }

        return $result;
    }

    /**
     * What each tank holds after a backup up to $upTo, as tank balances, one row per cost (TankCostLayers): the balances the tank has now and the fuel inbounds the backup
     * clears are what came in; the outbounds it clears (the supplies, and the ones by hand) take their fuel from the layer of their cost, and one with no cost takes the
     * oldest fuel first. Only the tanks that have rows to clear are in it. A cost that more left at than came in at is a row with a negative balance, so the level of the
     * tank stays right.
     *
     * @return array tank id => list of ['cost', 'balance', 'balance_date', 'note'] (cost null: not known)
     */
    public static function tankBalancesAfter($upTo)
    {
        $inbounds = FuelInbound::where('inbound_date', '<=', $upTo)->orderBy('inbound_date')->orderBy('id')->get()->groupBy('tank_id');
        $outs = static::outboundsUpTo($upTo)->whereNull('deleted_at')->selectRaw('tank_id, cost, SUM(amount_kg) AS kg')->groupBy('tank_id', 'cost')->get()->groupBy('tank_id');

        $plan = [];
        foreach ($inbounds->keys()->merge($outs->keys())->unique() as $tankId) {
            // what is in: the balances the tank has now and the inbounds the backup clears
            $entries = [];
            foreach (TankBalance::where('tank_id', $tankId)->orderBy('balance_date')->orderBy('id')->get() as $row) {
                $entries[] = ['cost' => $row->cost === null ? null : round((float) $row->cost, 4), 'kg' => (float) $row->balance,
                    'date' => $row->balance_date ? substr((string) $row->balance_date, 0, 10) : '', 'note' => $row->note, 'balance' => true];
            }
            foreach ($inbounds->get($tankId, collect()) as $inbound) {
                $entries[] = ['cost' => round((float) $inbound->cost, 4), 'kg' => (float) $inbound->amount_kg, 'date' => substr((string) $inbound->inbound_date, 0, 10), 'note' => null, 'balance' => false];
            }

            // what is out: the outbounds the backup clears, each from the layer of its cost; one with no cost takes the oldest fuel first
            $assigned = [];
            $unassigned = 0.0;
            foreach ($outs->get($tankId, collect()) as $row) {
                if ($row->cost === null) {
                    $unassigned += (float) $row->kg;
                } else {
                    $assigned[TankCostLayers::key($row->cost)] = ($assigned[TankCostLayers::key($row->cost)] ?? 0) + (float) $row->kg;
                }
            }

            // what is left, one row per cost (a cost that more left at than came in at is a row below zero)
            $plan[$tankId] = array_map(function ($layer) use ($upTo) {
                $notes = $layer['notes'];
                array_unshift($notes, 'balance after the backup up to ' . $upTo);

                return ['cost' => $layer['cost'], 'balance' => $layer['kg'], 'balance_date' => $layer['date'] ?: $upTo, 'note' => mb_substr(implode('; ', $notes), 0, 255)];
            }, TankCostLayers::layers($entries, $assigned, $unassigned));
        }

        return $plan;
    }

    // Sync answers "busy" while a backup runs, so a Smart210 never waits on the backup's row locks. The flag lives in the
    // cache because the backup holds those locks inside a transaction nobody else can see into; the expiry is a safety
    // net in case the process dies mid-run.
    const BUSY_KEY = 'backup_running';

    public static function isRunning()
    {
        return Cache::has(self::BUSY_KEY);
    }

    public static function run($upTo, $userId)
    {
        if (config('backup.is_backup_server')) {
            throw new RuntimeException('This is the backup server: clearing here would destroy the history it keeps.');
        }

        $blockers = static::blockers($upTo);
        if ($blockers) {
            throw new RuntimeException(implode(' ', $blockers));
        }

        Cache::put(self::BUSY_KEY, true, 3600);
        try {
            return static::runLocked($upTo, $userId);
        } finally {
            Cache::forget(self::BUSY_KEY);
        }
    }

    private static function runLocked($upTo, $userId)
    {
        if (config('backup.dump_before_clear') && Artisan::call('backup:dump') !== 0) {
            throw new RuntimeException('The safety dump failed, so nothing was cleared.');
        }

        $file = 'backup-upto-' . $upTo . '-' . now()->format('Ymd-His') . '.zip';

        try {
            return DB::transaction(function () use ($upTo, $userId, $file) {
                // Lock every row that will be cleared (and, under REPEATABLE READ, the gaps around
                // them, so a sync can't add a matching row) before exporting. Then the export, the
                // carry-forward and the delete all see exactly the same rows.
                $cleared = [];
                foreach (static::clearable($upTo) as $table => $query) {
                    $cleared[$table] = $query->lockForUpdate()->count();
                }
                $carry = static::tankCarryForward($upTo);
                $balances = static::tankBalancesAfter($upTo);      // before the rows are gone

                static::export($file, $upTo);

                // a debt of a sale that is cleared keeps the day of that sale (its own time may be another day: a sale can be dated back)
                static::keepDatesOfDebts($upTo);

                foreach (static::clearable($upTo) as $query) {
                    static::softDeletes($query->getModel()) ? $query->forceDelete() : $query->delete();
                }

                $backup = new Backup;
                $backup->forceFill([
                    'up_to_date' => $upTo, 'file' => $file, 'cleared' => $cleared, 'tank_carry' => $carry, 'user_id' => $userId,
                ])->save();

                // what the cleared inbounds and outbounds leave becomes the balances of the tanks, one row per cost (they replace the rows the tank had)
                foreach ($balances as $tankId => $rows) {
                    TankBalance::where('tank_id', $tankId)->delete();
                    foreach ($rows as $row) {
                        TankBalance::forceCreate($row + ['tank_id' => $tankId, 'source' => TankBalance::BACKUP, 'backup_id' => $backup->id, 'user_id' => $userId]);
                    }
                }

                return $backup;
            });
        } catch (\Throwable $e) {
            @unlink(static::path($file));
            throw $e;
        }
    }

    // The sale of a debt is cleared with the backup: the day of the sale is written on the debt first, so its place in the aging and the statements does not move.
    private static function keepDatesOfDebts($upTo)
    {
        $sales = Sale::withTrashed()->whereNull('card_id')->whereNotNull('sale_date')->where('sale_date', '<=', $upTo)->select('id');
        foreach (Debt::with('sale')->whereIn('sale_id', $sales)->get() as $debt) {
            if ($debt->sale) {
                $debt->timestamps = false;
                $debt->forceFill(['debt_date' => substr((string) $debt->sale->sale_date, 0, 10)])->save();
            }
        }
    }

    public static function path($file)
    {
        return storage_path('app/' . config('backup.export_dir') . '/' . $file);
    }

    // The outbound rows that go with a backup up to $upTo (deleted ones too): the ones made by hand up to that day, and the rows of the supplies that
    // are cleared (not the ones made with a card, and not a supply that has no date yet).
    private static function outboundsUpTo($upTo)
    {
        $supplements = Supplement::withTrashed()->whereNull('card_id')->whereNotNull('supply_date')->where('supply_date', '<=', $upTo)->select('id');

        return FuelOutbound::withTrashed()->where(function ($query) use ($upTo, $supplements) {
            $query->where(function ($byHand) use ($upTo) {
                $byHand->whereNull('supplement_id')->where('outbound_date', '<=', $upTo);
            })->orWhereIn('supplement_id', $supplements);
        });
    }

    private static function clearable($upTo)
    {
        $endOfDay = Carbon::parse($upTo)->addDay();
        $debtIds = static::settledDebts($upTo);
        $loanIds = static::settledLoans($upTo);
        $byCheckout = function ($model, $column = 'date') use ($upTo) {
            $query = $model::query();
            if (static::softDeletes($model)) {
                $query->withTrashed();
            }

            return $query->whereNotNull($column)->where($column, '<=', $upTo);
        };

        return [
            // the items go before their sales (they are found through them)
            'sale_items' => SaleItem::whereIn('sale_id', Sale::withTrashed()->whereNull('card_id')->whereNotNull('sale_date')->where('sale_date', '<=', $upTo)->select('id')),
            'sales' => $byCheckout(Sale::class, 'sale_date')->whereNull('card_id'),
            // the rows of the supplies go before them (they are found through them)
            'fuel_outbounds' => static::outboundsUpTo($upTo),
            'supplements' => $byCheckout(Supplement::class, 'supply_date')->whereNull('card_id'),
            'fuel_inbounds' => FuelInbound::withTrashed()->where('inbound_date', '<=', $upTo),
            'tickets' => Ticket::where(function ($used) use ($endOfDay) {
                $used->where(function ($q) use ($endOfDay) {
                    $q->where('status', config('ticket_statuses.supply'))->where('supply_date', '<', $endOfDay);
                })->orWhere(function ($q) use ($endOfDay) {
                    $q->where('status', config('ticket_statuses.restrict'))->where('updated_at', '<', $endOfDay);
                });
            })->whereNull('card_id')->where(function ($q) {
                // The used or restricted tickets of a publish dated before go-live stay: a ticket with no row counts as sold on paper before go-live (see
                // LegacyTickets), so clearing the row of a used one would make it usable again.
                $goLive = LegacyTickets::goLive();
                if ($goLive !== null) {
                    $q->whereNull('publish_id')->orWhereNotIn('publish_id', TicketPublish::where('date', '<', $goLive)->select('id'));
                } else {
                    $q->whereRaw('1 = 1');
                }
            }),
            // the debts and ticket loans that are settled in full, with what settled them (the payments and settlements go before them)
            'debt_adjustments' => DebtAdjustment::withTrashed()->whereIn('debt_id', $debtIds),
            'debt_payments' => DebtPayment::withTrashed()->whereIn('debt_id', $debtIds),
            'debts' => Debt::withTrashed()->whereIn('id', $debtIds),
            'ticket_loan_settlements' => TicketLoanSettlement::withTrashed()->whereIn('loan_id', $loanIds),
            'ticket_loans' => TicketLoan::withTrashed()->whereIn('id', $loanIds),
        ];
    }

    /**
     * The ids of the debts that a backup up to $upTo clears: dated on or before it, owing nothing (the amount and the returns that cut it, less the payments tied to it),
     * and with every payment and return of theirs on or before it too, so that nothing of the debt is left outside the backup. Deleted debts do not count (they
     * were cancelled along with a sale). An opening balance is a debt too (its day is its debt_date).
     */
    public static function settledDebts($upTo)
    {
        $paid = DebtPayment::selectRaw('debt_id, SUM(amount) AS total, MAX(paid_date) AS last')->whereNotNull('debt_id')->groupBy('debt_id')->get()->keyBy('debt_id');
        $cut = DebtAdjustment::selectRaw('debt_id, SUM(amount) AS total, MAX(adjusted_date) AS last')->groupBy('debt_id')->get()->keyBy('debt_id');

        $ids = [];
        foreach (Debt::with('sale')->get() as $debt) {
            $day = $debt->sale_id === null ? ($debt->debt_date ?: $debt->created_at) : $debt->display_date;
            $payments = $paid->get($debt->id);
            $returns = $cut->get($debt->id);
            $owed = (float) $debt->amount + ($returns ? (float) $returns->total : 0.0) - ($payments ? (float) $payments->total : 0.0);
            $last = max(substr((string) ($payments ? $payments->last : ''), 0, 10), substr((string) ($returns ? $returns->last : ''), 0, 10));
            if (substr((string) $day, 0, 10) <= $upTo && abs($owed) < 0.005 && ($last === '' || $last <= $upTo)) {
                $ids[] = $debt->id;
            }
        }

        return $ids;
    }

    /** The ids of the ticket loans that a backup up to $upTo clears: the same, for the kg of a loan and its settlements. */
    public static function settledLoans($upTo)
    {
        $settled = TicketLoanSettlement::selectRaw('loan_id, SUM(amount_kg) AS total, MAX(COALESCE(`date`, DATE(settled_at))) AS last')->groupBy('loan_id')->get()->keyBy('loan_id');

        $ids = [];
        foreach (TicketLoan::all() as $loan) {
            $row = $settled->get($loan->id);
            $owed = (float) $loan->amount_kg - ($row ? (float) $row->total : 0.0);
            $day = substr((string) ($loan->date ?: $loan->loaned_at), 0, 10);
            $last = $row ? substr((string) $row->last, 0, 10) : '';
            if ($day <= $upTo && abs($owed) < 0.005 && ($last === '' || $last <= $upTo)) {
                $ids[] = $loan->id;
            }
        }

        return $ids;
    }

    // One JSON-lines file per table (every row, soft-deleted included) plus a manifest. A generic
    // dump of every table, so DB::table rather than models.
    private static function export($file, $upTo)
    {
        $dir = storage_path('app/' . config('backup.export_dir'));
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // the table files first, the zip after them: the zip step can be tried again
        $temps = [];
        $counts = [];
        foreach (static::tables() as $table) {
            $temp = tempnam(sys_get_temp_dir(), 'bk');
            $out = fopen($temp, 'wb');
            $counts[$table] = 0;
            foreach (static::exportRows($table)->cursor() as $row) {
                $json = json_encode($row, JSON_UNESCAPED_UNICODE);
                if ($json === false) {
                    throw new RuntimeException("Could not export a row of $table: " . json_last_error_msg());
                }
                fwrite($out, $json . "\n");
                $counts[$table]++;
            }
            fclose($out);
            $temps[$table] = $temp;
        }
        $manifest = json_encode([
            'database' => DB::connection()->getDatabaseName(),
            'created_at' => now()->toDateTimeString(),
            'up_to_date' => $upTo,
            'rows' => $counts,
        ], JSON_PRETTY_PRINT);

        // On Windows a virus scanner or an indexer may hold the new zip for a moment, and then the last step (renaming the temporary file) fails
        // with "Unknown error": the zip is made again a few times before the backup gives up.
        $target = $dir . '/' . $file;
        $ok = false;
        for ($attempt = 1; $attempt <= 6 && !$ok; $attempt++) {
            if ($attempt > 1) {
                @unlink($target);
                usleep(500000);
            }
            $zip = new ZipArchive;
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                continue;
            }
            foreach ($temps as $table => $temp) {
                $zip->addFile($temp, $table . '.jsonl');
            }
            $zip->addFromString('manifest.json', $manifest);
            $ok = @$zip->close();
        }
        foreach ($temps as $temp) {
            @unlink($temp);
        }
        if (!$ok) {
            @unlink($target);
            throw new RuntimeException('Could not write ' . $file);
        }
    }

    private static function softDeletes($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }

    // Deleted sales are not backed up, nor are the debts and ticket loans that were cancelled along with them. The opening balances are left out: a debt or a ticket loan
    // with no sale, a remainder with no supply and its ticket.
    private static function exportRows($table)
    {
        $query = DB::table($table);

        if ($table === 'sales') {
            $query->whereNull('deleted_at');
        } elseif ($table === 'sale_items') {
            $query->whereIn('sale_id', DB::table('sales')->whereNull('deleted_at')->select('id'));
        } elseif (in_array($table, ['debts', 'ticket_loans'], true)) {
            $query->whereNotNull('sale_id')->whereNull('deleted_at');
        } elseif ($table === 'remainders') {
            $query->whereNotNull('supplement_id');
        } elseif ($table === 'tickets') {
            $query->whereNotIn('barcode', DB::table('remainders')->whereNull('supplement_id')->select('ticket_barcode'));
        }

        return $query;
    }

    private static function tables()
    {
        return collect(DB::select('SHOW TABLES'))->map(function ($row) {
            return array_values((array) $row)[0];
        })->reject(function ($table) {
            return in_array($table, self::NOT_EXPORTED, true);
        })->values();
    }
}
