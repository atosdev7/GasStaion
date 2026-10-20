<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Admin\User;
use App\Support\Admin\DeviceHash;
use Illuminate\Validation\Rule;

class UserController extends CrudController
{
    protected function model() { return User::class; }
    protected function route() { return 'users'; }
    protected function perm() { return 'users'; }
    protected function title() { return 'users'; }
    protected function langFile() { return 'admin'; }

    protected function fields()
    {
        $roles = [];
        foreach (config('roles') as $role) {
            $roles[$role] = __('roles.' . $role);
        }

        return [
            'name' => ['type' => 'text'],
            'username' => ['type' => 'text'],
            'role' => ['type' => 'select', 'options' => $roles],
            'password' => ['type' => 'password', 'hint' => 'admin.password_hint'],
        ];
    }

    protected function rules($user)
    {
        return [
            'name' => 'required|string|max:100',
            'username' => ['required', 'alpha_dash', 'max:50', Rule::unique('users', 'username')->ignore($user->getKey())],
            'role' => ['required', Rule::in(config('roles'))],
            'password' => [$user->exists ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
        ];
    }

    protected function prepare(array $data, $user)
    {
        if (!empty($data['password'])) {
            // One password for the web login and the Smart210 login (salted SHA-256 for the Smart210).
            $salt = bin2hex(random_bytes(8));
            $data['pos_salt'] = $salt;
            $data['pos_hash'] = DeviceHash::make($salt, $data['password']);
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $data['synced'] = false; // pushed to the Smart210 on the next sync

        return $data;
    }

    // The only resource with an activate/deactivate toggle.
    protected function hasActiveToggle()
    {
        return true;
    }

    // Nobody can lock themselves out.
    protected function canModify($user)
    {
        return $user->getKey() !== auth()->id();
    }
}
