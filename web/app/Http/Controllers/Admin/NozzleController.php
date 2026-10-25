<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Admin\Device;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;

// The nozzles of the station, listed only: they are added, changed and removed on the Smart210 (Machine Config), which reports them (see
// App\Support\Sync\Push; it names the tank, the fuel type is the tank's). Nothing can be entered here.
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
            'board_address' => ['type' => 'number', 'step' => '1'],
        ];
    }

    protected function rules($nozzle)
    {
        return []; // nothing is entered here
    }

    protected function readOnly() { return true; }
}
