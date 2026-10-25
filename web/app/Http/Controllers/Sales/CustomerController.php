<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\CrudController;
use App\Models\Sales\Customer;

class CustomerController extends CrudController
{
    protected function model() { return Customer::class; }
    protected function route() { return 'customers'; }
    protected function perm() { return 'customers'; }
    protected function title() { return 'customers'; }
    protected function langFile() { return 'sales'; }

    protected function fields()
    {
        return [
            'name' => ['type' => 'text'],
            'phone' => ['type' => 'text'],
        ];
    }

    protected function rules($customer)
    {
        return [
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
        ];
    }

    protected function prepare(array $data, $customer)
    {
        $data['synced'] = false;

        return $data;
    }

    // The built-in "Individual" customer can never be changed or deleted.
    protected function canModify($customer)
    {
        return !$customer->is_default;
    }
}
