<?php

namespace App\Http\Controllers\Supplements;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Fuel\Nozzle;
use App\Models\Supplements\Remainder;
use App\Models\Supplements\Supplement;
use App\Models\Tickets\Ticket;
use App\Support\Admin\Audit;
use App\Support\Cards\CardLedger;
use App\Support\Supplements\RemainderMaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// The remainder tickets: when less fuel came out than the tickets of a supply covered, the rest is handed back as a new ticket (see
// SupplementController). This lists them with the supply they came from and what became of the ticket.
class RemainderController extends Controller
{
    // The supplies that can still give a remainder (their tickets covered more than was dispensed and they have none yet), newest first.
    private function supplies()
    {
        // (not the supplies of tickets a card paid for: their unused part goes back to the card)
        return Supplement::with('nozzle')->doesntHave('remainder')->whereRaw('preset_kg - out_amount_kg >= 0.01')->orderByDesc('supplied_at')->limit(500)->get()
            ->filter(function ($supplement) {
                return RemainderMaker::room($supplement) >= 0.01;
            })->values();
    }

    public function create()
    {
        return view('remainder.create', [
            'supplies' => $this->supplies(),
            'nozzleLabels' => Nozzle::labels(),
            'fuels' => config('fuel_types'),
        ]);
    }

    // A remainder made by hand: from a supply that gave less than its tickets covered. It is the same record a supply makes (a new
    // ticket, already sold, and the remainder row); the weight may be at most what was not dispensed.
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplement_id' => ['required', 'uuid', \Illuminate\Validation\Rule::exists('supplements', 'id')->whereNull('deleted_at')],
            'amount_kg' => 'required|numeric|min:0.01|max:99999999',
            'remainder_date' => 'nullable|date_format:Y-m-d|before_or_equal:today', // the day of the remainder; today when it is not sent
            'comment' => 'nullable|string|max:255',
        ]);

        $remainder = DB::transaction(function () use ($data) {
            $supplement = Supplement::lockForUpdate()->findOrFail($data['supplement_id']);
            $room = RemainderMaker::room($supplement);
            if ($supplement->remainder()->exists()) {
                throw ValidationException::withMessages(['supplement_id' => __('supplements.remainder_exists')]);
            }
            if (CardLedger::paidByCard($supplement)) {
                throw ValidationException::withMessages(['supplement_id' => __('supplements.remainder_goes_to_card')]);
            }
            if ((float) $data['amount_kg'] > $room + 0.0001) {
                throw ValidationException::withMessages(['amount_kg' => __('supplements.remainder_too_much', ['kg' => number_format($room, 2)])]);
            }

            return RemainderMaker::make($supplement, round((float) $data['amount_kg'], 2), now(), $data['remainder_date'] ?? today()->toDateString(), $data['comment'] ?? null);
        });

        Audit::log('remainder_create', '#' . $remainder->id . ' ' . $remainder->ticket_barcode . ' ' . $remainder->amount_kg . 'kg of supply ' . $remainder->supplement_id . ($remainder->comment ? ' (' . $remainder->comment . ')' : ''));

        return redirect()->route('remainders.index')->with('status', __('supplements.remainder_added', ['barcode' => $remainder->ticket_barcode, 'kg' => number_format($remainder->amount_kg, 2, '.', '')]));
    }

    // The comment of a remainder can be written or changed after it was made (the other fields are what the supply gave and stay). An empty one
    // takes the comment away. A comment typed on the Smart210 and uploaded later replaces this one: the terminal's copy is the newer.
    public function updateComment(Request $request, $id)
    {
        $data = $request->validate(['comment' => 'nullable|string|max:255']);
        $remainder = Remainder::findOrFail($id);

        $before = (string) $remainder->comment;
        $after = trim((string) ($data['comment'] ?? ''));
        $remainder->comment = $after === '' ? null : $after;
        $remainder->save();

        if ($before !== $after) {
            Audit::log('remainder_comment', '#' . $remainder->id . ' ' . $remainder->ticket_barcode . ': ' . ($before === '' ? '-' : $before) . ' -> ' . ($after === '' ? '-' : $after));
        }

        return redirect()->back()->with('status', __('supplements.remainder_comment_saved'));
    }

    public function index(Request $request)
    {
        // the filters sit in the heads of the columns: the day (its date, the 9:00 business day), the fuel type and a part of the barcode
        $filters = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'fuel_type' => 'nullable|string|max:4',
            'q' => 'nullable|string|max:16',
        ]);

        $query = Remainder::query();
        if (!empty($filters['date'])) {
            $query->whereRaw(ReportController::day('created_at', 'remainder_date') . ' = ?', [$filters['date']]);
        }
        if (!empty($filters['fuel_type'])) {
            $query->where('fuel_type', $filters['fuel_type']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $query->where('ticket_barcode', 'like', '%' . addcslashes($filters['q'], '%_\\') . '%');
        }

        $totalKg = (clone $query)->sum('amount_kg');
        $remainders = $query->orderByDesc('created_at')->paginate(50)->appends($request->query());

        // the supply each came from (with its nozzle) and the state of each remainder ticket
        $supplements = Supplement::withTrashed()->whereIn('id', $remainders->pluck('supplement_id'))->get()->keyBy('id');
        $states = Ticket::whereIn('barcode', $remainders->pluck('ticket_barcode'))->pluck('status', 'barcode');

        return view('remainder.index', [
            'remainders' => $remainders,
            'filters' => $filters,
            'supplements' => $supplements,
            'states' => $states,
            'totalKg' => $totalKg,
            'fuels' => config('fuel_types'),
            'nozzleLabels' => Nozzle::labels(),
        ]);
    }
}
