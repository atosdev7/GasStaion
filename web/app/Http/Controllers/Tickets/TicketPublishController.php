<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketPublish;
use App\Support\Admin\Audit;
use App\Support\Tickets\Barcode;
use App\Support\Tickets\TicketStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Turn blank ticket stock (see TicketInboundController) into numbered tickets. Sign and code are
// both fixed, non-admin-editable constants (see App\Support\Tickets\Barcode) — the admin only picks the
// fuel type + spec and enters the serial range.
//
// A batch can be looked at (its tickets) but never changed or deleted: publishing cannot be reversed (the form asks to confirm it), and a ticket
// that was published wrongly is restricted instead. Blank tickets spoiled in printing are taken out of the stock with a negative ticket inbound.
// Confirmed with the daily confirmation (see ConfirmationController).
class TicketPublishController extends Controller
{
    const MAX_QUANTITY = 50000;

    // Every filter sits in the head of its column: the day of the batch, the fuel type and the spec.
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'fuel_type' => ['nullable', Rule::in(array_keys(config('fuel_types')))],
            'spec' => ['nullable', Rule::in(array_keys(config('ticket_specs')))],
        ]);

        $query = TicketPublish::orderByDesc('id');
        if (!empty($filters['date'])) {
            $query->where('date', $filters['date']);
        }
        if (!empty($filters['fuel_type'])) {
            $query->where('fuel_type', $filters['fuel_type']);
        }
        if (isset($filters['spec']) && $filters['spec'] !== '') {
            $query->where('spec', (int) $filters['spec']);
        }

        return view('ticket_publish.index', [
            'batches' => $query->paginate(50)->appends($request->query()),
            'filters' => $filters,
            'fuels' => config('fuel_types'),
            'specs' => config('ticket_specs'),
        ]);
    }

    public function create()
    {
        $stock = TicketStock::summary()->filter(function ($r) {
            return $r->available > 0;
        });

        // Suggested start serial per stock item: the next free one for that fuel type's code at
        // this spec. Purely a convenience default — the admin can change it, and the real check
        // against overlapping tickets happens on submit regardless.
        $nextSerials = $stock->mapWithKeys(function ($row) {
            $code = Barcode::codeFor($row->fuel_type, $row->spec);
            // From the publishes, not the tickets: backup removes used tickets, publishes stay. A deleted batch is gone and counts for nothing.
            $next = $code ? (int) TicketPublish::where('code', $code)->max('end_serial') + 1 : 1;

            return [$row->fuel_type . ':' . $row->spec => $next];
        });

        return view('ticket_publish.create', [
            'stock' => $stock,
            'fuels' => config('fuel_types'),
            'specs' => config('ticket_specs'),
            'nextSerials' => $nextSerials,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'stock_item' => ['required', 'regex:/^.+:\d+$/'],
            'start_serial' => 'required|integer|min:1|max:' . Barcode::MAX_SERIAL,
            'end_serial' => 'required|integer|min:1|max:' . Barcode::MAX_SERIAL,
            'date' => 'nullable|date', // the day it is published; today when it is not sent
        ]);

        list($fuelType, $spec) = explode(':', $data['stock_item']);

        if (!array_key_exists($fuelType, config('fuel_types')) || !array_key_exists((int) $spec, config('ticket_specs'))) {
            throw ValidationException::withMessages(['stock_item' => __('tickets.not_enough_stock')]);
        }

        $code = Barcode::codeFor($fuelType, $spec);
        if (!$code) {
            throw ValidationException::withMessages(['stock_item' => __('tickets.code_not_configured')]);
        }

        $startSerial = (int) $data['start_serial'];
        $endSerial = (int) $data['end_serial'];
        $date = Carbon::parse($data['date'] ?? today())->toDateString();
        $quantity = $this->quantityOf($startSerial, $endSerial);
        if ($quantity > TicketStock::available($fuelType, $spec)) {
            throw ValidationException::withMessages(['end_serial' => __('tickets.not_enough_stock')]);
        }

        $publish = DB::transaction(function () use ($fuelType, $spec, $code, $startSerial, $endSerial, $quantity, $date) {
            $this->refuseOverlap($fuelType, $spec, $startSerial, $endSerial, null);

            $publish = new TicketPublish;
            $publish->forceFill([
                'fuel_type' => $fuelType,
                'spec' => $spec,
                'code' => $code,
                'start_serial' => $startSerial,
                'end_serial' => $endSerial,
                'count' => $quantity,
                'date' => $date,
                'user_id' => auth()->id(),
            ])->save();

            $this->insertTickets($publish, $startSerial, $endSerial);

            return $publish;
        });

        Audit::log('ticket_publish', 'publish #' . $publish->id . ' code ' . $code
            . ' serial ' . $startSerial . '-' . $endSerial . ', ' . config('ticket_specs.' . $spec) . 'kg x ' . $quantity);

        return redirect()->route('ticket-publish.index')->with('status', __('tickets.published', [
            'count' => $quantity,
            'start' => Barcode::make($fuelType, $spec, $startSerial),
            'end' => Barcode::make($fuelType, $spec, $endSerial),
        ]));
    }

    // The tickets of a batch, with their state.
    public function show($id)
    {
        $batch = TicketPublish::findOrFail($id);

        return view('ticket_publish.show', [
            'batch' => $batch,
            'tickets' => Ticket::where('publish_id', $batch->id)->orderBy('serial')->paginate(100),
            'perStatus' => Ticket::where('publish_id', $batch->id)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'fuels' => config('fuel_types'),
        ]);
    }

    // ---- helpers ----

    private function quantityOf($startSerial, $endSerial)
    {
        if ($endSerial < $startSerial) {
            throw ValidationException::withMessages(['end_serial' => __('tickets.range_backwards')]);
        }
        $quantity = $endSerial - $startSerial + 1;
        if ($quantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages(['end_serial' => __('tickets.range_too_large', ['max' => self::MAX_QUANTITY])]);
        }

        return $quantity;
    }

    // Never publish over the range of another batch for this fuel type + spec. Every ticket comes from exactly one publish, so this alone
    // catches it, and it is a cheap indexed look-up against one row per publish.
    private function refuseOverlap($fuelType, $spec, $startSerial, $endSerial, $exceptId)
    {
        $query = TicketPublish::where('fuel_type', $fuelType)->where('spec', $spec)
            ->where('start_serial', '<=', $endSerial)->where('end_serial', '>=', $startSerial)->lockForUpdate();
        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['start_serial' => __('tickets.serial_overlap')]);
        }
    }

    private function insertTickets(TicketPublish $publish, $startSerial, $endSerial)
    {
        $now = now();
        // the publish date of a ticket (shown in the ticket lookup) is the date of its batch, with the time of the entry
        $publishDate = $publish->date ? Carbon::parse($publish->date)->setTimeFrom($now) : $now;
        foreach (array_chunk(range($startSerial, $endSerial), 500) as $serials) {
            Ticket::insert(array_map(function ($serial) use ($publish, $now, $publishDate) {
                return [
                    'barcode' => Barcode::make($publish->fuel_type, $publish->spec, $serial),
                    'code' => $publish->code,
                    'serial' => $serial,
                    'publish_id' => $publish->id,
                    'publish_date' => $publishDate,
                    'fuel_type' => $publish->fuel_type,
                    'spec' => $publish->spec,
                    'status' => config('ticket_statuses.publish'),
                    'synced' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $serials));
        }
    }
}
