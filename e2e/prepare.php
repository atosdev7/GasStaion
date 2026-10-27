<?php

// Makes the scratch database gasstation_e2e from nothing: schema, the default seed, and the web app's side of the scenario (staff, a device for
// 127.0.0.1, fuel, a tank with stock, customers, tickets published and tickets sold on the web, and a ticket loan made on the web).

require __DIR__ . '/common.php';

use App\Models\Sales\Customer;
use App\Models\Admin\Device;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelType;
use App\Models\Fuel\Tank;
use App\Models\Tickets\Ticket;
use App\Models\Debts\TicketLoan;
use App\Models\Admin\User;
use App\Support\Admin\DeviceHash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// the scratch database is made new every time (the name is the constant of common.php, nothing else is ever dropped)
$pdo = e2e_pdo();
$pdo->exec('DROP DATABASE IF EXISTS `' . E2E_DB . '`');
$pdo->exec('CREATE DATABASE `' . E2E_DB . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
DB::purge('mysql');
if (DB::connection()->getDatabaseName() !== E2E_DB) {
    fwrite(STDERR, "ABORT: wrong database\n");
    exit(2);
}

Artisan::call('migrate', ['--seed' => true, '--force' => true]);

function staff($username, $name, $role)
{
    $salt = bin2hex(random_bytes(8));

    return User::forceCreate([
        'name' => $name, 'username' => $username, 'password' => bcrypt('secret1'), 'role' => $role, 'active' => true,
        'pos_salt' => $salt, 'pos_hash' => DeviceHash::make($salt, 'secret1'),
    ]);
}

staff('cash', 'Cash One', 'cashier');
staff('acct', 'Accountant', 'accountant');

$s = DB::table('settings')->pluck('value', 'key');
Device::forceCreate([
    'name' => 'POS1', 'ip_address' => '127.0.0.1', 'purge_days' => 3,
    'pin_salt' => $s['default_device_pin_salt'], 'pin_hash' => $s['default_device_pin_hash'],
]);

FuelType::forceCreate(['fuel_type' => 'D', 'price' => 7.5, 'density' => 0.83]);
$tank = Tank::forceCreate(['name' => 'T1', 'fuel_type' => 'D', 'capacity_kg' => 5000000]);
FuelInbound::forceCreate([
    'tank_id' => $tank->id, 'fuel_type' => 'D', 'amount_kg' => 100000, 'cost' => 5.0, 'inbound_date' => date('Y-m-d'),
    'user_id' => 1,
]);
\App\Support\Fuel\DefaultCost::set('D', 5.0);      // the default cost of the fuel type (a setting of its own)

$acme = Customer::forceCreate(['name' => 'ACME']);
$beta = Customer::forceCreate(['name' => 'Beta Ltd']);

// Tickets of the printing press: code 06, sign 00000. spec 0 = 5 kg, 1 = 10 kg, 2 = 15 kg.
function press($serial, $spec, $status)
{
    return Ticket::forceCreate([
        'barcode' => sprintf('0000006%09d', $serial), 'fuel_type' => 'D', 'spec' => $spec, 'code' => '06', 'serial' => $serial,
        'status' => $status, 'sale_date' => $status == config('ticket_statuses.sale') ? now() : null,
    ]);
}

$publish = config('ticket_statuses.publish');
$sale = config('ticket_statuses.sale');
foreach (range(1, 10) as $n) {
    press($n, 0, $publish);            // 5 kg, in stock
}
foreach (range(101, 105) as $n) {
    press($n, 1, $publish);            // 10 kg, in stock
}
foreach (range(201, 210) as $n) {
    press($n, 0, $sale);               // 5 kg, sold on the web
}
foreach ([301, 302] as $n) {
    press($n, 2, $sale);               // 15 kg, sold on the web
}
foreach ([401, 402] as $n) {
    press($n, 1, $sale);               // 10 kg, sold on the web
}

// a ticket loan made on the web: ACME took 20 kg
TicketLoan::forceCreate(['customer_id' => $acme->id, 'fuel_type' => 'D', 'amount_kg' => 20, 'settled_amount_kg' => 0, 'user_id' => 1]);

echo "prepared " . E2E_DB . "\n";
