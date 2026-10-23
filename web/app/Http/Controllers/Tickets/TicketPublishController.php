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
use Illuminate\Validation\ValidationException;

// Turn blank ticket stock (see TicketInboundController) into numbered tickets. Sign and code are
// both fixed, non-admin-editable constants (see App\Support\Tickets\Barcode) — the admin only picks the
// fuel type + spec and enters the serial range.
//
// A batch can be looked at (its tickets), changed (the serial range and the date) or deleted while it is untouched: every ticket of it is still blank
// and none was downloaded by a terminal yet. After that the terminals hold its tickets and it stays as it is. A deleted batch is really deleted
// (no soft delete, nothing is kept of it): its serial numbers are free again, and the start number suggested for a new batch ignores it.
class TicketPublishController extends Controller
{
    const MAX_QUANTITY = 50000;

    public function index()
    {
        return view('ticket_publish.index', [
            'batches' => TicketPublish::orderByDesc('id')->get(),
            'fuels' => config('fuel_types'),
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
            'touched' => $this->touchedReason($batch),
        ]);
    }

    public function edit($id)
    {
        $batch = TicketPublish::findOrFail($id);
        if ($reason = $this->touchedReason($batch)) {
            return redirect()->route('ticket-publish.index')->withErrors(['batch' => $reason]);
        }

        return view('ticket_publish.edit', ['batch' => $batch, 'fuels' => config('fuel_types')]);
    }

    // Gives the batch another serial range (the fuel type, the spec and the code stay): its blank tickets are numbered again.
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'start_serial' => 'required|integer|min:1|max:' . Barcode::MAX_SERIAL,
            'end_serial' => 'required|integer|min:1|max:' . Barcode::MAX_SERIAL,
            'date' => 'nullable|date',
        ]);
        $startSerial = (int) $data['start_serial'];
        $endSerial = (int) $data['end_serial'];
        $quantity = $this->quantityOf($startSerial, $endSerial);
        $date = isset($data['date']) ? Carbon::parse($data['date'])->toDateString() : null; // null: the date stays

        $before = '';
        $batch = DB::transaction(function () use ($id, $startSerial, $endSerial, $quantity, $date, &$before) {
            $batch = TicketPublish::lockForUpdate()->findOrFail($id);
            if ($reason = $this->touchedReason($batch, true)) {
                throw ValidationException::withMessages(['start_serial' => $reason]);
            }
            // the blank tickets of this batch go back to the stock first: the new range may take as many as the stock holds
            if ($quantity > TicketStock::available($batch->fuel_type, $batch->spec) + $batch->count) {
                throw ValidationException::withMessages(['end_serial' => __('tickets.not_enough_stock')]);
            }
            $this->refuseOverlap($batch->fuel_type, $batch->spec, $startSerial, $endSerial, $batch->id);

            $before = $batch->start_serial . '-' . $batch->end_serial;
            Ticket::where('publish_id', $batch->id)->delete();
            $batch->forceFill(['start_serial' => $startSerial, 'end_serial' => $endSerial, 'count' => $quantity] + ($date ? ['date' => $date] : []))->save();
            $this->insertTickets($batch, $startSerial, $endSerial);

            return $batch;
        });

        Audit::log('ticket_publish_update', 'publish #' . $batch->id . ' serial ' . $before . ' -> ' . $startSerial . '-' . $endSerial);

        return redirect()->route('ticket-publish.index')->with('status', __('tickets.saved'));
    }

    // Cancels a wrong batch: its blank tickets are removed and go back to the blank stock, and the batch itself is deleted for good (its numbers are free).
    public function destroy($id)
    {
        $batch = DB::transaction(function () use ($id) {
            $batch = TicketPublish::lockForUpdate()->findOrFail($id);
            if ($reason = $this->touchedReason($batch, true)) {
                throw ValidationException::withMessages(['batch' => $reason]);
            }
            Ticket::where('publish_id', $batch->id)->delete();
            $batch->delete();

            return $batch;
        });

        Audit::log('ticket_publish_delete', 'publish #' . $batch->id . ' serial ' . $batch->start_serial . '-' . $batch->end_serial);

        return redirect()->route('ticket-publish.index')->with('status', __('tickets.deleted'));
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

    // null when the batch is untouched (every ticket of it is blank, none was downloaded by a terminal), else the reason it cannot change.
    // A ticket counts as sold when it is no longer 'published', or names a sale or a supplement. A terminal can sell a ticket before the web app
    // hears of it, so a downloaded batch is never deleted either. $lock (inside a transaction): the tickets are locked first, so that a sale
    // made at the same moment cannot slip in between this check and the delete.
    private function touchedReason(TicketPublish $batch, $lock = false)
    {
        $tickets = Ticket::where('publish_id', $batch->id);
        if ($lock) {
            (clone $tickets)->lockForUpdate()->count();
        }
        $used = (clone $tickets)->where(function ($q) {
            $q->where('status', '<>', config('ticket_statuses.publish'))->orWhereNotNull('sale_id')->orWhereNotNull('sale_date')->orWhereNotNull('supplement_id');
        })->exists();
        $downloaded = (clone $tickets)->where('synced', 1)->exists();

        // a ticket that is gone was used and removed by a backup
        if ($used || (clone $tickets)->count() !== (int) $batch->count) {
            return __('tickets.batch_used');
        }

        return $downloaded ? __('tickets.batch_downloaded') : null;
    }
}
