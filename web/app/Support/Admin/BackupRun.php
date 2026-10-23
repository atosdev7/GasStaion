<?php

namespace App\Support\Admin;

use App\Models\Admin\Backup;
use App\Models\Admin\Device;
use App\Models\Fuel\FuelInbound;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

// Backup = export everything to a zip, then clear finished daily records up to a date.
// Cleared: sales, supplements (by date — rows without a date are never
// cleared), fuel inbounds (by inbound_date), and supplied/restricted tickets. Kept: debts,
// debt_payments, ticket_loans, ticket_loan_settlements — their balances are computed from those rows; the remainders (a remainder outlives its
// supply: its supplement_id may then point at a supply that was cleared, and an opening remainder has none); and the sales and supplements of the cards
// (the top-ups and the supplies made with a card, `card_id` set), because the balance of a card is computed from them.
// A backup is refused while a sale or supplement it would clear has not been confirmed (the accountant could no longer review it), and while a
// terminal has not uploaded since the end of that business day (it may still hold records of those days that the web does not know yet).
// The net kg each tank loses from the cleared inbound/supplied rows is recorded on the backup row
// (see TankLevels), so live tank stock doesn't change and initial_tank_balances stays the go-live value.
class BackupRun
{
    // Not exported: framework tables and the live server's own backup history.
    const NOT_EXPORTED = ['migrations', 'password_resets', 'failed_jobs', 'backups'];

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

        $dayEnds = Carbon::parse($upTo)->addDay()->setTime((int) config('backup.business_day_start_hour'), 0, 0);
        $silent = Device::where('initial_sync_done', true)
            ->where(function ($q) use ($dayEnds) {
                $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $dayEnds);
            })->orderBy('name')->pluck('name');
        if ($silent->isNotEmpty()) {
            $blockers[] = __('admin.backup_blocked_devices', ['devices' => $silent->implode(', '), 'time' => $dayEnds->format('Y-m-d H:i')]);
        }

        return $blockers;
    }

    /** Net kg per tank in the rows a backup up to $upTo would clear: inbound minus supplied. */
    public static function tankCarryForward($upTo)
    {
        $inbound = FuelInbound::where('inbound_date', '<=', $upTo)
            ->selectRaw('tank_id, SUM(amount_kg) AS kg')->groupBy('tank_id')->pluck('kg', 'tank_id');
        // the supplies made with a card are not cleared, so they are not carried either
        $supplied = Supplement::whereNotNull('supplements.supply_date')->whereNull('supplements.card_id')->where('supplements.supply_date', '<=', $upTo)
            ->join('nozzles', 'nozzles.id', '=', 'supplements.nozzle_id')
            ->selectRaw('nozzles.tank_id, SUM(supplements.out_amount_kg) AS kg')->groupBy('nozzles.tank_id')->pluck('kg', 'tank_id');

        $result = [];
        foreach ($inbound->keys()->merge($supplied->keys())->unique() as $tankId) {
            $result[$tankId] = round((float) ($inbound[$tankId] ?? 0) - (float) ($supplied[$tankId] ?? 0), 2);
        }

        return $result;
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

                static::export($file, $upTo);

                foreach (static::clearable($upTo) as $query) {
                    static::softDeletes($query->getModel()) ? $query->forceDelete() : $query->delete();
                }

                $backup = new Backup;
                $backup->forceFill([
                    'up_to_date' => $upTo, 'file' => $file, 'cleared' => $cleared, 'tank_carry' => $carry, 'user_id' => $userId,
                ])->save();

                return $backup;
            });
        } catch (\Throwable $e) {
            @unlink(static::path($file));
            throw $e;
        }
    }

    public static function path($file)
    {
        return storage_path('app/' . config('backup.export_dir') . '/' . $file);
    }

    private static function clearable($upTo)
    {
        $endOfDay = Carbon::parse($upTo)->addDay();
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
            'supplements' => $byCheckout(Supplement::class, 'supply_date')->whereNull('card_id'),
            'fuel_inbounds' => FuelInbound::withTrashed()->where('inbound_date', '<=', $upTo),
            'tickets' => Ticket::where(function ($q) use ($endOfDay) {
                $q->where('status', config('ticket_statuses.supply'))->where('supply_date', '<', $endOfDay);
            })->orWhere(function ($q) use ($endOfDay) {
                $q->where('status', config('ticket_statuses.restrict'))->where('updated_at', '<', $endOfDay);
            }),
        ];
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

    // Deleted sales are not backed up, nor are the debts and ticket loans that were cancelled along with them (the ones
    // that came from a sale: a deleted opening balance has no sale and is kept like any other row).
    private static function exportRows($table)
    {
        $query = DB::table($table);

        if ($table === 'sales') {
            $query->whereNull('deleted_at');
        } elseif ($table === 'sale_items') {
            $query->whereIn('sale_id', DB::table('sales')->whereNull('deleted_at')->select('id'));
        } elseif (in_array($table, ['debts', 'ticket_loans'], true)) {
            $query->where(function ($q) {
                $q->whereNull('deleted_at')->orWhereNull('sale_id');
            });
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
