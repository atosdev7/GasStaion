<?php

namespace Tests\Feature;

use App\Models\Sales\Customer;
use App\Models\Admin\Setting;
use App\Models\Tickets\Ticket;
use App\Support\Tickets\Barcode;
use App\Support\Tickets\SerialCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Runs against the gasstation_test database (see phpunit.xml).
 */
class SyncFoundationsTest extends TestCase
{
    use RefreshDatabase;

    private function syncedValue($customer)
    {
        return (int) Customer::withTrashed()->whereKey($customer->id)->value('synced');
    }

    public function testASavedFromDeviceChangeIsNotFlaggedForPulling()
    {
        $customer = Customer::forceCreate(['name' => 'Old']);
        Customer::whereKey($customer->id)->toBase()->update(['synced' => true]);

        $customer->saveFromDevice(['name' => 'From device', 'synced' => true]);

        $this->assertSame(1, $this->syncedValue($customer));
        $this->assertSame('From device', Customer::find($customer->id)->name);
    }

    public function testAnOrdinaryChangeStillFlagsTheRowAndTheFlagIsRestoredAfterwards()
    {
        $customer = Customer::forceCreate(['name' => 'Old']);
        $customer->saveFromDevice(['synced' => true]);
        $this->assertFalse($customer->isSavedFromDevice()); // only for the duration of that save

        $customer->forceFill(['name' => 'Web edit'])->save();

        $this->assertSame(0, $this->syncedValue($customer));
    }

    public function testWebSerialsIgnoreTicketsMintedByADevice()
    {
        $base = config('ticket_serial.device_base');
        foreach ([$base + 1, $base + 7] as $serial) {
            Ticket::forceCreate([
                'barcode' => Barcode::makeReserved('21', $serial), 'fuel_type' => 'D', 'spec' => null, 'code' => '21',
                'serial' => $serial, 'amount_kg' => 5, 'status' => config('ticket_statuses.sale'),
            ]);
        }

        $this->assertSame(1, SerialCounter::next('21')); // not 500000008
        $this->assertSame(2, SerialCounter::next('21'));
    }

    public function testTheCounterRowIsMadeAtTheFirstUseAndHeldFromThenOn()
    {
        $this->assertNull(Setting::find('serial_high_77'));

        $this->assertSame(1, SerialCounter::next('77'));

        $this->assertSame('1', Setting::find('serial_high_77')->value);
        $this->assertSame(2, SerialCounter::next('77'));
        $this->assertSame(1, Setting::where('key', 'serial_high_77')->count());
    }

    public function testWebSerialsStillCountTheWebsOwnTickets()
    {
        Ticket::forceCreate([
            'barcode' => Barcode::makeReserved('25', 41), 'fuel_type' => 'D', 'spec' => null, 'code' => '25',
            'serial' => 41, 'amount_kg' => 5, 'status' => config('ticket_statuses.sale'),
        ]);

        $this->assertSame(42, SerialCounter::next('25'));
    }
}
