<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Tank;
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

    protected function rules($tank)
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('tanks', 'name')->ignore($tank->getKey())],
            'fuel_type' => ['required', Rule::in(array_keys(config('fuel_types')))],
            'capacity_kg' => 'nullable|numeric|min:0|max:99999999',
        ];
    }
}
