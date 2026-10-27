<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Fuel\FuelType;
use App\Models\Fuel\Nozzle;
use App\Models\Fuel\Tank;
use Illuminate\Validation\Rule;

class FuelTypeController extends CrudController
{
    protected function model() { return FuelType::class; }
    protected function route() { return 'fuel'; }
    protected function perm() { return 'fuel'; }
    protected function title() { return 'fuel'; }
    protected function langFile() { return 'admin'; }

    protected function fields()
    {
        return [
            'fuel_type' => ['type' => 'select', 'options' => config('fuel_types'), 'locked_on_edit' => true],
            'price' => ['type' => 'number', 'step' => '0.01'],
            'density' => ['type' => 'number', 'step' => '0.0001', 'hint' => 'admin.density_hint'],
        ];
    }

    // A fuel type that a tank or a nozzle uses is not deleted: its price and density are what the sale forms and the terminals read.
    public function destroy($id)
    {
        if (Tank::where('fuel_type', $id)->exists() || Nozzle::where('fuel_type', $id)->exists()) {
            return redirect()->route('fuel.index')->with('error', __('admin.fuel_in_use', ['fuel' => config('fuel_types.' . $id, $id)]));
        }

        return parent::destroy($id);
    }

    protected function rules($fuelType)
    {
        return [
            // the key is what every other table stores: it does not change once the row exists
            'fuel_type' => [
                'required', Rule::in(array_keys(config('fuel_types'))),
                Rule::unique('fuel_types', 'fuel_type')->ignore($fuelType->getKey(), 'fuel_type'),
                function ($attribute, $value, $fail) use ($fuelType) {
                    if ($fuelType->exists && $value !== $fuelType->getKey()) {
                        $fail(__('admin.fuel_key_locked'));
                    }
                },
            ],
            'price' => 'required|numeric|min:0|max:9999999',
            'density' => 'nullable|numeric|min:0.01|max:99',
        ];
    }
}
