<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Device;
use App\Models\Admin\SyncError;
use App\Models\Admin\User;
use App\Support\Admin\Audit;
use Illuminate\Http\Request;

// Sync Status: when each Smart210 last synced, and the rows the server rejected or found in conflict.
class SyncController extends Controller
{
    public function index(Request $request)
    {
        $all = $request->boolean('all');

        $errors = SyncError::with('device')->orderByDesc('updated_at')->orderByDesc('id');
        if (!$all) {
            $errors->whereNull('resolved_at');
        }

        return view('sync.index', [
            'devices' => Device::orderBy('name')->get(),
            'errors' => $errors->paginate(50)->appends($all ? ['all' => 1] : []),
            'openCount' => SyncError::whereNull('resolved_at')->count(),
            'all' => $all,
            'staleAfter' => now()->subHours(config('sync.stale_hours')),
            'users' => User::withTrashed()->pluck('name', 'id'),
        ]);
    }

    public function resolve(Request $request, $id)
    {
        $error = SyncError::whereNull('resolved_at')->findOrFail($id);
        $error->forceFill(['resolved_at' => now(), 'resolved_by' => auth()->id()])->save();

        Audit::log('sync_error_resolve', '#' . $error->id . ' ' . $error->entity . ' ' . $error->record_id . ': ' . $error->reason);

        return redirect()->route('sync.index', $request->boolean('all') ? ['all' => 1] : [])->with('status', __('sync.resolved'));
    }
}
