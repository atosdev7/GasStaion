<?php

namespace App\Http\Middleware;

use Closure;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $locale = session('locale');
        if ($locale && array_key_exists($locale, config('locales'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
