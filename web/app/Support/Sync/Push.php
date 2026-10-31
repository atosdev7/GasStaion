<?php

namespace App\Support\Sync;

use App\Http\Controllers\Sales\SaleController;
use App\Models\Admin\Device;
use App\Models\Admin\SyncError;
use App\Models\Cards\Card;
use App\Models\Debts\TicketLoan;
use App\Models\Debts\TicketLoanSettlement;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;
use App\Models\Sales\Customer;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Supplements\Remainder;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Support\Tickets\Barcode;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

// Rows the Smart210 pushes. Each is validated and stored by its key, so sending the same row again changes nothing.
// A device is the source of truth for what happened at the pump, so a valid row is stored as it is; only rows that are
// malformed or point at things that do not exist are rejected. Push order: nozzles, sales, supplements, remainders, ticket_loans,
// ticket_loan_settlements, tickets.
class Push
{
    const TABLES = ['nozzles', 'cards', 'sales', 'supplements', 'remainders', 'ticket_loans', 'ticket_loan_settlements', 'tickets'];

    const DATETIME = 'date_format:Y-m-d H:i:s';

    public static function pattern()
    {
        return implode('|', self::TABLES);
    }

    // How a row is named in replies and in sync_errors: tickets by barcode, nozzles by number, everything else by id.
    public static function keyOf($table, array $row)
    {
        $field = $table === 'tickets' ? 'barcode' : ($table === 'nozzles' ? 'number' : 'id');

        return isset($row[$field]) && is_scalar($row[$field]) ? (string) $row[$field] : null;
    }

    // Stores one row in its own transaction; throws RejectedRow when it must not be stored.
    public static function apply($table, Device $device, array $row)
    {
        // A request the terminal gave up on (a timeout, a power cut) is still worked on by the web server while the terminal sends the same row again,
        // so two requests can store one row at the same moment. The second one gets a duplicate key (or a deadlock) from the database; by then the first
        // has stored the row, so running this one again finds it and stores it as the same row (what a repeated push always does).
        for ($attempt = 1; ; $attempt++) {
            try {
                DB::transaction(function () use ($table, $device, $row) {
                    call_user_func([self::class, $table], $device, $row);
                });

                return;
            } catch (QueryException $e) {
                $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
                if ($attempt >= 4 || !in_array($driverCode, [1062, 1213, 1205], true)) {
                    throw $e;
                }
                usleep(150000 * $attempt);
            }
        }
    }

    // The nozzles are set up on the Smart210, which is the source of truth for them: a row creates, changes or deletes the
    // calling device's nozzle with this number. The fuel type is the tank's. Supplements keep pointing at a deleted nozzle.
    private static function nozzles(Device $device, array $row)
    {
        $d = self::validate($row, [
            'number' => 'required|integer|min:1|max:255',
            'tank_id' => ['required', 'integer', Rule::exists('tanks', 'id')],
            'board_address' => 'nullable|integer|min:0|max:255',
            'name' => 'nullable|string|max:50', // the name given on the Smart210; an older terminal sends none
            'deleted' => 'required|boolean',
        ]);

        $nozzle = Nozzle::withTrashed()->where('device_id', $device->id)->where('number', $d['number'])->first();

        if ($d['deleted']) {
            if ($nozzle && !$nozzle->trashed()) {
                $nozzle->delete();
            }

            return;
        }

        if (!$nozzle) {
            $nozzle = new Nozzle;
            $nozzle->device_id = $device->id;
            $nozzle->number = $d['number'];
        }
        $nozzle->tank_id = $d['tank_id'];
        $nozzle->fuel_type = Tank::findOrFail($d['tank_id'])->fuel_type;
        $nozzle->board_address = $d['board_address'];
        if (array_key_exists('name', $row)) {
            $nozzle->name = isset($d['name']) && trim($d['name']) !== '' ? trim($d['name']) : null;
        }
        $nozzle->deleted_at = null;
        $nozzle->save();
    }

    // The cards. The cashier registers a card on the Smart210, which creates it here (id, card id, holder, fuel type, the salted PIN hash) and reports the
    // wrong PINs it counted and a lock after 5 of them. The web is the source of truth for everything else: unlocking, blocking a lost card and
    // resetting a PIN are done on the web and go down with the pull. So an existing card takes from the terminal only its wrong tries and a lock of
    // an active card, and only while the web has no change waiting to be pulled (a row not synced): otherwise the terminal's copy is older.
    private static function cards(Device $device, array $row)
    {
        $d = self::validate($row, [
            'id' => 'required|uuid',
            'uid' => 'required|string|max:32',
            'holder_name' => 'required|string|max:100',
            'holder_phone' => 'nullable|string|max:30', // optional: asked at the registration when the terminal has the field
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'pin_salt' => 'required|string|max:32',
            'pin_hash' => 'required|string|size:64',
            'failed_attempts' => 'nullable|integer|min:0|max:255',
            'status' => ['nullable', Rule::in(Card::STATUSES)],
        ]);

        $card = Card::withTrashed()->find($d['id']);
        if (!$card) {
            if (Card::withTrashed()->where('uid', $d['uid'])->exists()) {
                throw new RejectedRow('another card has this card id');
            }
            $card = new Card;
            $card->id = $d['id'];
            self::store($card, [
                'uid' => $d['uid'], 'holder_name' => $d['holder_name'], 'holder_phone' => isset($d['holder_phone']) ? $d['holder_phone'] : null, 'fuel_type' => $d['fuel_type'], 'pin_salt' => $d['pin_salt'], 'pin_hash' => $d['pin_hash'],
                'failed_attempts' => isset($d['failed_attempts']) ? $d['failed_attempts'] : 0,
                // a new card is active; a lock the terminal already counted is kept
                'status' => (isset($d['status']) && $d['status'] === 'locked') ? 'locked' : 'active',
                'device_id' => $device->id, 'synced' => true,
            ]);

            return;
        }

        if (!$card->synced) {
            return; // the web changed it and the terminal has not pulled that yet: its copy is the older one
        }
        $changes = [];
        if (isset($d['failed_attempts']) && $card->status === 'active') {
            $changes['failed_attempts'] = $d['failed_attempts'];
        }
        if (isset($d['status']) && $d['status'] === 'locked' && $card->status === 'active') {
            $changes['status'] = 'locked';
        }
        if ($changes) {
            self::store($card, $changes + ['synced' => true]);
        }
    }

    private static function sales(Device $device, array $row)
    {
        $d = self::validate($row, [
            'id' => 'required|uuid',
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            // a card sale (a top-up) names its card, which exists (the cards go first in a push)
            'card_id' => ['nullable', 'uuid', Rule::exists('cards', 'id')],
            'sale_type' => ['required', Rule::in(SaleController::SALE_TYPES)],
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'price' => 'required|numeric|min:0|max:9999999',
            'amount_kg' => 'required|numeric|min:0|max:99999999',
            'payment_method' => ['required', Rule::in(SaleController::PAYMENT_METHODS)],
            'total_amount' => 'required|numeric|min:0|max:99999999',
            'paid_amount' => 'required|numeric|min:0|max:99999999',
            'unpaid_amount' => 'required|numeric|min:0|max:99999999',
            'receipt' => 'nullable|string|max:100',
            'usage' => ['required', Rule::in(SaleController::USAGES)],
            'comment' => 'nullable|string|max:255',
            'created_at' => ['required', self::DATETIME], // the moment the sale was made at the terminal
            'sale_date' => 'required|date_format:Y-m-d',
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            // the items of a ticket sale as entered at the terminal; they go with the sale and replace the ones kept before
            'items' => 'nullable|array|max:200',
            'items.*.spec' => 'required|integer|min:0|max:255',
            'items.*.start_serial' => 'required|integer|min:1',
            'items.*.end_serial' => 'required|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
            'items.*.amount_kg' => 'required|numeric|min:0|max:99999999',
        ]);
        $items = isset($d['items']) ? $d['items'] : [];
        unset($d['items']);

        // The receipt is a yes/no on the web; the device sends nothing, or some text, for it.
        $d['receipt'] = !empty($d['receipt']);

        // (only a top-up of a free payment card may be for nothing)
        $freeCard = $d['sale_type'] === 'card' && !empty($d['card_id']) && (bool) Card::where('id', $d['card_id'])->value('free_payment');
        if ($d['usage'] === 'sale' && $d['price'] <= 0 && !$freeCard) {
            throw new RejectedRow('a sale needs a price above 0');
        }
        if (abs($d['paid_amount'] + $d['unpaid_amount'] - $d['total_amount']) > 0.01) {
            throw new RejectedRow('paid and unpaid amounts do not add up to the total');
        }
        // The amount is the price times the weight. The terminal can also type the amount and work the weight out from it, which is rounded to 0.01 kg, so the two may
        // differ by one cent and half a hundredth of a kg of fuel; more than that is a wrong row. Own use and other use have no amount.
        if ($d['usage'] === 'sale' || $d['total_amount'] > 0) {
            $expected = round($d['price'] * $d['amount_kg'], 2);
            if (abs($d['total_amount'] - $expected) > 0.01 + 0.005 * $d['price']) {
                throw new RejectedRow('the total amount is not the price times the weight (' . number_format($expected, 2, '.', '') . ')');
            }
        }
        // The items of a ticket sale are the tickets that were sold: their kg make the weight of the sale.
        if ($items && abs(array_sum(array_column($items, 'amount_kg')) - $d['amount_kg']) > 0.01) {
            throw new RejectedRow('the weights of the tickets do not add up to the weight of the sale');
        }
        // Debts are created on the web; a sale made at the pump is always paid in full.
        if ($d['unpaid_amount'] > 0.01) {
            throw new RejectedRow('a Smart210 sale cannot leave an unpaid amount');
        }
        if ($d['sale_type'] === 'card') {
            // a top-up of a card: of its fuel type, cash only, a sale of fuel (no customer, no debt)
            if (empty($d['card_id'])) {
                throw new RejectedRow('a card sale needs its card');
            }
            if (Card::find($d['card_id'])->fuel_type !== $d['fuel_type']) {
                throw new RejectedRow('the fuel type is not the fuel type of the card');
            }
            if ($d['payment_method'] !== 'cash' || $d['usage'] !== 'sale') {
                throw new RejectedRow('a card is paid in cash and a top-up is a sale');
            }
            $d['customer_id'] = null;
        } elseif (!empty($d['card_id'])) {
            throw new RejectedRow('only a card sale has a card');
        }

        // Own use and other use are always the customer "Individual", as on the web forms, whatever customer the terminal sent.
        if (in_array($d['usage'], ['self', 'other'], true)) {
            $d['customer_id'] = Customer::individual()->id;
        }

        $sale = self::find(Sale::class, $d['id']);
        if ($sale->confirmed_at) {
            self::refuseEditOfConfirmed($sale, $d, 'sale_date');

            return; // confirmed by the accountant: final, so a repeated push changes nothing
        }
        if (!$sale->exists) {
            // The cost is the web's to fill, never the device's: the default cost of the fuel type when the row arrives (it may come days after the sale). The
            // accountant can change the cost of a sale (and of a supply) before confirming it, so a changed default costs nothing but that look.
            $sale->cost = \App\Support\Fuel\DefaultCost::get($d['fuel_type']);      // the default cost of the fuel type; the accountant can change it on the sale before confirming
        }

        self::store($sale, $d, ['synced' => true]);

        SaleItem::where('sale_id', $d['id'])->delete();
        foreach ($items as $item) {
            $row = new SaleItem;
            $row->forceFill($item + ['sale_id' => $d['id']])->save();
        }
    }

    private static function supplements(Device $device, array $row)
    {
        // an older terminal calls the day of a supply `date`
        if (!array_key_exists('supply_date', $row) && array_key_exists('date', $row)) {
            $row['supply_date'] = $row['date'];
        }
        unset($row['date']);
        // an older terminal calls the weight and the litres that came out `amount_kg` and `liters_dispensed`
        foreach (['amount_kg' => 'out_amount_kg', 'liters_dispensed' => 'out_amount_lt'] as $old => $new) {
            if (!array_key_exists($new, $row) && array_key_exists($old, $row)) {
                $row[$new] = $row[$old];
            }
            unset($row[$old]);
        }

        $d = self::validate($row, [
            'id' => 'required|uuid',
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'nozzle_number' => 'required|integer|min:1|max:255',
            'out_amount_lt' => 'required|numeric|min:0.01|max:999999',
            'density' => 'nullable|numeric|min:0|max:9999.9999', // kg per litre; an older terminal sends none
            'out_amount_kg' => 'required|numeric|min:0|max:99999999',
            'preset_kg' => 'required|numeric|min:0|max:99999999',
            'manual_reading' => 'nullable|boolean', // gone from the web app: an older terminal still sends it, a reading typed in by hand counts as the manual mode
            'mode' => ['nullable', Rule::in(Supplement::MODES)], // the nozzle's mode at the fill; an older terminal sends none: auto
            'card_id' => ['nullable', 'uuid', Rule::exists('cards', 'id')], // the card a supply was made with (it has no tickets)
            'supplied_at' => ['required', self::DATETIME],
            'comment' => 'nullable|string|max:255',
            'supply_date' => 'nullable|date_format:Y-m-d',
        ]);

        // No tolerance: a fill may fall short of its tickets (what is left is a remainder), never go past them. (The firmware of the board makes it stop at the preset.)
        if (round($d['out_amount_kg'] - $d['preset_kg'], 2) > 0) {
            throw new RejectedRow('more was dispensed than the tickets cover');
        }

        // The device names its nozzle by number; the nozzles go first in a push, so it exists (deleted ones count too).
        $nozzle = Nozzle::withTrashed()->where('device_id', $device->id)->where('number', $d['nozzle_number'])->first();
        if (!$nozzle) {
            throw new RejectedRow('unknown nozzle ' . $d['nozzle_number'] . ' for this device');
        }
        $d['nozzle_id'] = $nozzle->id;
        unset($d['nozzle_number']);
        if (!empty($d['card_id']) && Card::find($d['card_id'])->fuel_type !== $d['fuel_type']) {
            throw new RejectedRow('the fuel type is not the fuel type of the card');
        }
        $d['mode'] = isset($d['mode']) ? $d['mode'] : (!empty($d['manual_reading']) ? 'manual' : 'auto');
        unset($d['manual_reading']);
        if (empty($d['density'])) {
            $d['density'] = round($d['out_amount_kg'] / $d['out_amount_lt'], 4); // what the litres and the kg give
        }

        $supplement = self::find(Supplement::class, $d['id']);
        if ($supplement->confirmed_at) {
            self::refuseEditOfConfirmed($supplement, $d, 'supply_date');

            return; // confirmed by the accountant: final, so a repeated push changes nothing
        }

        // Fuel that was dispensed is a fact, as with a ticket: a fill made with a card is stored even when the card holds less than was poured
        // (the terminal checks the balance before it starts, but a top-up can be deleted or the clock can be wrong). The balance then goes below
        // zero, the card is listed as a warning on the Cards page, and the staff see the case among the sync problems.
        $overdrawn = null;
        if (!empty($d['card_id']) && !$supplement->exists) {
            $held = Card::find($d['card_id'])->balance();
            if (round($held - (float) $d['out_amount_kg'], 2) < 0) {
                $overdrawn = 'conflict: the card held only ' . number_format($held, 2, '.', '') . ' kg, less than the ' . number_format((float) $d['out_amount_kg'], 2, '.', '') . ' kg supplied: stored, the balance is below zero';
            }
        }

        // The cost of a new supply is the default cost of the fuel type when the tank has fuel at that cost (the terminal cannot choose); the accountant changes it before
        // confirming. A supply that is uploaded again keeps its cost.
        $extra = ['device_id' => $device->id, 'synced' => true];
        if (!$supplement->exists) {
            $extra['cost'] = \App\Support\Fuel\CostChoices::costForNewSupply($nozzle->tank_id);
        }

        // Whichever device is calling is the one that dispensed it.
        self::store($supplement, $d, $extra);

        if ($overdrawn) {
            SyncError::record($device->id, 'supplements', $d['id'], json_encode($d), $overdrawn);
        }
    }

    private static function remainders(Device $device, array $row)
    {
        // an older terminal calls the moment `remainder_at` and the day `date`
        foreach (['remainder_at' => 'created_at', 'date' => 'remainder_date'] as $old => $new) {
            if (!array_key_exists($new, $row) && array_key_exists($old, $row)) {
                $row[$new] = $row[$old];
            }
            unset($row[$old]);
        }

        $d = self::validate($row, [
            'id' => 'required|uuid',
            'supplement_id' => ['required', 'uuid', Rule::exists('supplements', 'id')],
            'ticket_barcode' => 'required|string|size:16',
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'amount_kg' => 'required|numeric|min:0.01|max:99999999',
            'created_at' => ['required', self::DATETIME], // the moment of the remainder at the terminal
            'remainder_date' => 'nullable|date_format:Y-m-d',
            'comment' => 'nullable|string|max:255',
        ]);

        self::store(self::find(Remainder::class, $d['id']), $d);
    }

    private static function ticket_loans(Device $device, array $row)
    {
        $d = self::validate($row, [
            'id' => 'required|uuid',
            'loaned_at' => ['required', self::DATETIME],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'amount_kg' => 'required|numeric|min:0.01|max:99999999',
            'sale_id' => 'nullable|uuid',
            'comment' => 'nullable|string|max:255',
            'date' => 'nullable|date_format:Y-m-d',
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        // What has been settled is the ledger's total, which the web owns (it can be settled there too), so the device's
        // own figure is not taken.
        $loan = self::find(TicketLoan::class, $d['id']);
        if (!$loan->exists) {
            $loan->settled_amount_kg = TicketLoanSettlement::where('loan_id', $d['id'])->sum('amount_kg');
        }

        self::store($loan, $d, ['synced' => true]);
    }

    private static function ticket_loan_settlements(Device $device, array $row)
    {
        $d = self::validate($row, [
            'id' => 'required|uuid',
            'loan_id' => ['required', 'uuid', Rule::exists('ticket_loans', 'id')],
            'ticket_barcode' => 'required|string|size:16',
            'amount_kg' => 'required|numeric|min:0.01|max:99999999',
            'settled_at' => ['required', self::DATETIME],
            'comment' => 'nullable|string|max:255',
            'date' => 'nullable|date_format:Y-m-d',
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $settlement = self::find(TicketLoanSettlement::class, $d['id']);
        if ($settlement->confirmed_at) {
            self::refuseEditOfConfirmed($settlement, $d, 'date');

            return; // confirmed by the accountant: final, so a repeated push changes nothing
        }

        self::store($settlement, $d, ['synced' => true]);

        // Recomputed from the ledger, as the web does. An ordinary save: the new total goes back to the device with its
        // next pull, together with anything settled on the web meanwhile.
        $loan = self::find(TicketLoan::class, $d['loan_id']);
        $loan->settled_amount_kg = TicketLoanSettlement::where('loan_id', $loan->id)->sum('amount_kg');
        $loan->save();
    }

    // A device reports a ticket as sold or supplied, nothing else. It may create tickets only in its own reserved range
    // (direct, self, other, ticket loan, remainder); press tickets exist already. A ticket only moves forward, and a
    // push that disagrees with what the web already knows is not applied but logged, so staff can see it and the
    // device does not retry forever. Fuel that was dispensed is a fact: it wins over a restriction made meanwhile.
    private static function tickets(Device $device, array $row)
    {
        $s = config('ticket_statuses');
        $d = self::validate($row, [
            'barcode' => 'required|string|size:16',
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'amount_kg' => 'nullable|numeric|min:0.01|max:99999999',
            'status' => ['required', 'integer', Rule::in([$s['sale'], $s['supply']])],
            'sale_id' => 'nullable|uuid',
            'sale_date' => ['nullable', self::DATETIME],
            'supply_date' => ['nullable', self::DATETIME],
            'supplement_id' => 'nullable|uuid',
            'card_id' => ['nullable', 'uuid', Rule::exists('cards', 'id')], // a ticket made for a card (the card paid for it): it has no sale
            'print_count' => 'nullable|integer|min:0|max:1000000', // how many times the terminal printed it, and when last
            'last_printed_at' => ['nullable', self::DATETIME],
        ]);
        $status = (int) $d['status'];

        if ($status === $s['sale'] && empty($d['sale_date'])) {
            throw new RejectedRow('a sold ticket needs its sale date');
        }
        // No supplement is needed: a ticket surrendered to settle a ticket loan is supplied too, but not at a nozzle.
        if ($status === $s['supply'] && empty($d['supply_date'])) {
            throw new RejectedRow('a supplied ticket needs its supply date');
        }

        $ticket = Ticket::find($d['barcode']);
        if (!$ticket && \App\Support\Tickets\LegacyTickets::ensure($d['barcode'])) {
            $ticket = Ticket::find($d['barcode']);        // sold before go-live: its row is made now, and the report goes on like for any sold ticket
        }
        if (!$ticket) {
            self::createDeviceTicket($d, $status);
            // The card paid with kg it did not have (a top-up deleted, a clock that was wrong): the ticket is stored, as fuel that was poured is a fact, and the staff see
            // the case among the sync problems; the card is listed below zero on the Cards page.
            if (!empty($d['card_id']) && \App\Support\Cards\CardLedger::balance($d['card_id']) < -0.004) {
                SyncError::record($device->id, 'tickets', $d['barcode'], json_encode($d), 'conflict: the card held less than the ticket (' . number_format(\App\Support\Cards\CardLedger::balance($d['card_id']), 2, '.', '') . ' kg now): stored');
            }

            return;
        }

        if ($status === $s['sale'] && empty($d['sale_id']) && Barcode::posType($ticket->code) !== 'remainder' && empty($ticket->card_id)) {
            throw new RejectedRow('a sold ticket needs its sale');
        }

        $current = (int) $ticket->status;
        $names = array_flip($s);
        $sale = ['sale_id' => $d['sale_id'] ?? null, 'sale_date' => $d['sale_date'] ?? null];
        $attributes = [];

        if ($status === $s['sale']) {
            if ($current === $s['publish']) {
                self::requireRecords($d);
                $attributes = ['status' => $s['sale']] + $sale;
            } elseif ($current !== $s['sale']) {
                self::conflict($device, $d, 'the ticket is already ' . $names[$current] . ' on the server, the sale was not applied');
            }
        } elseif ($current === $s['supply']) {
            // The same ticket used twice: at a nozzle under two supplements, or once at a nozzle and once for a ticket loan (that use has no supplement). Whichever
            // came first stays; the staff see the case among the sync problems. (The same use sent again is quiet: the supplements are equal, or both are empty.)
            $stored = (string) $ticket->supplement_id;
            $pushed = (string) ($d['supplement_id'] ?? '');
            if ($stored !== $pushed) {
                self::conflict($device, $d, $stored === '' ? 'the ticket was already used for a ticket loan, kept as it was'
                    : ($pushed === '' ? 'the ticket was already supplied at a nozzle, kept as it was' : 'the ticket was already supplied under another supplement, kept as it was'));
            }
        } else {
            if ($current === $s['restrict']) {
                self::conflict($device, $d, 'the ticket was restricted on the server but fuel was dispensed, marked supplied');
            }
            self::requireRecords($d);
            $attributes = ['status' => $s['supply'], 'supply_date' => $d['supply_date'], 'supplement_id' => $d['supplement_id'] ?? null];
            if ($current === $s['publish']) {
                $attributes += $sale; // sold and supplied between two syncs
            }
        }

        // the prints are counted by the terminal: the larger count wins (an older upload cannot take a print away)
        if ((int) ($d['print_count'] ?? 0) > (int) $ticket->print_count) {
            $attributes += ['print_count' => (int) $d['print_count'], 'last_printed_at' => $d['last_printed_at'] ?? null];
        }

        if ($attributes) {
            $ticket->saveFromDevice($attributes + ['synced' => true]);
        }
    }

    // The sale (or the supplement) a ticket names must be on the server: when the sale was refused (its rows are checked one by one) or has not
    // arrived, its tickets are refused too and wait, instead of being stored as sold under a sale that does not exist (the stock gone, no money
    // recorded). The ticket is sent again with every upload until its sale is accepted. Deleted records count: they are on the server.
    private static function requireRecords(array $d)
    {
        if (!empty($d['sale_id']) && !Sale::withTrashed()->whereKey($d['sale_id'])->exists()) {
            throw new RejectedRow('its sale is not on the server (it was refused or has not arrived): the ticket waits for it');
        }
        if (!empty($d['supplement_id']) && !Supplement::withTrashed()->whereKey($d['supplement_id'])->exists()) {
            throw new RejectedRow('its supplement is not on the server (it was refused or has not arrived): the ticket waits for it');
        }
    }

    private static function createDeviceTicket(array $d, $status)
    {
        $parsed = Barcode::parse($d['barcode']);

        if ($parsed['sign'] !== sprintf('%05d', config('ticket_sign'))) {
            throw new RejectedRow('the barcode does not carry this business\'s sign');
        }
        if (!Barcode::isReservedCode($parsed['code'])) {
            throw new RejectedRow('unknown ticket: a Smart210 can only create tickets with a reserved code');
        }
        if ($parsed['serial'] <= config('ticket_serial.device_base')) {
            throw new RejectedRow('the serial is below the range reserved for Smart210 tickets');
        }
        if (empty($d['amount_kg'])) {
            throw new RejectedRow('a new ticket needs its amount');
        }
        // the code says the fuel type and the kind of the ticket: the fuel type must be the ticket's own
        if (Barcode::posFuel($parsed['code']) !== $d['fuel_type']) {
            throw new RejectedRow('the code of the ticket is of another fuel type than the ticket');
        }
        $kind = Barcode::posType($parsed['code']);
        if (!empty($d['card_id'])) {
            // a ticket made for a card: paid for with the card's kg, no sale behind it; of the card's fuel type, with the card code
            if ($kind !== 'card') {
                throw new RejectedRow('a ticket made for a card has a card code');
            }
            if (Card::find($d['card_id'])->fuel_type !== $d['fuel_type']) {
                throw new RejectedRow('the fuel type is not the fuel type of the card');
            }
        } elseif ($kind === 'card') {
            throw new RejectedRow('a ticket with a card code needs its card');
        } elseif ($status === config('ticket_statuses.sale') && empty($d['sale_id']) && $kind !== 'remainder') {
            throw new RejectedRow('a sold ticket needs its sale');
        }
        self::requireRecords($d);

        $ticket = new Ticket;
        $ticket->barcode = $d['barcode'];
        $ticket->saveFromDevice([
            'fuel_type' => $d['fuel_type'], 'spec' => null, 'code' => $parsed['code'], 'serial' => $parsed['serial'],
            'amount_kg' => $d['amount_kg'], 'status' => $status,
            'sale_id' => $d['sale_id'] ?? null, 'sale_date' => $d['sale_date'] ?? null,
            'supply_date' => $d['supply_date'] ?? null, 'supplement_id' => $d['supplement_id'] ?? null,
            'card_id' => $d['card_id'] ?? null,
            'print_count' => (int) ($d['print_count'] ?? 0), 'last_printed_at' => $d['last_printed_at'] ?? null,
            'synced' => true,
        ]);
    }

    private static function conflict(Device $device, array $d, $reason)
    {
        SyncError::record($device->id, 'tickets', $d['barcode'], json_encode($d), 'conflict: ' . $reason);
    }

    // The cashier may change the comment and the date of a record after it was uploaded. A record the accountant has confirmed is
    // final: such a change is refused with the reason, so the cashier sees it. Any other difference is ignored, as before (the device
    // cannot make one, and it stops retrying).
    private static function refuseEditOfConfirmed($model, array $d, $dateColumn)
    {
        $comment = isset($d['comment']) ? trim((string) $d['comment']) : '';
        $date = isset($d[$dateColumn]) ? (string) $d[$dateColumn] : '';

        $storedComment = trim((string) $model->comment);
        $storedDate = $model->{$dateColumn} ? substr((string) $model->{$dateColumn}, 0, 10) : '';

        if ($comment !== $storedComment || $date !== $storedDate) {
            throw new RejectedRow('confirmed by the accountant: ask the manager to take the confirmation back, then change it and upload again');
        }
    }

    private static function validate(array $row, array $rules)
    {
        $validator = Validator::make($row, $rules);
        if ($validator->fails()) {
            throw new RejectedRow($validator->errors()->first());
        }

        return $validator->validated();
    }

    // The existing row, or a new one with this key, deleted rows included.
    private static function find($class, $key)
    {
        $query = in_array(SoftDeletes::class, class_uses_recursive($class), true) ? $class::withTrashed() : $class::query();
        $model = $query->find($key);
        if (!$model) {
            $model = new $class;
            $model->{$model->getKeyName()} = $key;
        }

        return $model;
    }

    private static function store($model, array $data, array $extra = [])
    {
        $attributes = $extra + array_diff_key($data, [$model->getKeyName() => true]);

        if (method_exists($model, 'saveFromDevice')) {
            $model->saveFromDevice($attributes);
        } else {
            $model->forceFill($attributes)->save();
        }
    }
}
