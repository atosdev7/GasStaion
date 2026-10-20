<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fuel\FuelType;
use App\Models\Sales\Sale;
use App\Models\Admin\Setting;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Support\Sync\Api;
use App\Support\Admin\Audit;
use App\Support\Sync\Pull;
use App\Support\Dashboard\TankLevels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// What the Smart210 pulls from the web app.
class PullController extends Controller
{
    const DEFAULT_LIMIT = 200;
    const MAX_LIMIT = 500;

    // Small config, sent whole every time: the Smart210 replaces its copy, so edits and deletions need no flag.
    public function config(Request $request)
    {
        $device = $request->attributes->get('device');
        $prices = FuelType::all()->keyBy('fuel_type');
        $fuelTypes = [];
        foreach (config('fuel_types') as $code => $name) {
            $fuelTypes[] = [
                'fuel_type' => $code, 'name' => $name,
                'price' => number_format(isset($prices[$code]) ? (float) $prices[$code]->price : 0, 2, '.', ''),
                'density' => isset($prices[$code]) ? $prices[$code]->density : null,
            ];
        }

        // The highest serial the web app already holds for each reserved code in the Smart210 range, so a device whose database
        // started empty (a replaced board) never numbers a new ticket with one that exists.
        $floors = [];
        foreach (config('ticket_reserved_codes') as $code) {
            $floors[$code] = (int) (Ticket::where('code', $code)->where('serial', '>', config('ticket_serial.device_base'))->max('serial')
                ?: config('ticket_serial.device_base'));
        }

        return Api::response([
            'status' => 'ok',
            'server_time' => now()->format('Y-m-d H:i:s'),
            // What the device needs to write a ticket barcode of its own: the business sign (5 digits) and the reserved codes.
            'ticket_sign' => sprintf('%05d', config('ticket_sign')),
            'reserved_codes' => config('ticket_reserved_codes'),
            'ticket_serial_floor' => $floors,
            // Tickets the device mints itself (reserved codes 21-25) are numbered above this; the web app uses the range below.
            'ticket_serial_base' => (int) config('ticket_serial.device_base'),
            'fuel_types' => $fuelTypes,
            'tanks' => TankLevels::all()->map(function ($r) {
                return [
                    'id' => (int) $r->tank->id, 'name' => $r->tank->name, 'fuel_type' => $r->tank->fuel_type,
                    'capacity_kg' => $r->tank->capacity_kg, 'stock_kg' => number_format($r->current, 2, '.', ''),
                ];
            })->values()->all(),
            // After a restore (php artisan backup:restored): the data of the web app is valid up to "since". A terminal that has not handled this
            // restore yet marks what it uploaded after that time as not sent. null when the database was never restored.
            'restore' => Setting::find('restored_at') ? [
                'restored_at' => Setting::find('restored_at')->value,
                'since' => optional(Setting::find('restored_since'))->value,
            ] : null,
            'device' => [
                'id' => (int) $device->id, 'name' => $device->name, 'purge_days' => (int) $device->purge_days,
                'pin_salt' => $device->pin_salt, 'pin_hash' => $device->pin_hash,
                'initial_sync_done' => (bool) $device->initial_sync_done,
            ],
        ]);
    }

    // One page of rows that are not synced yet. Pull, store, ack, repeat until "more" is false.
    public function page(Request $request, $table)
    {
        $limit = (int) $request->query('limit', self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit ?: self::DEFAULT_LIMIT));

        // Taken before the query: the ack only trusts rows last changed before this moment.
        $pulledAt = now()->format('Y-m-d H:i:s');
        $rows = Pull::unsynced($table)->limit($limit + 1)->get();

        return Api::response([
            'status' => 'ok',
            'server_time' => $pulledAt,
            'table' => $table,
            'rows' => $rows->take($limit)->map(function ($m) use ($table) {
                return Pull::row($table, $m);
            })->values()->all(),
            'more' => $rows->count() > $limit,
        ]);
    }

    // Marks rows synced once the Smart210 has stored them. Send back the page's keys and its server_time as pulled_at.
    // A row the web changed after the page was served stays unsynced and is sent again.
    public function ack(Request $request, $table)
    {
        $validator = Validator::make($request->all(), [
            'keys' => 'required|array|max:' . self::MAX_LIMIT,
            'keys.*' => ['required', 'regex:/^[A-Za-z0-9-]+$/'], // ids, barcodes and uuids; never nested arrays
            'pulled_at' => 'required|date_format:Y-m-d H:i:s',
        ]);
        if ($validator->fails()) {
            return Api::error($validator->errors()->first(), 422);
        }
        $data = $validator->validated();

        $acked = Pull::all($table)
            ->whereIn(Pull::key($table), $data['keys'])
            ->where('synced', false)
            ->where(function ($q) use ($data) {
                $q->whereNull('updated_at')->orWhere('updated_at', '<', $data['pulled_at']);
            })
            ->toBase()->update(['synced' => true]);

        return Api::response(['status' => 'ok', 'acked' => $acked]);
    }

    // Which of the records the terminal still holds has the accountant confirmed on the web app (daily confirmation)? The terminal sends the ids
    // of the sales and supplements it has uploaded and still keeps, and removes the ones named in the answer: a confirmed record is final.
    public function confirmed(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sales' => 'array|max:' . self::MAX_LIMIT,
            'sales.*' => ['required', 'regex:/^[A-Za-z0-9-]+$/'],
            'supplements' => 'array|max:' . self::MAX_LIMIT,
            'supplements.*' => ['required', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);
        if ($validator->fails()) {
            return Api::error($validator->errors()->first(), 422);
        }
        $data = $validator->validated();

        $sales = empty($data['sales']) ? [] : Sale::withTrashed()->whereIn('id', $data['sales'])->whereNotNull('confirmed_at')->pluck('id')->all();
        $supplements = empty($data['supplements']) ? [] : Supplement::withTrashed()->whereIn('id', $data['supplements'])->whereNotNull('confirmed_at')->pluck('id')->all();

        return Api::response(['status' => 'ok', 'sales' => $sales, 'supplements' => $supplements]);
    }

    // A Smart210 whose database is empty (new, or its board was replaced) calls this before its first sync: every pull
    // table goes back to unsynced, so it downloads everything again, and the device counts as not set up until it has.
    public function begin(Request $request)
    {
        $device = $request->attributes->get('device');
        Pull::resetAll();
        $device->initial_sync_done = false;
        $device->save();

        Audit::log('sync_begin', '#' . $device->id . ' ' . $device->name, 'device');

        return Api::response(['status' => 'ok']);
    }

    // The Smart210 has pulled everything once; the web app shows the device as set up.
    public function initialDone(Request $request)
    {
        $device = $request->attributes->get('device');
        $device->initial_sync_done = true;
        $device->save();

        return Api::response(['status' => 'ok']);
    }
}
