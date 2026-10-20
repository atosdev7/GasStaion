<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\FuelType;
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
            'fuel_type' => ['type' => 'select', 'options' => config('fuel_types')],
            'price' => ['type' => 'number', 'step' => '0.01'],
            'density' => ['type' => 'number', 'step' => '0.0001', 'hint' => 'admin.density_hint'],
        ];
    }

    protected function rules($fuelType)
    {
        return [
            'fuel_type' => [
                'required', Rule::in(array_keys(config('fuel_types'))),
                Rule::unique('fuel_types', 'fuel_type')->ignore($fuelType->getKey(), 'fuel_type'),
            ],
            'price' => 'required|numeric|min:0|max:9999999',
            'density' => 'nullable|numeric|min:0.01|max:99',
        ];
    }
}
