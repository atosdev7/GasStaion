<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Device;
use App\Models\Nozzle;
use App\Models\Tank;
use Illuminate\Validation\Rule;

// The nozzles of the station: which device (Smart210) runs the nozzle, its number on that device, its fuel type, the tank it draws from (which holds
// that fuel type) and the address of its board. The Smart210 also reports its nozzles (see App\Support\Push; it names the tank, the fuel type is
// the tank's), so a nozzle set up there appears here too.
class NozzleController extends CrudController
{
    protected function model() { return Nozzle::class; }
    protected function route() { return 'nozzles'; }
    protected function perm() { return 'fuel'; }
    protected function title() { return 'nozzles'; }
    protected function langFile() { return 'admin'; }

    protected function fields()
    {
        $fuels = config('fuel_types');

        return [
            'device_id' => ['type' => 'select', 'options' => Device::orderBy('name')->pluck('name', 'id')->all()],
            'number' => ['type' => 'number', 'step' => '1'],
            'fuel_type' => ['type' => 'select', 'options' => $fuels],
            // a tank is named with its fuel type; `fuels` (tank => fuel type) is for the script of the form, which keeps the two selects together
            'tank_id' => ['type' => 'select', 'options' => Tank::orderBy('name')->get()->mapWithKeys(function ($tank) use ($fuels) {
                return [$tank->id => $tank->name . ' (' . ($fuels[$tank->fuel_type] ?? $tank->fuel_type) . ')'];
            })->all(), 'fuels' => Tank::pluck('fuel_type', 'id')->all()],
            'board_address' => ['type' => 'number', 'step' => '1', 'hint' => 'admin.board_address_hint'],
        ];
    }

    protected function rules($nozzle)
    {
        return [
            'device_id' => ['required', 'integer', Rule::exists('devices', 'id')->whereNull('deleted_at')],
            // a number is used once on a device, also by a nozzle that was deleted (it keeps its number: restore it)
            'number' => ['required', 'integer', 'min:1', 'max:255',
                Rule::unique('nozzles', 'number')->where('device_id', request('device_id'))->ignore($nozzle->getKey())],
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            // the tank holds the fuel type of the nozzle
            'tank_id' => ['required', 'integer', Rule::exists('tanks', 'id')->whereNull('deleted_at'), function ($attribute, $value, $fail) {
                $tank = Tank::find($value);
                if ($tank && $tank->fuel_type !== request('fuel_type')) {
                    $fail(__('admin.nozzle_fuel_mismatch'));
                }
            }],
            'board_address' => 'nullable|integer|min:0|max:255',
        ];
    }

    protected function messages()
    {
        return ['number.unique' => __('admin.nozzle_number_taken')];
    }

    protected function prepare(array $data, $nozzle)
    {
        $data['board_address'] = $data['board_address'] ?? null;

        return $data;
    }
}
