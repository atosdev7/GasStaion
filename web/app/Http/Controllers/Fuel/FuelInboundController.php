<?php

namespace App\Http\Controllers\Fuel;

use App\Http\Controllers\Controller;
use App\Models\FuelInbound;
use App\Models\Tank;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FuelInboundController extends Controller
{
    public function index()
    {
        return view('inbound.index', [
            'rows' => FuelInbound::orderByDesc('inbound_date')->orderByDesc('id')->get(),
            'tanks' => Tank::pluck('name', 'id'),
            'fuels' => config('fuel_types'),
        ]);
    }

    public function create()
    {
        return view('inbound.create', [
            'tanks' => Tank::orderBy('name')->pluck('name', 'id'), // deleted tanks are excluded automatically
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'inbound_date' => 'required|date',
            'amount_kg' => 'required|numeric|min:0.01|max:999999999',
            'cost' => 'required|numeric|min:0|max:999999',
            'is_default' => 'nullable|boolean',
            'note' => 'nullable|string|max:255',
        ]);

        $tank = Tank::findOrFail($data['tank_id']);

        $inbound = DB::transaction(function () use ($data, $tank, $request) {
            // The first inbound of a fuel type becomes its default automatically.
            $hasDefault = FuelInbound::where('fuel_type', $tank->fuel_type)->where('is_default', true)->exists();
            $makeDefault = $request->boolean('is_default') || !$hasDefault;

            $inbound = new FuelInbound;
            $inbound->forceFill([
                'tank_id' => $tank->id,
                'fuel_type' => $tank->fuel_type,
                'amount_kg' => $data['amount_kg'],
                'cost' => $data['cost'],
                'is_default' => false,
                'inbound_date' => $data['inbound_date'],
                'note' => $data['note'] ?? null,
                'user_id' => auth()->id(),
            ]);
            if ($makeDefault) {
                $inbound->becomeDefault();
            }
            $inbound->save();

            return $inbound;
        });

        Audit::log('inbound_create', '#' . $inbound->id . ' tank ' . $tank->name . ', ' . $inbound->amount_kg . ' kg, cost ' . $inbound->cost
            . ($inbound->is_default ? ', default' : ''));

        return redirect()->route('inbound.index')->with('status', __('fuel.created'));
    }

    public function edit($id)
    {
        $inbound = FuelInbound::findOrFail($id);

        return view('inbound.edit', [
            'inbound' => $inbound,
            // the fuel type of a record does not change: only the tanks that hold it are offered
            'tanks' => Tank::where('fuel_type', $inbound->fuel_type)->orderBy('name')->pluck('name', 'id'),
            'fuels' => config('fuel_types'),
        ]);
    }

    // Changes a record: date, tank (of the same fuel type), amount, cost, note. The tank stock follows because it is worked out from the
    // records; sales already made keep the cost they were made with. Which record is the default is changed with "make default".
    public function update(Request $request, $id)
    {
        $inbound = FuelInbound::findOrFail($id);
        $data = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'inbound_date' => 'required|date',
            'amount_kg' => 'required|numeric|min:0.01|max:999999999',
            'cost' => 'required|numeric|min:0|max:999999',
            'note' => 'nullable|string|max:255',
        ]);
        $tank = Tank::findOrFail($data['tank_id']);
        if ($tank->fuel_type !== $inbound->fuel_type) {
            return back()->withInput()->withErrors(['tank_id' => __('fuel.inbound_tank_fuel')]);
        }

        $before = '#' . $inbound->id . ' ' . $inbound->inbound_date . ', tank ' . $inbound->tank_id . ', ' . $inbound->amount_kg . ' kg, cost ' . $inbound->cost;
        $inbound->forceFill([
            'tank_id' => $tank->id,
            'inbound_date' => $data['inbound_date'],
            'amount_kg' => $data['amount_kg'],
            'cost' => $data['cost'],
            'note' => $data['note'] ?? null,
        ])->save();

        Audit::log('inbound_update', $before . ' -> ' . $data['inbound_date'] . ', tank ' . $tank->id . ', ' . $data['amount_kg'] . ' kg, cost ' . $data['cost']);

        return redirect()->route('inbound.index')->with('status', __('fuel.saved'));
    }

    // Removes a wrong record (it is kept as deleted). The default of a fuel type is not deleted while other records of that fuel type exist:
    // another one is made the default first.
    public function destroy($id)
    {
        $inbound = FuelInbound::findOrFail($id);

        if ($inbound->is_default && FuelInbound::where('fuel_type', $inbound->fuel_type)->where('id', '<>', $inbound->id)->exists()) {
            return redirect()->route('inbound.index')->withErrors(['inbound' => __('fuel.inbound_default_delete')]);
        }

        $inbound->delete();
        Audit::log('inbound_delete', '#' . $inbound->id . ' ' . $inbound->inbound_date . ', tank ' . $inbound->tank_id . ', ' . $inbound->amount_kg . ' kg');

        return redirect()->route('inbound.index')->with('status', __('fuel.deleted'));
    }

    // Make this inbound's cost the default for its fuel type (only one default per fuel type).
    public function makeDefault($id)
    {
        $inbound = FuelInbound::findOrFail($id);

        DB::transaction(function () use ($inbound) {
            if (!$inbound->is_default) {
                $inbound->becomeDefault();
                $inbound->save();
            }
        });

        Audit::log('inbound_default', '#' . $inbound->id . ' cost ' . $inbound->cost);

        return redirect()->route('inbound.index')->with('status', __('fuel.saved'));
    }
}
