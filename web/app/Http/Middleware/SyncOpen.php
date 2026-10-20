<?php

namespace App\Http\Middleware;

use App\Support\Api;
use App\Support\BackupRun;
use Closure;

// While a backup exports and clears, sync waits: the Smart210 keeps its data and asks again shortly.
class SyncOpen
{
    public function handle($request, Closure $next)
    {
        if (BackupRun::isRunning()) {
            return Api::response(['status' => 'busy', 'message' => 'a backup is running, try again shortly'], 503)
                ->header('Retry-After', 30);
        }

        return $next($request);
    }
}
