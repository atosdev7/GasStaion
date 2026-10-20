<?php

namespace Tests\Feature;

use App\Models\Admin\Backup;
use App\Models\Sales\Customer;
use App\Models\Debts\Debt;
use App\Models\Debts\DebtPayment;
use App\Models\Admin\Device;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\InitialTankBalance;
use App\Models\Fuel\Nozzle;
use App\Models\Sales\Sale;
use App\Models\Supplements\Supplement;
use App\Models\Fuel\Tank;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Admin\User;
use App\Support\Dashboard\TankLevels;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private const UP_TO = '2026-09-15';

    private $customer;
    private $tank;
    private $nozzle;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'backup.dump_before_clear' => false,
            'backup.export_dir' => 'backups/test-exports',
            'backup.dump_dir' => 'backups/test-daily',
        ]);
        $this->customer = Customer::forceCreate(['name' => 'ACME']);
        $this->tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => 5000]);
        $device = Device::forceCreate(['name' => 'Pump 1']);
        $this->nozzle = Nozzle::forceCreate(['device_id' => $device->id, 'number' => 1, 'tank_id' => $this->tank->id, 'fuel_type' => 'D']);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(storage_path('app/backups/test-exports'));
        (new Filesystem)->deleteDirectory(storage_path('app/backups/test-daily'));
        parent::tearDown();
    }

    private function as($role)
    {
        $user = User::where('role', $role)->first() ?: User::forceCreate([
            'name' => $role, 'username' => $role, 'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);

        return $this->actingAs($user);
    }

    private function sale($checkoutDate)
    {
        return Sale::forceCreate([
            'customer_id' => $this->customer->id, 'sale_type' => 'ticket',
            'fuel_type' => 'D', 'price' => 2, 'amount_kg' => 10, 'payment_method' => 'cash', 'total_amount' => 20,
            'paid_amount' => 0, 'unpaid_amount' => 20, 'usage' => 'sale', 'sale_date' => $checkoutDate, 'user_id' => 1,
        ]);
    }

    private function supplement($kg, $checkoutDate)
    {
        return Supplement::forceCreate([
            'fuel_type' => 'D', 'nozzle_id' => $this->nozzle->id, 'device_id' => $this->nozzle->device_id,
            'out_amount_lt' => $kg, 'out_amount_kg' => $kg, 'preset_kg' => $kg, 'supplied_at' => ($checkoutDate ?: '2026-09-28') . ' 11:00:00',
            'supply_date' => $checkoutDate,
        ]);
    }

    private function inbound($kg, $date)
    {
        return FuelInbound::forceCreate([
            'tank_id' => $this->tank->id, 'fuel_type' => 'D', 'amount_kg' => $kg, 'cost' => 1, 'inbound_date' => $date, 'user_id' => 1,
        ]);
    }

    private function ticket($serial, $status, $date)
    {
        return Ticket::forceCreate([
            'barcode' => sprintf('000000600%07d', $serial), 'fuel_type' => 'D', 'spec' => 0, 'code' => '06', 'serial' => $serial,
            'status' => config('ticket_statuses.' . $status), 'supply_date' => $status === 'supply' ? $date : null,
            'created_at' => $date, 'updated_at' => $date,
        ]);
    }

    // Two of each kind inside the cut-off, and rows that must survive.
    private function scenario()
    {
        $old = $this->sale('2026-09-10');
        $this->sale('2026-09-15');
        $this->sale('2026-09-20');       // after the cut-off
        $this->sale('2026-09-12')->delete(); // soft-deleted, still cleared

        InitialTankBalance::create(['tank_id' => $this->tank->id, 'amount_kg' => 1000]);
        $this->inbound(500, '2026-09-05');
        $this->inbound(300, '2026-09-20');
        $this->supplement(100, '2026-09-10');
        $this->supplement(40, '2026-09-20');
        $this->supplement(7, null);

        $this->ticket(1, 'supply', '2026-09-10');
        $this->ticket(2, 'restrict', '2026-09-11');
        $this->ticket(3, 'supply', '2026-09-20');
        $this->ticket(4, 'sale', '2026-09-10');

        $debt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => $old->id, 'amount' => 20, 'payment_method' => 'cash', 'user_id' => 1]);
        DebtPayment::forceCreate([
            'customer_id' => $this->customer->id, 'debt_id' => $debt->id, 'amount' => 5, 'payment_method' => 'cash',
            'paid_date' => '2026-09-11', 'date' => '2026-09-11', 'user_id' => 1,
        ]);
        $loan = TicketLoan::forceCreate([
            'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 10, 'settled_amount_kg' => 5,
            'date' => '2026-09-10', 'user_id' => 1,
        ]);
        TicketLoanSettlement::forceCreate([
            'loan_id' => $loan->id, 'ticket_barcode' => '0000006000000001', 'amount_kg' => 5, 'settled_at' => '2026-09-10',
            'date' => '2026-09-10', 'user_id' => 1,
        ]);
    }

    public function testPreviewCountsWhatWouldBeCleared()
    {
        $this->scenario();

        $response = $this->as('admin')->get('/backup?up_to=' . self::UP_TO)->assertOk();

        $this->assertSame(['sale_items' => 0, 'sales' => 3, 'supplements' => 1, 'fuel_inbounds' => 1, 'tickets' => 2], $response->viewData('preview'));
        $this->assertEquals([$this->tank->id => 400.0], $response->viewData('carry')); // 500 in - 100 out
        $this->assertSame(0, Backup::count());
    }

    public function testRemaindersAreNeverCleared()
    {
        $this->scenario();
        $supply = Supplement::whereNotNull('supply_date')->where('supply_date', '<=', self::UP_TO)->first();   // a supply that the backup clears
        $fromSupply = \App\Models\Supplements\Remainder::forceCreate([
            'supplement_id' => $supply->id, 'ticket_barcode' => '0000021000000001', 'fuel_type' => 'D', 'amount_kg' => 2, 'remainder_date' => '2026-09-10', 'created_at' => '2026-09-10 10:00:00',
        ]);
        $opening = \App\Models\Supplements\Remainder::forceCreate([
            'supplement_id' => null, 'ticket_barcode' => '0000021000000002', 'fuel_type' => 'D', 'amount_kg' => 3, 'remainder_date' => '2026-09-01', 'created_at' => '2026-09-01 10:00:00',
        ]);

        $preview = $this->as('admin')->get('/backup?up_to=' . self::UP_TO)->assertOk()->viewData('preview');
        $this->assertArrayNotHasKey('remainders', $preview);

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup');

        $this->assertNull(Supplement::find($supply->id));                                           // the supply is gone...
        $this->assertNotNull(\App\Models\Supplements\Remainder::find($fromSupply->id));                        // ...its remainder stays
        $this->assertNotNull(\App\Models\Supplements\Remainder::find($opening->id));
        $this->as('admin')->get('/remainders')->assertOk()->assertSee(__('supplements.remainder_supply_cleared'))->assertSee(__('supplements.remainder_opening'));
    }

    public function testRunExportsClearsAndKeepsTankLevelsAndBalances()
    {
        $this->scenario();
        $before = TankLevels::all()->first()->current; // 1000 + 800 - 147

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup')->assertSessionHas('status');

        $this->assertSame(1, Sale::withTrashed()->count());
        $this->assertSame(2, Supplement::count());
        $this->assertSame(1, FuelInbound::count());
        $this->assertSame(['0000006000000003', '0000006000000004'], Ticket::orderBy('barcode')->pluck('barcode')->all());

        $this->assertEquals(1000, InitialTankBalance::where('tank_id', $this->tank->id)->value('amount_kg')); // go-live value untouched
        $this->assertEquals($before, TankLevels::all()->first()->current);

        $this->assertSame(1, Debt::count());
        $this->assertSame(1, DebtPayment::count());
        $this->assertSame(1, TicketLoan::count());
        $this->assertSame(1, TicketLoanSettlement::count());

        $backup = Backup::first();
        $this->assertSame(3, $backup->cleared['sales']);
        $this->assertEquals([$this->tank->id => 400], $backup->tank_carry);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/backups/test-exports/' . $backup->file)));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(3, $manifest['rows']['sales']); // exported before clearing; the deleted sale is not backed up
        $this->assertCount(3, array_filter(explode("\n", $zip->getFromName('sales.jsonl'))));
        $this->assertStringContainsString('"amount_kg":"1000.00"', $zip->getFromName('initial_tank_balances.jsonl'));
        $this->assertFalse($zip->getFromName('backups.jsonl'));
        $zip->close();
    }

    public function testDeletedSalesAndWhatWasCancelledWithThemAreNotExported()
    {
        $kept = $this->sale('2026-09-10');
        $gone = $this->sale('2026-09-10');
        $gone->delete();

        $cancelledDebt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => $gone->id, 'amount' => 20, 'payment_method' => 'cash', 'user_id' => 1]);
        $cancelledDebt->delete();
        $openingDebt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => null, 'amount' => 7, 'payment_method' => 'cash', 'user_id' => 1]);
        $openingDebt->delete(); // a deleted opening balance has no sale: it is kept like any other row
        $liveDebt = Debt::forceCreate(['customer_id' => $this->customer->id, 'sale_id' => $kept->id, 'amount' => 20, 'payment_method' => 'cash', 'user_id' => 1]);

        $cancelledLoan = TicketLoan::forceCreate(['loaned_at' => now(), 'customer_id' => $this->customer->id, 'fuel_type' => 'D', 'amount_kg' => 5, 'sale_id' => $gone->id, 'user_id' => 1]);
        $cancelledLoan->delete();

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/backups/test-exports/' . Backup::first()->file)));
        $lines = function ($table) use ($zip) {
            return array_filter(explode("\n", $zip->getFromName($table . '.jsonl')));
        };

        $sales = implode("\n", $lines('sales'));
        $this->assertStringContainsString($kept->id, $sales);
        $this->assertStringNotContainsString($gone->id, $sales);

        $debts = implode("\n", $lines('debts'));
        $this->assertStringNotContainsString($cancelledDebt->id, $debts);
        $this->assertStringContainsString($openingDebt->id, $debts);
        $this->assertStringContainsString($liveDebt->id, $debts);

        $this->assertSame([], $lines('ticket_loans')); // the only loan was cancelled with its sale
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(1, $manifest['rows']['sales']);
        $this->assertSame(2, $manifest['rows']['debts']);
        $zip->close();
    }

    public function testSyncIsOnlyBusyWhileTheBackupRuns()
    {
        $this->assertFalse(\App\Support\Admin\BackupRun::isRunning());

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup');
        $this->assertFalse(\App\Support\Admin\BackupRun::isRunning()); // cleared when it finished

        config(['backup.dump_before_clear' => true, 'backup.mysqldump' => 'Z:\\no\\such\\mysqldump.exe']);
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');
        $this->assertFalse(\App\Support\Admin\BackupRun::isRunning()); // and also when it failed
    }

    public function testDebtOfAClearedSaleKeepsItsDateOnThePaymentsPage()
    {
        $this->scenario();
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);

        $this->get('/payments')->assertOk()->assertDontSee(__('report.opening_balance'));
    }

    public function testTodayCannotBeCleared()
    {
        $this->as('admin')->post('/backup', ['up_to' => today()->toDateString()])->assertSessionHasErrors('up_to');
        $this->assertSame(0, Backup::count());
    }

    public function testAFailedSafetyDumpClearsNothing()
    {
        config(['backup.dump_before_clear' => true, 'backup.mysqldump' => 'Z:\\no\\such\\mysqldump.exe']);
        $this->sale('2026-09-10');

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');

        $this->assertSame(1, Sale::count());
        $this->assertSame(0, Backup::count());
    }

    public function testDownloadReturnsTheZip()
    {
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);

        $this->get('/backup/' . Backup::first()->id . '/download')->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=' . Backup::first()->file);
    }

    public function testOnlyAdminCanBackUp()
    {
        foreach (['manager', 'accountant', 'assistant', 'cashier'] as $role) {
            $this->as($role)->get('/backup')->assertForbidden();
            $this->as($role)->post('/backup', ['up_to' => self::UP_TO])->assertForbidden();
        }
    }

    public function testImportRefusesOutsideTheBackupServer()
    {
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);

        $this->artisan('backup:import', ['file' => storage_path('app/backups/test-exports/' . Backup::first()->file)])
            ->assertExitCode(1);
    }

    public function testTheItemsOfAClearedSaleAreExportedAndClearedWithIt()
    {
        $old = $this->sale('2026-09-10');
        $late = $this->sale('2026-09-20');       // after the cut-off
        $deleted = $this->sale('2026-09-12');
        foreach ([$old, $late, $deleted] as $sale) {
            \App\Models\Sales\SaleItem::forceCreate(['sale_id' => $sale->id, 'spec' => 0, 'start_serial' => 1, 'end_serial' => 2, 'quantity' => 2, 'amount_kg' => 10]);
        }
        $deleted->delete();

        $this->assertSame(2, $this->as('admin')->get('/backup?up_to=' . self::UP_TO)->viewData('preview')['sale_items']); // the old sale's and the deleted one's

        $this->post('/backup', ['up_to' => self::UP_TO])->assertRedirect('/backup')->assertSessionHas('status');

        $this->assertSame([$late->id], \App\Models\Sales\SaleItem::pluck('sale_id')->all()); // the cleared sales took their items along
        $backup = Backup::first();
        $this->assertSame(2, $backup->cleared['sale_items']); // the old sale's item and the deleted sale's
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/backups/test-exports/' . $backup->file)));
        $this->assertSame(2, (int) json_decode($zip->getFromName('manifest.json'), true)['rows']['sale_items']); // old and late; a deleted sale's are not backed up
    }

    public function testAnExportOfSuppliesMadeBeforeTheSupplyDateRenameStillImports()
    {
        $file = tempnam(sys_get_temp_dir(), 'bk') . '.zip';
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('supplements.jsonl', json_encode([
            'id' => '22222222-2222-2222-2222-222222222222', 'fuel_type' => 'D', 'nozzle_id' => $this->nozzle->id, 'liters_dispensed' => '10.00', 'amount_kg' => '8.00',
            'preset_kg' => '10.00', 'manual_reading' => 0, 'supplied_at' => '2026-10-01 10:00:00', 'date' => '2026-10-01',
        ]) . "
");
        $zip->addFromString('manifest.json', json_encode(['rows' => ['supplements' => 1], 'up_to_date' => '2026-10-01']));
        $zip->close();

        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $supplement = Supplement::find('22222222-2222-2222-2222-222222222222');
        $this->assertSame('2026-10-01', substr((string) $supplement->supply_date, 0, 10)); // its old `date`
        $this->assertSame('auto', $supplement->mode);                                         // and the default for what the export did not have
    }

    public function testAnExportOfRemaindersMadeBeforeTheirFieldsChangedStillImports()
    {
        $supplement = $this->supplement(10, '2026-10-01');
        $file = tempnam(sys_get_temp_dir(), 'bk') . '.zip';
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('remainders.jsonl', json_encode([
            'id' => '33333333-3333-3333-3333-333333333333', 'supplement_id' => $supplement->id, 'ticket_barcode' => '0000025500000021', 'fuel_type' => 'D',
            'amount_kg' => '2.00', 'remainder_at' => '2026-10-01 11:05:00', 'date' => '2026-10-01', 'created_at' => '2026-10-02 00:00:00',
        ]) . "
");
        $zip->addFromString('manifest.json', json_encode(['rows' => ['remainders' => 1], 'up_to_date' => '2026-10-01']));
        $zip->close();

        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $remainder = \App\Models\Supplements\Remainder::find('33333333-3333-3333-3333-333333333333');
        $this->assertSame('2026-10-01', substr((string) $remainder->remainder_date, 0, 10));   // its old `date`
        $this->assertNull($remainder->complete_date);
    }

    public function testImportMergesExportsWithoutDuplicates()
    {
        $this->scenario();
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);
        $this->assertSame(1, Sale::withTrashed()->count());
        $file = storage_path('app/backups/test-exports/' . Backup::first()->file);

        // Same database standing in for the backup server: the cleared rows come back from the
        // export, the rows still here are updated in place, and a second import changes nothing.
        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $this->assertSame(3, Sale::withTrashed()->count()); // the deleted sale was cleared but, being deleted, not exported
        $this->assertSame(0, Sale::onlyTrashed()->count());
        $this->assertSame(4, Ticket::count());
        $this->assertSame(1, Debt::count());
    }

    public function testBackupServerTankLevelsMatchLiveAfterImport()
    {
        $this->scenario();
        $before = TankLevels::all()->first()->current;
        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO]);
        $file = storage_path('app/backups/test-exports/' . Backup::first()->file);

        // Backup server: has every row and the go-live opening, and no backups history of its own.
        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);
        Backup::query()->delete();

        $this->assertEquals($before, TankLevels::all()->first()->current);
    }

    public function testClearingIsRefusedOnTheBackupServer()
    {
        config(['backup.is_backup_server' => true]);
        $this->sale('2026-09-10');

        $this->as('admin')->post('/backup', ['up_to' => self::UP_TO])->assertSessionHasErrors('up_to');
        $this->assertSame(1, Sale::count());
    }

    public function testSpotSerialsAreNotReusedAfterUsedTicketsAreCleared()
    {
        Ticket::forceCreate([
            'barcode' => '0000021000000005', 'fuel_type' => 'D', 'spec' => null, 'code' => '21', 'serial' => 5, 'amount_kg' => 10,
            'status' => config('ticket_statuses.supply'), 'supply_date' => '2026-09-10',
        ]);
        $this->as('admin')->post('/ticket-sales', [
            'sale_type' => 'spot', 'fuel_type' => 'D', 'amount_kg' => 3, 'customer_id' => $this->customer->id,
            'price' => 2, 'usage' => 'sale', 'paid_amount' => 6,
        ]); // serial 6

        Ticket::where('serial', 6)->update(['status' => config('ticket_statuses.supply'), 'supply_date' => '2026-09-11']);
        $this->post('/backup', ['up_to' => self::UP_TO]);
        $this->assertSame(0, Ticket::count());

        $this->post('/ticket-sales', [
            'sale_type' => 'spot', 'fuel_type' => 'D', 'amount_kg' => 3, 'customer_id' => $this->customer->id,
            'price' => 2, 'usage' => 'sale', 'paid_amount' => 6,
        ]);
        $this->assertSame('0000021000000007', Ticket::first()->barcode);
    }

    public function testDailyDumpWritesAFileAndPrunesOldOnes()
    {
        $dir = storage_path('app/backups/test-daily');
        mkdir($dir, 0755, true);
        touch($dir . '/old.sql.gz', now()->subDays(10)->getTimestamp());

        $this->artisan('backup:dump')->assertExitCode(0);

        $files = glob($dir . '/*.sql.gz');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('CREATE TABLE', gzdecode(file_get_contents($files[0])));
    }

    public function testTheNightlySafetyNetOnlyDumpsWhenThereIsNoRecentDump()
    {
        $dir = storage_path('app/backups/test-daily');
        mkdir($dir, 0755, true);
        touch($dir . '/recent.sql.gz', now()->subHours(3)->getTimestamp()); // the dump of the confirmation, this morning

        $this->artisan('backup:dump --if-older-than=20 --note=safety-net')->assertExitCode(0);
        $this->assertCount(1, glob($dir . '/*.sql.gz')); // nothing new

        touch($dir . '/recent.sql.gz', now()->subHours(30)->getTimestamp()); // nobody confirmed yesterday
        $this->artisan('backup:dump --if-older-than=20 --note=safety-net')->assertExitCode(0);
        $this->assertCount(2, glob($dir . '/*.sql.gz')); // the old one is still within keep_days, a new one was written
    }

    public function testTheJobAfterAConfirmationWritesTheDump()
    {
        $dir = storage_path('app/backups/test-daily');

        (new \App\Jobs\RunBackupDump('confirmed 2026-10-01'))->handle();

        $files = glob($dir . '/*.sql.gz');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('CREATE TABLE', gzdecode(file_get_contents($files[0])));
    }

    public function testAnExportMadeBeforeTheRenameStillImports()
    {
        // an old export: the column is still called checkout_date in its rows
        $dir = storage_path('app/backups/test-exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/old-export.zip';
        $zip = new \ZipArchive;
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('sales.jsonl', json_encode([
            'id' => '11111111-1111-1111-1111-111111111111', 'sold_at' => '2026-10-01 10:00:00', 'customer_id' => null, 'sale_type' => 'spot',
            'fuel_type' => 'D', 'price' => '2.00', 'amount_kg' => '10.00', 'payment_method' => 'cash', 'total_amount' => '20.00',
            'paid_amount' => '20.00', 'unpaid_amount' => '0.00', 'usage' => 'sale', 'checkout_date' => '2026-10-01', 'user_id' => 1,
        ]) . "
");
        $zip->addFromString('manifest.json', json_encode(['rows' => ['sales' => 1], 'up_to_date' => '2026-10-01']));
        $zip->close();

        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $this->assertSame('2026-10-01', substr((string) Sale::find('11111111-1111-1111-1111-111111111111')->sale_date, 0, 10));
    }

    public function testAnExportWithTheUserOfASupplyStillImports()
    {
        // an export made while supplies still had a user_id
        $dir = storage_path('app/backups/test-exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/old-supply-export.zip';
        $zip = new \ZipArchive;
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('supplements.jsonl', json_encode([
            'id' => '22222222-2222-2222-2222-222222222222', 'fuel_type' => 'D', 'nozzle_id' => $this->nozzle->id, 'device_id' => $this->nozzle->device_id,
            'liters_dispensed' => '12.00', 'amount_kg' => '10.00', 'preset_kg' => '10.00', 'supplied_at' => '2026-10-01 11:00:00',
            'date' => '2026-10-01', 'user_id' => 1,
        ]) . "
");
        $zip->addFromString('manifest.json', json_encode(['rows' => ['supplements' => 1], 'up_to_date' => '2026-10-01']));
        $zip->close();

        config(['backup.is_backup_server' => true]);
        $this->artisan('backup:import', ['file' => $file])->assertExitCode(0);

        $this->assertEquals(10, Supplement::find('22222222-2222-2222-2222-222222222222')->out_amount_kg);
    }
}
