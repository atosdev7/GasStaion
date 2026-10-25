<?php

namespace App\Http\Controllers\Fuel;

use App\Http\Controllers\Controller;
use App\Models\Fuel\FuelOutbound;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;
use App\Support\Admin\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;

// Fuel outbound: the fuel that left the tanks. A row is either made by hand here (a transfer, a sample, fuel returned to the supplier: add, change and
// remove it) or follows a supply (it is made, changed and removed with the supplement, see FuelOutbound, and cannot be changed here). The stock of a
// tank is worked out from these rows (TankLevels). A supply counts on its date (the business day, see Supplement::getSupplyDayAttribute).
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

        $query = FuelOutbound::with('supplement')->whereBetween('outbound_date', [$from, $to]);
        if ($tankId) {
            $query->where('tank_id', $tankId);
        }
        $rows = $query->orderByDesc('outbound_date')->orderByDesc('id')->get();

        // totals per tank (the litres are known for a supply only)
        $perTank = [];
        foreach ($rows as $row) {
            $perTank[$row->tank_id] = $perTank[$row->tank_id] ?? (object) ['tank_id' => (int) $row->tank_id, 'supplies' => 0, 'count' => 0, 'kg' => 0, 'liters' => 0];
            $perTank[$row->tank_id]->count += 1;
            $perTank[$row->tank_id]->kg += (float) $row->amount_kg;
            if ($row->supplement) {
                $perTank[$row->tank_id]->supplies += 1;
                $perTank[$row->tank_id]->liters += (float) $row->supplement->out_amount_lt;
            }
        }
        ksort($perTank);

        return view('outbound.index', [
            'from' => $from,
            'to' => $to,
            'tankId' => $tankId,
            'rows' => $rows,
            'perTank' => collect(array_values($perTank)),
            'tanks' => Tank::orderBy('name')->pluck('name', 'id'),                       // the tank filter: tanks in use
            'tankNames' => Tank::withTrashed()->orderBy('name')->pluck('name', 'id'),    // names, also of deleted tanks (old rows)
            'nozzleLabels' => Nozzle::labels(),
            'fuels' => config('fuel_types'),
        ]);
    }

    public function create()
    {
        return view('outbound.form', ['outbound' => new FuelOutbound, 'tanks' => Tank::orderBy('name')->pluck('name', 'id')]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $outbound = new FuelOutbound;
        $outbound->forceFill($data + ['user_id' => auth()->id()])->save();

        Audit::log('outbound_create', '#' . $outbound->id . ' tank ' . $outbound->tank_id . ', ' . $outbound->amount_kg . ' kg' . ($outbound->to_name ? ', to ' . $outbound->to_name : ''));

        return redirect()->route('outbound.index')->with('status', __('fuel.outbound_created'));
    }

    public function edit($id)
    {
        $outbound = $this->byHand($id);

        return view('outbound.form', ['outbound' => $outbound, 'tanks' => Tank::orderBy('name')->pluck('name', 'id')]);
    }

    public function update(Request $request, $id)
    {
        $outbound = $this->byHand($id);
        $data = $this->validated($request);

        $before = '#' . $outbound->id . ' ' . $outbound->outbound_date . ', tank ' . $outbound->tank_id . ', ' . $outbound->amount_kg . ' kg';
        $outbound->forceFill($data)->save();

        Audit::log('outbound_update', $before . ' -> ' . $data['outbound_date'] . ', tank ' . $data['tank_id'] . ', ' . $data['amount_kg'] . ' kg');

        return redirect()->route('outbound.index')->with('status', __('fuel.outbound_saved'));
    }

    public function destroy($id)
    {
        $outbound = $this->byHand($id);
        $outbound->delete();

        Audit::log('outbound_delete', '#' . $outbound->id . ' ' . $outbound->outbound_date . ', tank ' . $outbound->tank_id . ', ' . $outbound->amount_kg . ' kg');

        return redirect()->route('outbound.index')->with('status', __('fuel.outbound_deleted'));
    }

    // Only a row made by hand can be changed here: the row of a supply follows the supply.
    private function byHand($id)
    {
        $outbound = FuelOutbound::findOrFail($id);
        abort_if($outbound->isSupply(), 403, __('fuel.outbound_of_supply'));

        return $outbound;
    }

    private function validated(Request $request)
    {
        return $request->validate([
            'tank_id' => 'required|integer|exists:tanks,id,deleted_at,NULL',
            'outbound_date' => 'required|date',
            'amount_kg' => 'required|numeric|min:0.01|max:999999999',
            'to_name' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
        ]);
    }
}
