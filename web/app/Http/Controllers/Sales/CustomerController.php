<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\CrudController;
use App\Models\Sales\Customer;
use App\Support\Debts\CustomerBalances;

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

    // A customer with a debt or a ticket loan that is not settled is not deleted: nobody could record the payment any more.
    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        if ($this->canModify($customer) && ($owes = CustomerBalances::blocker($customer->id))) {
            return redirect()->route('customers.index')->with('error', $owes);
        }

        return parent::destroy($id);
    }

    // The built-in "Individual" customer can never be changed or deleted.
    protected function canModify($customer)
    {
        return !$customer->is_default;
    }
}
