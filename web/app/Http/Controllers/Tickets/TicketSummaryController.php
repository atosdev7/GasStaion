<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Tickets\TicketInbound;
use App\Models\Tickets\TicketPublish;
use App\Support\Reports\ReportSheets;
use App\Support\Reports\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// The blank tickets in and out of ONE fuel type and spec, day by day: how many were received (ticket inbound), the serial numbers and the
// number published that day (ticket publish), and the balance of blank tickets after that day. Read-only. The user chooses the fuel type and
// the spec and presses Show; the table is the whole history of that fuel type and spec, oldest day first, so the balance is the real stock at
// the end of each day. Deleted records are not counted.
class TicketSummaryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'fuel_type' => ['required_with:spec', 'nullable', Rule::in(array_keys(config('fuel_types')))],
            'spec' => ['required_with:fuel_type', 'nullable', Rule::in(array_keys(config('ticket_specs')))],
        ]);
        $fuelType = $filters['fuel_type'] ?? null;
        $spec = isset($filters['spec']) && $filters['spec'] !== null ? (int) $filters['spec'] : null;
        $chosen = $fuelType !== null && $fuelType !== '' && $spec !== null;

        $rows = collect();
        if ($chosen) {
            $rows = $this->rows($fuelType, $spec);
        }

        $data = [
            'chosen' => $chosen,
            'fuelType' => $fuelType,
            'spec' => $spec,
            'rows' => $rows,
            'fuels' => config('fuel_types'),
            'specs' => config('ticket_specs'),
            'totalIn' => $rows->sum('in'),
            'totalOut' => $rows->sum('out'),
        ];

        // the same table as an Excel file (only for a chosen fuel type and spec)
        if ($chosen && $request->query('export') === 'xls') {
            $file = 'ticket-summary-' . $fuelType . '-' . config('ticket_specs.' . $spec) . 'kg-' . today()->toDateString() . '.xlsx';

            return response()->download(XlsxWriter::build(ReportSheets::ticketSummary($data)), $file, ['Content-Type' => XlsxWriter::MIME])->deleteFileAfterSend(true);
        }

        return view('ticket_summary.index', $data);
    }

    // One row per day on which blank tickets came in or batches were published, with the running balance.
    private function rows($fuelType, $spec)
    {
        $days = [];
        $row = function ($day) use (&$days) {
            $day = substr((string) $day, 0, 10);
            $days[$day] = $days[$day] ?? (object) ['day' => $day, 'in' => 0, 'first' => null, 'last' => null, 'out' => 0, 'balance' => 0];

            return $days[$day];
        };

        $in = TicketInbound::where('fuel_type', $fuelType)->where('spec', $spec)
            ->selectRaw('inbound_date AS day, SUM(quantity) AS n')->groupBy('inbound_date')->get();
        foreach ($in as $r) {
            $row($r->day)->in += (int) $r->n;
        }

        $out = TicketPublish::where('fuel_type', $fuelType)->where('spec', $spec)
            ->selectRaw('COALESCE(`date`, DATE(created_at)) AS day, MIN(start_serial) AS first_serial, MAX(end_serial) AS last_serial, SUM(`count`) AS n')
            ->groupBy(DB::raw('COALESCE(`date`, DATE(created_at))'))->get();
        foreach ($out as $r) {
            $day = $row($r->day);
            $day->out += (int) $r->n;
            $day->first = (int) $r->first_serial;
            $day->last = (int) $r->last_serial;
        }

        ksort($days);
        $balance = 0;
        foreach ($days as $day) {
            $balance += $day->in - $day->out;
            $day->balance = $balance;
        }

        return collect(array_values($days));
    }
}
