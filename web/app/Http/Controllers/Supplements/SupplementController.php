<?php

namespace App\Http\Controllers\Supplements;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Cards\Card;
use App\Models\Fuel\FuelOutbound;
use App\Models\Fuel\FuelType;
use App\Models\Fuel\Nozzle;
use App\Models\Supplements\Remainder;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Support\Admin\Audit;
use App\Support\Cards\CardLedger;
use App\Support\Fuel\CostChoices;
use App\Support\Supplements\RemainderMaker;
use App\Support\Supplements\SupplementDeletion;
use App\Support\Tickets\Barcode;
use App\Support\Tickets\LegacyTickets;
use App\Support\Tickets\SerialCounter;
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
            'card' => $supplement->card_id ? optional(\App\Models\Cards\Card::withTrashed()->find($supplement->card_id))->label : null,   // a supply made with a card
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
            // the costs a supply can be made at: those of the fuel in the tank of each nozzle (for a user who may see costs)
            'costChoices' => $this->canSeeCosts() ? CostChoices::byNozzle() : null,
        ]);
    }

    private function canSeeCosts()
    {
        return auth()->user() && auth()->user()->can('costs.view');
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
            'supplied_at' => ['required', 'date', 'before_or_equal:now'], // a supply has happened already
            'density' => 'nullable|numeric|min:0.0001|max:9999.9999', // kg per litre; the density of the fuel type when it is not sent
            'mode' => ['nullable', Rule::in(Supplement::MODES)], // auto when it is not sent
            'cost' => 'nullable|numeric|min:0|max:9999999', // the cost per kg: one of the costs of the fuel in the tank; the default cost when it is not sent
        ], ['supplied_at.before_or_equal' => __('supplements.supplied_in_future')]);

        $result = DB::transaction(function () use ($data) {
            LegacyTickets::ensure($data['ticket_barcodes']);       // a ticket sold before go-live has no row until it is used

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

            // One supply takes tickets of one kind (press, spot sale, self use, other use, ticket loan, card, remainder) and the tickets of a card are all of one card.
            $kindOf = function ($ticket) {
                $kind = Barcode::posType($ticket->code);

                return $kind === null ? 'press' : $kind;
            };
            if ($tickets->map($kindOf)->unique()->count() > 1) {
                throw ValidationException::withMessages(['ticket_barcodes' => __('supplements.tickets_mixed_kind')]);
            }
            if ($tickets->pluck('card_id')->unique()->count() > 1) {
                throw ValidationException::withMessages(['ticket_barcodes' => __('supplements.tickets_mixed_card')]);
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
                'cost' => $this->canSeeCosts() && isset($data['cost']) && $data['cost'] !== '' ? $data['cost'] : CostChoices::costForNewSupply($nozzle->tank_id),
                'preset_kg' => $presetKg,
                'mode' => isset($data['mode']) ? $data['mode'] : 'auto',
                'supplied_at' => $suppliedAt,
                'supply_date' => $suppliedAt->toDateString(),
            ]);

            foreach ($tickets as $ticket) {
                $ticket->forceFill([
                    'status' => config('ticket_statuses.supply'),
                    'supply_date' => $suppliedAt, // the moment of the supply, not of the entry: a remainder ticket used up here completes its remainder on that day
                    'supplement_id' => $supplement->id,
                ])->save();
            }

            // the unused part of the tickets a card paid for goes back to the card (the ledger made the entry when the tickets joined the supply)
            $refund = CardLedger::refund($supplement);

            return [$supplement, CardLedger::paidByCard($supplement) ? null : $this->returnRemainder($supplement, $presetKg - $amountKg), $tickets->count(), $refund];
        });

        list($supplement, $remainder, $ticketCount, $refund) = $result;

        Audit::log('supplement', '#' . $supplement->id . ' ' . $supplement->mode . ' ' . $ticketCount . ' tickets, ' . $supplement->out_amount_lt . 'L / '
            . $supplement->out_amount_kg . 'kg of ' . $supplement->preset_kg . 'kg'
            . ($remainder ? ', remainder ' . $remainder->ticket_barcode . ' ' . $remainder->amount_kg . 'kg' : '')
            . ($refund ? ', ' . number_format($refund[1], 2, '.', '') . 'kg back to card ' . $refund[0] : ''));

        $kg = number_format($supplement->out_amount_kg, 2, '.', '');
        if ($refund) {
            return redirect()->route('supplements.index')->with('status', __('supplements.supplied_with_refund', [
                'kg' => $kg, 'left' => number_format($refund[1], 2, '.', ''), 'card' => optional(Card::withTrashed()->find($refund[0]))->label,
            ]));
        }

        return redirect()->route('supplements.index')->with('status', $remainder
            ? __('supplements.supplied_with_remainder', [
                'kg' => $kg, 'barcode' => $remainder->ticket_barcode, 'left' => number_format($remainder->amount_kg, 2, '.', ''),
            ])
            : __('supplements.supplied', ['kg' => $kg]));
    }

    // The comment of a supply (the manager, the accountant and the admin); the admin also changes the nozzle (of the same fuel type), the time, the mode and
    // the litres (not when the supply made a remainder ticket or was paid with a card: it is deleted and made again). A confirmed supply is locked until
    // a manager takes its confirmation back.
    public function edit($id)
    {
        $supplement = Supplement::with('remainder')->findOrFail($id);
        if ($supplement->confirmed_at) {
            return redirect()->route('supplements.index')->withErrors(['supplement' => __('supplements.blocked_confirmed')]);
        }

        $labels = Nozzle::labels();
        $sameFuel = Nozzle::where('fuel_type', $supplement->fuel_type)->pluck('id')->all();

        return view('supplement.edit', [
            'supplement' => $supplement,
            'full' => auth()->user()->can('supplements.manage'),
            'nozzleOptions' => collect($labels)->only($sameFuel),
            'litresFixed' => $supplement->remainder !== null || $supplement->card_id !== null,
            'fuels' => config('fuel_types'),
            // the cost per kg of the supply: one of the costs of the fuel in the tank it takes its fuel from (and the one it has), for a user who may see costs
            'costChoices' => $this->canSeeCosts() ? CostChoices::forTank($this->tankOf($supplement), $supplement->cost) : null,
        ]);
    }

    // The tank a supply took its fuel from: the one of its outbound row, otherwise the one of its nozzle.
    private function tankOf(Supplement $supplement)
    {
        $tankId = FuelOutbound::withTrashed()->where('supplement_id', $supplement->id)->value('tank_id');

        return $tankId ?: optional(Nozzle::withTrashed()->find($supplement->nozzle_id))->tank_id;
    }

    public function update(Request $request, $id)
    {
        $supplement = Supplement::with('remainder')->findOrFail($id);
        if ($supplement->confirmed_at) {
            return redirect()->route('supplements.index')->withErrors(['supplement' => __('supplements.blocked_confirmed')]);
        }

        $full = $request->user()->can('supplements.manage');
        $rules = ['comment' => 'nullable|string|max:255'];
        if ($this->canSeeCosts()) {
            $rules['cost'] = 'nullable|numeric|min:0|max:9999999';       // the cost per kg is chosen again by whoever may see costs (the accountant, before confirming)
        }
        if ($full) {
            $rules += [
                'nozzle_id' => ['required', Rule::exists('nozzles', 'id')->whereNull('deleted_at')],
                'supplied_at' => ['required', 'date', 'before_or_equal:now'],
                'mode' => ['required', Rule::in(Supplement::MODES)],
                'out_amount_lt' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            ];
        }
        $data = $request->validate($rules, ['supplied_at.before_or_equal' => __('supplements.supplied_in_future')]);

        $before = $supplement->supplied_at . ' ' . $supplement->mode . ' ' . $supplement->out_amount_lt . 'L / ' . $supplement->out_amount_kg . 'kg nozzle ' . $supplement->nozzle_id
            . ' / ' . $supplement->comment;
        $changes = ['comment' => isset($data['comment']) && trim($data['comment']) !== '' ? trim($data['comment']) : null];
        if (isset($data['cost']) && $data['cost'] !== null) {
            $changes['cost'] = $data['cost'];
        }

        if ($full) {
            $nozzle = Nozzle::findOrFail($data['nozzle_id']);
            if ($nozzle->fuel_type !== $supplement->fuel_type) {
                throw ValidationException::withMessages(['nozzle_id' => __('supplements.nozzle_fuel_type_mismatch')]);
            }
            $suppliedAt = Carbon::parse($data['supplied_at']);
            $changes += [
                'nozzle_id' => $nozzle->id, 'device_id' => $nozzle->device_id, 'mode' => $data['mode'], 'supplied_at' => $suppliedAt,
                'supply_date' => $suppliedAt->toDateString(),
            ];

            $litresFixed = $supplement->remainder !== null || $supplement->card_id !== null;
            if (!$litresFixed && round((float) $data['out_amount_lt'], 2) !== round((float) $supplement->out_amount_lt, 2)) {
                $density = $supplement->density ?: optional(FuelType::find($supplement->fuel_type))->density;
                if (!$density) {
                    throw ValidationException::withMessages(['out_amount_lt' => __('supplements.density_not_configured')]);
                }
                $kg = round($data['out_amount_lt'] * $density, 2);
                if ($kg > $supplement->preset_kg) {
                    throw ValidationException::withMessages(['out_amount_lt' => __('supplements.supplied_more_than_tickets', ['preset' => number_format($supplement->preset_kg, 2)])]);
                }
                $changes += ['out_amount_lt' => $data['out_amount_lt'], 'out_amount_kg' => $kg];
            }
        }

        // Less fuel than the tickets cover is never lost: what is left comes back as a remainder ticket, as when the supply was made (to the card, when a card
        // paid for the tickets: the ledger makes that entry when the supply is saved).
        $remainder = null;
        DB::transaction(function () use ($supplement, $changes, &$remainder) {
            $moved = isset($changes['supplied_at']) && $changes['supplied_at']->format('Y-m-d H:i:s') !== substr((string) $supplement->supplied_at, 0, 19);
            $supplement->forceFill($changes)->save();
            $left = round($supplement->preset_kg - $supplement->out_amount_kg, 2);
            if (isset($changes['out_amount_kg']) && $left > 0 && !$supplement->remainder && !CardLedger::paidByCard($supplement)) {
                $remainder = RemainderMaker::make($supplement, $left, $supplement->supplied_at, $supplement->supply_date);
            }
            if ($moved) {
                $this->moveDates($supplement);
            }
        });
        Audit::log($full ? 'supplement_edit' : 'supplement_comment', '#' . $supplement->id . ': ' . $before . ' -> ' . $supplement->supplied_at . ' ' . $supplement->mode . ' '
            . $supplement->out_amount_lt . 'L / ' . $supplement->out_amount_kg . 'kg nozzle ' . $supplement->nozzle_id . ' / ' . $supplement->comment . ' / cost ' . $supplement->cost);

        return redirect()->route('supplements.index')->with('status', $remainder
            ? __('supplements.saved_with_remainder', ['barcode' => $remainder->ticket_barcode, 'left' => number_format($remainder->amount_kg, 2, '.', '')])
            : __('supplements.saved'));
    }

    // The supply moved to another time: what follows it moves with it. The tickets it used were supplied at that moment (and a remainder ticket among
    // them was completed on that day), and the remainder it made was made at that moment and on that day.
    private function moveDates(Supplement $supplement)
    {
        $day = substr((string) $supplement->supplied_at, 0, 10);
        foreach (Ticket::where('supplement_id', $supplement->id)->get() as $ticket) {
            $ticket->forceFill(['supply_date' => $supplement->supplied_at])->save();
            foreach (Remainder::where('ticket_barcode', $ticket->barcode)->whereNotNull('complete_date')->get() as $used) {
                $used->forceFill(['complete_date' => $day])->save();
            }
        }

        $remainder = Remainder::where('supplement_id', $supplement->id)->first();
        if ($remainder) {
            $remainder->forceFill(['created_at' => $supplement->supplied_at, 'remainder_date' => $supplement->supply_date])->save();
            $made = Ticket::find($remainder->ticket_barcode);
            if ($made) {
                $made->forceFill(['sale_date' => $supplement->supplied_at])->save();
            }
        }
    }

    // The admin deletes a supply made wrong (see SupplementDeletion).
    public function destroy($id)
    {
        $supplement = SupplementDeletion::delete($id);

        Audit::log('supplement_delete', '#' . $supplement->id . ' ' . $supplement->mode . ' ' . $supplement->out_amount_lt . 'L / ' . $supplement->out_amount_kg . 'kg of ' . $supplement->preset_kg . 'kg');

        return redirect()->route('supplements.index')->with('status', __('supplements.deleted'));
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
