<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\DeviceHash;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

/**
 * Username + password login (the system has no email).
 */
class SessionController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function username()
    {
        return 'username';
    }

    protected function credentials(Request $request)
    {
        return $request->only('username', 'password') + ['active' => true];
    }

    // The password was just verified, so an old SHA-1 Smart210 hash can be replaced by a SHA-256 one.
    protected function authenticated(Request $request, $user)
    {
        DeviceHash::upgradeUser($user, $request->input('password'));
    }
}
