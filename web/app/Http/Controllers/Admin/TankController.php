<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Fuel\FuelInbound;
use App\Models\Fuel\FuelOutbound;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\TankBalance;
use App\Models\Fuel\Tank;
use App\Support\Dashboard\TankLevels;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TankController extends CrudController
{
    protected function model() { return Tank::class; }
    protected function route() { return 'tanks'; }
    protected function perm() { return 'fuel'; }
    protected function title() { return 'tanks'; }
    protected function langFile() { return 'admin'; }

    protected function fields()
    {
        return [
            'name' => ['type' => 'text'],
            'fuel_type' => ['type' => 'select', 'options' => config('fuel_types')],
            'capacity_kg' => ['type' => 'number', 'step' => '0.01'],
        ];
    }

    // A capacity below what the tank holds is allowed (it may be a typing mistake, or the stock may be wrong): the save goes through with a warning.
    public function store(Request $request)
    {
        $response = parent::store($request);

        return $this->withCapacityWarning($response, Tank::where('name', $request->input('name'))->first());
    }

    public function update(Request $request, $id)
    {
        $response = parent::update($request, $id);

        return $this->withCapacityWarning($response, Tank::find($id));  // the tank that was changed, not the one that has the name typed now
    }

    private function withCapacityWarning($response, $tank)
    {
        $capacity = request()->input('capacity_kg');
        if (!$tank || $capacity === null || $capacity === '') {
            return $response;
        }

        $level = TankLevels::all()->first(function ($row) use ($tank) {
            return $row->tank->id === $tank->id;
        });
        if ($level && $level->current > (float) $capacity) {
            $response->with('warning', __('admin.tank_capacity_below_stock', [
                'tank' => $tank->name, 'capacity' => number_format((float) $capacity, 2), 'stock' => number_format($level->current, 2),
            ]));
        }

        return $response;
    }

    // A tank that a nozzle draws from cannot be deleted (the nozzle's fuel type is the tank's): the nozzles have to be removed on the Smart210 first.
    public function destroy($id)
    {
        $tank = Tank::findOrFail($id);
        $numbers = Nozzle::where('tank_id', $tank->id)->orderBy('device_id')->orderBy('number')->pluck('number')->all();
        if ($numbers) {
            return redirect()->route('tanks.index')->with('error', __('admin.tank_has_nozzles', ['tank' => $tank->name, 'nozzles' => implode(', ', $numbers)]));
        }

        if ($this->hasRecords($tank->id)) {
            return redirect()->route('tanks.index')->with('error', __('admin.tank_has_records', ['tank' => $tank->name]));
        }

        return parent::destroy($id);
    }

    // Does the tank have a balance, a fuel inbound or a fuel outbound (a supply's included)? Their fuel type and their stock are the tank's, so such a tank is neither
    // deleted nor given another fuel type.
    private function hasRecords($tankId)
    {
        return TankBalance::where('tank_id', $tankId)->exists() || FuelInbound::where('tank_id', $tankId)->exists() || FuelOutbound::where('tank_id', $tankId)->exists();
    }

    protected function rules($tank)
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('tanks', 'name')->ignore($tank->getKey())],
            // a nozzle's fuel type is its tank's: while nozzles draw from the tank, only its name and capacity can change
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types'))), function ($attribute, $value, $fail) use ($tank) {
                if ($tank->exists && $value !== $tank->fuel_type) {
                    $numbers = Nozzle::where('tank_id', $tank->getKey())->orderBy('device_id')->orderBy('number')->pluck('number')->all();
                    if ($numbers) {
                        $fail(__('admin.tank_fuel_locked', ['nozzles' => implode(', ', $numbers)]));
                    } elseif ($this->hasRecords($tank->getKey())) {
                        $fail(__('admin.tank_fuel_has_records'));
                    }
                }
            }],
            'capacity_kg' => 'nullable|numeric|min:0|max:99999999',
        ];
    }
}
