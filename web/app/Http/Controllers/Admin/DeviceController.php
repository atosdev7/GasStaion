<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Device;
use App\Support\DeviceHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeviceController extends CrudController
{
    protected function model() { return Device::class; }
    protected function route() { return 'devices'; }
    protected function perm() { return 'devices'; }
    protected function title() { return 'devices'; }
    protected function langFile() { return 'admin'; }

    protected function fields()
    {
        return [
            'name' => ['type' => 'text'],
            'ip_address' => ['type' => 'text', 'hint' => 'admin.ip_address_hint'],
            'purge_days' => ['type' => 'number', 'step' => '1', 'hint' => 'admin.purge_days_hint'],
            'pin' => ['type' => 'password', 'hint' => 'admin.pin_hint'],
        ];
    }

    protected function rules($device)
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('devices', 'name')->ignore($device->getKey())],
            // Two devices with one address could not be told apart; a deleted device does not count.
            'ip_address' => ['required', 'ip', Rule::unique('devices', 'ip_address')->whereNull('deleted_at')->ignore($device->getKey())],
            'purge_days' => 'required|integer|min:1|max:365',
            'pin' => 'nullable|digits_between:4,8',
        ];
    }

    protected function prepare(array $data, $device)
    {
        $pin = $data['pin'] ?? null;
        unset($data['pin']);

        if ($pin) {
            $data = $this->pinColumns($data, $pin);
        } elseif (!$device->exists) {
            // New device: start from the default PIN copied from settings.
            $s = DB::table('settings')->whereIn('key', ['default_device_pin_salt', 'default_device_pin_hash'])->pluck('value', 'key');
            $data['pin_salt'] = $s['default_device_pin_salt'] ?? null;
            $data['pin_hash'] = $s['default_device_pin_hash'] ?? null;
        }

        return $data;
    }

    private function pinColumns(array $data, $pin)
    {
        $salt = bin2hex(random_bytes(8));
        $data['pin_salt'] = $salt;
        $data['pin_hash'] = DeviceHash::make($salt, $pin);

        return $data;
    }
}
