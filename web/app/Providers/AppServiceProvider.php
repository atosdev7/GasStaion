<?php

namespace App\Providers;

use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen(Login::class, function ($e) {
            Audit::log('login', request()->ip(), $e->user->username);
        });
        Event::listen(Logout::class, function ($e) {
            if ($e->user) {
                Audit::log('logout', request()->ip(), $e->user->username);
            }
        });
        Event::listen(Failed::class, function ($e) {
            Audit::log('login_failed', request()->ip(), $e->credentials['username'] ?? 'unknown');
        });
    }
}
