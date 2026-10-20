<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Sales\Customer;
use App\Models\Tickets\Ticket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Read-only: a table of the tickets with filters and pages, so that the situation of the tickets can be checked by a list; the Details button
// of a ticket opens a popup with its full lifecycle (published -> sold -> supplied/restricted, plus how it was sold and, if applicable,
// which loan it settled).
class TicketLookupController extends Controller
{
    const PER_PAGE = 50;

    public function index(Request $request)
    {
        $barcode = trim((string) $request->query('barcode', ''));
        // The detail of one ticket is shown in a popup: the popup asks for this page with modal=1 and gets the detail alone. The page itself
        // opens the popup for a barcode of its address (a link from another screen, or the page after an action).
        if ($barcode !== '' && $request->query('modal')) {
            return view('ticket_lookup._detail', [
                'ticket' => Ticket::with(['batch', 'sale', 'loanSettlement.loan', 'supplement.nozzle', 'supplement.device'])->find($barcode),
                'customers' => Customer::pluck('name', 'id'),
                'fuels' => config('fuel_types'),
                'back' => (string) $request->query('back', ''),
            ]);
        }

        // the table of tickets and its filters
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_values(config('ticket_statuses')))],
            'fuel_type' => ['nullable', Rule::in(array_keys(config('fuel_types')))],
            'spec' => ['nullable', Rule::in(array_keys(config('ticket_specs')))],
            'batch' => 'nullable|integer|min:1',
            'published' => 'nullable|date', // the day it was published, sold, supplied: one date each
            'sold' => 'nullable|date',
            'supplied' => 'nullable|date',
            'q' => 'nullable|string|max:16',
        ]);

        $base = Ticket::query();
        if (isset($filters['fuel_type']) && $filters['fuel_type'] !== '') {
            $base->where('fuel_type', $filters['fuel_type']);
        }
        if (isset($filters['spec']) && $filters['spec'] !== '') {
            $base->where('spec', (int) $filters['spec']);
        }
        if (!empty($filters['batch'])) {
            $base->where('publish_id', (int) $filters['batch']);
        }
        // a day as a range of the time stamp, so the index of the column can be used
        foreach (['published' => 'publish_date', 'sold' => 'sale_date', 'supplied' => 'supply_date'] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $base->where($column, '>=', $filters[$filter] . ' 00:00:00')->where($column, '<=', $filters[$filter] . ' 23:59:59');
            }
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $base->where('barcode', 'like', '%' . addcslashes($filters['q'], '%_\\') . '%');
        }

        // how many tickets are in each state under the other filters (the state filter itself is left out)
        $perStatus = (clone $base)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        $list = clone $base;
        if (isset($filters['status']) && $filters['status'] !== '') {
            $list->where('status', (int) $filters['status']);
        }
        $tickets = $list->with('batch')->orderBy('publish_id', 'desc')->orderBy('serial', 'desc')->orderBy('barcode')
            ->paginate(self::PER_PAGE)->appends($request->query());

        return view('ticket_lookup.index', [
            'openBarcode' => $barcode !== '' ? $barcode : null,
            'fuels' => config('fuel_types'),
            'specs' => config('ticket_specs'),
            'filters' => $filters,
            'tickets' => $tickets,
            'perStatus' => $perStatus,
        ]);
    }
}
