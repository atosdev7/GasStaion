<?php

namespace App\Http\Controllers\Supplements;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\ReportController;
use App\Models\FuelType;
use App\Models\Nozzle;
use App\Models\Remainder;
use App\Models\Supplement;
use App\Models\Ticket;
use App\Support\Audit;
use App\Support\RemainderMaker;
use App\Support\SerialCounter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// One supply event: the customer hands over one or more sold tickets and fuel is dispensed at a nozzle. The tickets are
// assigned to the supplement (tickets.supplement_id) and go to status=supply; if less was dispensed than the tickets
// cover, the rest comes back as a new remainder ticket, as the Smart210 does. This is the manual web entry;
// the Smart210 creates the same kind of record from its own sessions.
class SupplementController extends Controller
{
    const MAX_TICKETS = 200;

    public function index(Request $request)
    {
        // the filters sit in the heads of the columns: the day of the supply (its date, the 9:00 business day), the nozzle and the mode
        $filters = $request->validate([
            'supply_date' => 'nullable|date_format:Y-m-d',
            'nozzle_id' => 'nullable|integer',
            'mode' => ['nullable', Rule::in(Supplement::MODES)],
        ]);

        $query = Supplement::query();
        if (!empty($filters['supply_date'])) {
            $query->whereRaw(ReportController::day('supplied_at', 'supply_date') . ' = ?', [$filters['supply_date']]);
        }
        if (!empty($filters['nozzle_id'])) {
            $query->where('nozzle_id', $filters['nozzle_id']);
        }
        if (!empty($filters['mode'])) {
            $query->where('mode', $filters['mode']);
        }

        // the totals are of everything the filters let through, not of the page
        $totalKg = (clone $query)->sum('out_amount_kg');
        $totalLiters = (clone $query)->sum('out_amount_lt');
        $supplements = $query->withCount('tickets')->with('remainder')->orderByDesc('supplied_at')->paginate(50)->appends($request->query());

        return view('supplement.index', [
            'supplements' => $supplements,
            'filters' => $filters,
            'nozzleLabels' => Nozzle::labels(),
            'modes' => Supplement::MODES,
            'totalKg' => $totalKg,
            'totalLiters' => $totalLiters,
        ]);
    }

    // One supply with the tickets it used: a page, or only the detail (for the popup of the list) when asked with modal=1.
    public function show(Request $request, $id)
    {
        $supplement = Supplement::withTrashed()->with('remainder')->findOrFail($id);
        $labels = Nozzle::labels();

        return view($request->query('modal') ? 'supplement._detail' : 'supplement.show', [
            'supplement' => $supplement,
            'nozzle' => isset($labels[$supplement->nozzle_id]) ? $labels[$supplement->nozzle_id] : $supplement->nozzle_id,
            'card' => $supplement->card_id ? optional(\App\Models\Card::withTrashed()->find($supplement->card_id))->label : null,   // a supply made with a card
            'fuels' => config('fuel_types'),
            'ticketCount' => Ticket::where('supplement_id', $supplement->id)->count(),
            'tickets' => Ticket::where('supplement_id', $supplement->id)->orderBy('barcode')->limit(200)->get(),
        ]);
    }

    public function create()
    {
        return view('supplement.create', [
            'nozzleOptions' => Nozzle::labels(),
            // the density of the fuel type of every nozzle, to fill the density field when a nozzle is chosen
            'densities' => Nozzle::pluck('fuel_type', 'id')->map(function ($fuelType) {
                return optional(FuelType::find($fuelType))->density;
            }),
            'modes' => Supplement::MODES,
        ]);
    }

    public function store(Request $request)
    {
        // The barcodes arrive as free text (one per line, the way a scanner types them): split them before validating.
        $request->merge([
            'ticket_barcodes' => preg_split('/[\s,;]+/', trim((string) $request->input('ticket_barcodes')), -1, PREG_SPLIT_NO_EMPTY),
        ]);

        $data = $request->validate([
            'ticket_barcodes' => ['required', 'array', 'max:' . self::MAX_TICKETS],
            'ticket_barcodes.*' => ['string', 'size:16', 'distinct'],
            'nozzle_id' => ['required', Rule::exists('nozzles', 'id')->whereNull('deleted_at')],
            'out_amount_lt' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'supplied_at' => ['required', 'date'],
            'density' => 'nullable|numeric|min:0.0001|max:9999.9999', // kg per litre; the density of the fuel type when it is not sent
            'mode' => ['nullable', Rule::in(Supplement::MODES)], // auto when it is not sent
        ]);

        $result = DB::transaction(function () use ($data) {
            // Only tickets the customer actually holds (sold, not blank stock, not already used or restricted) can be
            // supplied against, the same rule as a loan settlement. All of them or none.
            $tickets = Ticket::whereIn('barcode', $data['ticket_barcodes'])
                ->where('status', config('ticket_statuses.sale'))
                ->lockForUpdate()
                ->get();

            $missing = array_values(array_diff($data['ticket_barcodes'], $tickets->pluck('barcode')->all()));
            if ($missing) {
                throw ValidationException::withMessages([
                    'ticket_barcodes' => __('supplements.tickets_not_suppliable', ['barcodes' => implode(', ', $missing)]),
                ]);
            }

            $nozzle = Nozzle::findOrFail($data['nozzle_id']);
            if ($tickets->contains(function ($ticket) use ($nozzle) {
                return $ticket->fuel_type !== $nozzle->fuel_type;
            })) {
                throw ValidationException::withMessages(['nozzle_id' => __('supplements.nozzle_fuel_type_mismatch')]);
            }

            // the density the litres are converted with: the one typed in the form, else the one of the fuel type
            $density = !empty($data['density']) ? (float) $data['density'] : optional(FuelType::find($nozzle->fuel_type))->density;
            if (!$density) {
                throw ValidationException::withMessages(['density' => __('supplements.density_not_configured')]);
            }

            // A press ticket's amount comes from its spec. Computed once here and stored, like sales.price/cost: a
            // point-in-time fact, not re-derived if the density or a spec changes later.
            $specs = config('ticket_specs');
            $presetKg = round($tickets->sum(function ($ticket) use ($specs) {
                return $ticket->amount_kg !== null ? $ticket->amount_kg : ($specs[$ticket->spec] ?? 0);
            }), 2);
            $amountKg = round($data['out_amount_lt'] * $density, 2);

            if ($amountKg > $presetKg) {
                throw ValidationException::withMessages([
                    'out_amount_lt' => __('supplements.supplied_more_than_tickets', ['preset' => number_format($presetKg, 2)]),
                ]);
            }

            $suppliedAt = Carbon::parse($data['supplied_at']);
            $supplement = Supplement::forceCreate([
                'fuel_type' => $nozzle->fuel_type,
                'nozzle_id' => $nozzle->id,
                'device_id' => $nozzle->device_id,
                'out_amount_lt' => $data['out_amount_lt'],
                'density' => $density,
                'out_amount_kg' => $amountKg,
                'preset_kg' => $presetKg,
                'mode' => isset($data['mode']) ? $data['mode'] : 'auto',
                'supplied_at' => $suppliedAt,
                'supply_date' => $suppliedAt->toDateString(),
            ]);

            foreach ($tickets as $ticket) {
                $ticket->forceFill([
                    'status' => config('ticket_statuses.supply'),
                    'supply_date' => now(),
                    'supplement_id' => $supplement->id,
                ])->save();
            }

            return [$supplement, $this->returnRemainder($supplement, $presetKg - $amountKg), $tickets->count()];
        });

        list($supplement, $remainder, $ticketCount) = $result;

        Audit::log('supplement', '#' . $supplement->id . ' ' . $supplement->mode . ' ' . $ticketCount . ' tickets, ' . $supplement->out_amount_lt . 'L / '
            . $supplement->out_amount_kg . 'kg of ' . $supplement->preset_kg . 'kg'
            . ($remainder ? ', remainder ' . $remainder->ticket_barcode . ' ' . $remainder->amount_kg . 'kg' : ''));

        $kg = number_format($supplement->out_amount_kg, 2, '.', '');

        return redirect()->route('supplements.index')->with('status', $remainder
            ? __('supplements.supplied_with_remainder', [
                'kg' => $kg, 'barcode' => $remainder->ticket_barcode, 'left' => number_format($remainder->amount_kg, 2, '.', ''),
            ])
            : __('supplements.supplied', ['kg' => $kg]));
    }

    // The tickets were paid for in full, so what was not dispensed becomes a new ticket for the customer: already sold,
    // no sale behind it. The Smart210 gets it with the next pull and can reprint it.
    private function returnRemainder(Supplement $supplement, $leftKg)
    {
        $leftKg = round($leftKg, 2);
        if ($leftKg <= 0) {
            return null;
        }

        // created_at is the moment of the supply, which is the moment of the remainder
        return RemainderMaker::make($supplement, $leftKg, $supplement->supplied_at, $supplement->supply_date);
    }
}
