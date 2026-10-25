<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Tickets\TicketInbound;
use App\Support\Admin\Audit;
use App\Support\Tickets\TicketStock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Blank ticket stock received: fuel type, spec (fixed cylinder weight), count. No serial numbers yet
// (numbering happens on publish, see TicketPublishController).
class TicketInboundController extends Controller
{
    // Every filter sits in the head of its column: the day of the inbound, the fuel type and the spec.
    public function index(Request $request)
    {
        $filters = $request->validate([
            'inbound_date' => 'nullable|date_format:Y-m-d',
            'fuel_type' => ['nullable', Rule::in(array_keys(config('fuel_types')))],
            'spec' => ['nullable', Rule::in(array_keys(config('ticket_specs')))],
        ]);

        $query = TicketInbound::orderByDesc('inbound_date')->orderByDesc('id');
        if (!empty($filters['inbound_date'])) {
            $query->where('inbound_date', $filters['inbound_date']);
        }
        if (!empty($filters['fuel_type'])) {
            $query->where('fuel_type', $filters['fuel_type']);
        }
        if (isset($filters['spec']) && $filters['spec'] !== '') {
            $query->where('spec', (int) $filters['spec']);
        }

        return view('ticket_inbound.index', [
            'rows' => $query->paginate(50)->appends($request->query()),
            'filters' => $filters,
            'fuels' => config('fuel_types'),
            'specs' => config('ticket_specs'),
        ]);
    }

    public function create()
    {
        return view('ticket_inbound.create', [
            'fuels' => config('fuel_types'),
            'specs' => config('ticket_specs'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'spec' => ['required', Rule::in(array_keys(config('ticket_specs')))],
            'quantity' => 'required|integer|min:1|max:10000000',
            'inbound_date' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $inbound = new TicketInbound;
        $inbound->forceFill($data + ['user_id' => auth()->id()])->save();

        Audit::log('ticket_inbound', '#' . $inbound->id . ' fuel ' . $data['fuel_type']
            . ', ' . config('ticket_specs.' . $data['spec']) . 'kg x ' . $data['quantity']);

        return redirect()->route('ticket-inbound.index')->with('status', __('tickets.created'));
    }

    public function edit($id)
    {
        return view('ticket_inbound.edit', [
            'inbound' => TicketInbound::findOrFail($id),
            'fuels' => config('fuel_types'),
        ]);
    }

    // Changes the quantity, the date and the note of a record. The fuel type and the spec stay: the published batches are numbered for them.
    // The quantity may not go so low that fewer blank tickets were received than were published already.
    public function update(Request $request, $id)
    {
        $inbound = TicketInbound::findOrFail($id);
        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:10000000',
            'inbound_date' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        if (TicketStock::available($inbound->fuel_type, $inbound->spec) - $inbound->quantity + $data['quantity'] < 0) {
            return back()->withInput()->withErrors(['quantity' => __('tickets.ticket_inbound_below_published')]);
        }

        $before = '#' . $inbound->id . ' ' . $inbound->inbound_date . ', ' . $inbound->quantity;
        $inbound->forceFill($data)->save();
        Audit::log('ticket_inbound_update', $before . ' -> ' . $data['inbound_date'] . ', ' . $data['quantity']);

        return redirect()->route('ticket-inbound.index')->with('status', __('tickets.saved'));
    }

    // Removes a wrong record (it is kept as deleted), unless the blank tickets that remain would be fewer than the ones published.
    public function destroy($id)
    {
        $inbound = TicketInbound::findOrFail($id);

        if (TicketStock::available($inbound->fuel_type, $inbound->spec) - $inbound->quantity < 0) {
            return redirect()->route('ticket-inbound.index')->withErrors(['inbound' => __('tickets.ticket_inbound_below_published')]);
        }

        $inbound->delete();
        Audit::log('ticket_inbound_delete', '#' . $inbound->id . ' ' . $inbound->inbound_date . ', ' . $inbound->quantity);

        return redirect()->route('ticket-inbound.index')->with('status', __('tickets.deleted'));
    }
}
