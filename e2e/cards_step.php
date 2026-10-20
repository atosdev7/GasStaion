<?php

// A step of the "cards" scenario, called by the terminal program while the web app keeps running on the scratch database gasstation_e2e:
//   php cards_step.php webtopup <card id> <kg>   tops the card up on the web (as the Top Ups page does: a sale of type card); prints sale=<id>
//   php cards_step.php delete <sale id>          deletes a card sale on the web (CardSales::delete, as the manager does); prints balance=<kg of the card>
// Never touches any other database (common.php).

require __DIR__ . '/common.php';

use App\Models\Card;
use App\Models\Sale;
use App\Models\User;
use App\Support\CardLedger;
use App\Support\CardSales;

$action = $argv[1] ?? '';
$manager = User::forceCreate(['name' => 'Manager', 'username' => 'mgr' . mt_rand(1000, 9999), 'password' => bcrypt('x'), 'role' => 'manager', 'active' => true]);
auth()->setUser($manager);

if ($action === 'webtopup') {
    $card = Card::findOrFail($argv[2]);
    $kg = round((float) $argv[3], 2);
    $sale = new Sale;
    $sale->forceFill([
        'customer_id' => null, 'card_id' => $card->id, 'sale_type' => 'card', 'fuel_type' => $card->fuel_type, 'price' => 7.5, 'amount_kg' => $kg,
        'payment_method' => 'cash', 'total_amount' => round($kg * 7.5, 2), 'paid_amount' => round($kg * 7.5, 2), 'unpaid_amount' => 0, 'receipt' => false,
        'usage' => 'sale', 'sale_date' => date('Y-m-d'), 'synced' => false, 'user_id' => $manager->id,
    ])->save();
    Card::where('id', $card->id)->toBase()->update(['synced' => false, 'updated_at' => now()]);
    echo 'sale=' . $sale->id . "\n";
    exit(0);
}

if ($action === 'delete') {
    $sale = CardSales::delete($argv[2]);
    echo 'balance=' . number_format(CardLedger::balance($sale->card_id), 2, '.', '') . "\n";
    exit(0);
}

fwrite(STDERR, "unknown action\n");
exit(1);
