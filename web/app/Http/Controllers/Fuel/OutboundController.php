<?php

namespace App\Http\Controllers\Fuel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Nozzle;
use App\Models\Supplement;
use App\Models\Tank;
use Carbon\Carbon;
use Illuminate\Http\Request;

// Fuel outbound: the fuel that left the tanks through the dispensers, from the supplies. Read-only: a supply is recorded by the
// Smart210 (or on the Supplements screen), this page only lists them per tank for a period. Like the reports it counts a supply on its
// date (the business day), see ReportController::day().
class OutboundController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'tank_id' => 'nullable|integer',
        ]);
        $from = Carbon::parse($data['from'] ?? today()->startOfMonth())->toDateString();
        $to = Carbon::parse($data['to'] ?? today())->toDateString();
        $tankId = $data['tank_id'] ?? null;

        $query = Supplement::with('nozzle')->whereRaw(ReportController::day('supplied_at', 'supply_date') . ' BETWEEN ? AND ?', [$from, $to]);
        if ($tankId) {
            // a nozzle deleted on the Smart210 still names its old supplements
            $query->whereIn('nozzle_id', Nozzle::withTrashed()->where('tank_id', $tankId)->pluck('id'));
        }
        $rows = $query->orderByDesc('supplied_at')->get();

        // totals per tank
        $perTank = [];
        foreach ($rows as $row) {
            $tank = $row->nozzle ? (int) $row->nozzle->tank_id : 0;
            $perTank[$tank] = $perTank[$tank] ?? (object) ['tank_id' => $tank, 'supplies' => 0, 'kg' => 0, 'liters' => 0];
            $perTank[$tank]->supplies += 1;
            $perTank[$tank]->kg += (float) $row->out_amount_kg;
            $perTank[$tank]->liters += (float) $row->out_amount_lt;
        }
        ksort($perTank);

        return view('outbound.index', [
            'from' => $from,
            'to' => $to,
            'tankId' => $tankId,
            'rows' => $rows,
            'perTank' => collect(array_values($perTank)),
            'tanks' => Tank::orderBy('name')->pluck('name', 'id'),                       // the tank filter: tanks in use
            'tankNames' => Tank::withTrashed()->orderBy('name')->pluck('name', 'id'),    // names, also of deleted tanks (old supplies)
            'nozzleLabels' => Nozzle::labels(),
            'fuels' => config('fuel_types'),
        ]);
    }
}
