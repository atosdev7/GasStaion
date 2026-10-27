<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketPublish;
use App\Support\Admin\Audit;
use App\Support\Tickets\Barcode;
use App\Support\Tickets\LegacyTickets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Changes or deletes many press tickets at once, by serial ranges of one code (fuel type + spec). Made for the go-live: the publishes before it are entered
// as they were, which makes every ticket of them, and the tickets that are not in the stock room any more were sold on paper:
// - delete: removes the tickets that are still published (never sold) of the publishes dated before go-live. Such a ticket is sold again as far as the system
//   is concerned (see LegacyTickets), so nothing is lost. The terminals that already hold the deleted rows keep them until they are registered again.
// - sold: marks the published tickets of the publishes dated before go-live as sold on the day of their publish (no sale, no money).
// - restrict: blocks the published or sold tickets (lost, stolen); any publish.
// A preview with the number of tickets comes first; the work is done in chunks.
class TicketBulkController extends Controller
{
    const ACTIONS = ['delete', 'sold', 'restrict'];
    const MAX_RANGES = 200;
    const CHUNK = 20000;

    public function index()
    {
        return view('ticket_bulk.index', $this->form());
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'stock_item' => ['required', 'regex:/^.+:\d+$/'],
            'ranges' => 'required|string|max:5000',
            'action' => ['required', Rule::in(self::ACTIONS)],
            'confirm' => 'nullable|boolean',
        ]);

        list($fuelType, $spec) = explode(':', $data['stock_item']);
        $code = Barcode::codeFor($fuelType, $spec);
        if (!$code) {
            throw ValidationException::withMessages(['stock_item' => __('tickets.code_not_configured')]);
        }
        $ranges = $this->ranges($data['ranges']);
        $oldOnly = $data['action'] !== 'restrict';
        if ($oldOnly && LegacyTickets::goLive() === null) {
            throw ValidationException::withMessages(['action' => __('tickets.bulk_needs_go_live')]);
        }

        $count = 0;
        foreach ($ranges as $range) {
            $count += $this->query($code, $range, $data['action'])->count();
        }

        if (empty($data['confirm'])) {
            return view('ticket_bulk.index', $this->form() + ['preview' => [
                'count' => $count, 'action' => $data['action'], 'stock_item' => $data['stock_item'], 'ranges' => $data['ranges'], 'code' => $code,
            ]]);
        }

        @set_time_limit(0);
        $done = 0;
        foreach ($ranges as $range) {
            $done += $this->apply($code, $range, $data['action']);
        }

        Audit::log('ticket_bulk', $data['action'] . ' code ' . $code . ' serial ' . $data['ranges'] . ': ' . $done . ' tickets');

        return redirect()->route('ticket-bulk.index')->with('status', __('tickets.bulk_done_' . $data['action'], ['count' => $done]));
    }

    private function form()
    {
        $options = [];
        foreach (config('fuel_types') as $fuel => $fuelName) {
            foreach (config('ticket_specs') as $spec => $kg) {
                if (Barcode::codeFor($fuel, $spec)) {
                    $options[$fuel . ':' . $spec] = $fuelName . ' ' . $kg . ' kg (' . Barcode::codeFor($fuel, $spec) . ')';
                }
            }
        }

        return ['options' => $options, 'goLive' => LegacyTickets::goLive()];
    }

    // "1-150000, 150200, 150300-150500" => [[1, 150000], [150200, 150200], [150300, 150500]]
    private function ranges($text)
    {
        $ranges = [];
        foreach (preg_split('/[\s,;]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (!preg_match('/^(\d+)(?:-(\d+))?$/', $part, $m)) {
                throw ValidationException::withMessages(['ranges' => __('tickets.bulk_bad_range', ['range' => $part])]);
            }
            $from = (int) $m[1];
            $to = isset($m[2]) ? (int) $m[2] : $from;
            if ($from < 1 || $to < $from || $to > Barcode::MAX_SERIAL) {
                throw ValidationException::withMessages(['ranges' => __('tickets.bulk_bad_range', ['range' => $part])]);
            }
            $ranges[] = [$from, $to];
        }
        if (!$ranges || count($ranges) > self::MAX_RANGES) {
            throw ValidationException::withMessages(['ranges' => __('tickets.bulk_ranges_count', ['max' => self::MAX_RANGES])]);
        }

        return $ranges;
    }

    // The tickets of one range the action works on.
    private function query($code, array $range, $action)
    {
        $query = Ticket::where('code', $code)->whereBetween('serial', $range);

        if ($action === 'restrict') {
            return $query->whereIn('status', [config('ticket_statuses.publish'), config('ticket_statuses.sale')]);
        }

        // delete and sold: only tickets nobody has sold yet, and only of the publishes before go-live (the ones the system can take as sold again)
        return $query->where('status', config('ticket_statuses.publish'))
            ->whereIn('publish_id', TicketPublish::where('date', '<', LegacyTickets::goLive())->select('id'));
    }

    private function apply($code, array $range, $action)
    {
        $total = 0;
        do {
            $query = $this->query($code, $range, $action)->limit(self::CHUNK);
            if ($action === 'delete') {
                $n = $query->delete();
            } elseif ($action === 'sold') {
                $n = $query->update(['status' => config('ticket_statuses.sale'), 'sale_date' => DB::raw('publish_date'), 'synced' => 0]);
            } else {
                $n = $query->update(['status' => config('ticket_statuses.restrict'), 'synced' => 0]);
            }
            $total += $n;
        } while ($n > 0);

        return $total;
    }
}
