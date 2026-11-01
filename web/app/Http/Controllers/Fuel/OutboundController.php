<?php

namespace App\Http\Controllers\Fuel;

use App\Http\Controllers\Controller;
use App\Models\Fuel\FuelOutbound;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;
use App\Support\Admin\Audit;
use App\Support\Fuel\CostChoices;
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

    private function canSeeCosts()
    {
        return auth()->user() && auth()->user()->can('costs.view');
    }

    public function create()
    {
        return view('outbound.form', [
            'outbound' => new FuelOutbound, 'tanks' => Tank::orderBy('name')->pluck('name', 'id'),
            'costChoices' => $this->canSeeCosts() ? CostChoices::byTank() : null,      // the costs of the fuel in each tank
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $outbound = new FuelOutbound;
        $outbound->forceFill($data + ['user_id' => auth()->id()])->save();

        Audit::log('outbound_create', '#' . $outbound->id . ' tank ' . $outbound->tank_id . ', ' . $outbound->amount_kg . ' kg' . ($outbound->to_name ? ', to ' . $outbound->to_name : ''));

        return $this->withStockWarning(redirect()->route('outbound.index')->with('status', __('fuel.outbound_created')), $outbound->tank_id);
    }

    public function edit($id)
    {
        $outbound = $this->byHand($id);
        if ($outbound->isConfirmed()) {
            return redirect()->route('outbound.index')->withErrors(['outbound' => __('fuel.confirmed_locked')]);
        }

        $choices = $this->canSeeCosts() ? CostChoices::byTank() : null;
        if ($choices !== null && $outbound->cost !== null && isset($choices[$outbound->tank_id])) {
            $choices[$outbound->tank_id] = CostChoices::forTank($outbound->tank_id, $outbound->cost);      // the cost it has is listed even when the tank has none at it now
        }

        return view('outbound.form', ['outbound' => $outbound, 'tanks' => Tank::orderBy('name')->pluck('name', 'id'), 'costChoices' => $choices]);
    }

    public function update(Request $request, $id)
    {
        $outbound = $this->byHand($id);
        if ($outbound->isConfirmed()) {
            return redirect()->route('outbound.index')->withErrors(['outbound' => __('fuel.confirmed_locked')]);
        }
        $data = $this->validated($request);

        $before = '#' . $outbound->id . ' ' . $outbound->outbound_date . ', tank ' . $outbound->tank_id . ', ' . $outbound->amount_kg . ' kg';
        $outbound->forceFill($data)->save();

        Audit::log('outbound_update', $before . ' -> ' . $data['outbound_date'] . ', tank ' . $data['tank_id'] . ', ' . $data['amount_kg'] . ' kg');

        return $this->withStockWarning(redirect()->route('outbound.index')->with('status', __('fuel.outbound_saved')), $outbound->tank_id);
    }

    public function destroy($id)
    {
        $outbound = $this->byHand($id);
        if ($outbound->isConfirmed()) {
            return redirect()->route('outbound.index')->withErrors(['outbound' => __('fuel.confirmed_locked')]);
        }
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

    // The cost is the cost of the layer the fuel is taken from (a user who may see costs chooses it); with none, the oldest fuel goes first.
    // An outbound by hand may take more than the tank holds (the stock may be wrong, or an inbound not entered yet): it is saved and the staff are told, as for a tank
    // capacity below its stock.
    private function withStockWarning($response, $tankId)
    {
        $level = \App\Support\Dashboard\TankLevels::all()->first(function ($row) use ($tankId) {
            return $row->tank->id === (int) $tankId;
        });
        if ($level && $level->current < -0.004) {
            $response->with('warning', __('fuel.outbound_over_stock', ['tank' => $level->tank->name, 'stock' => number_format($level->current, 2)]));
        }

        return $response;
    }

    private function validated(Request $request)
    {
        $rules = [
            'tank_id' => 'required|integer|exists:tanks,id,deleted_at,NULL',
            'outbound_date' => 'required|date|before_or_equal:today',
            'amount_kg' => 'required|numeric|min:0.01|max:999999999',
            'to_name' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
        ];
        if ($this->canSeeCosts()) {
            $rules['cost'] = 'nullable|numeric|min:0|max:9999999';
        }
        $data = $request->validate($rules);
        if ($this->canSeeCosts()) {
            $data['cost'] = isset($data['cost']) && $data['cost'] !== '' ? $data['cost'] : null;
        }

        return $data;
    }
}
