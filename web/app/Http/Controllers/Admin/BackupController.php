<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Backup;
use App\Models\Fuel\Tank;
use App\Support\Admin\Audit;
use App\Support\Admin\BackupRun;
use Illuminate\Http\Request;
use RuntimeException;

class BackupController extends Controller
{
    public function index(Request $request)
    {
        $upTo = null;
        if ($request->filled('up_to')) {
            $upTo = $request->validate(['up_to' => 'date|before:today'])['up_to'];
        }

        return view('backup.index', [
            'backups' => Backup::orderByDesc('id')->get(),
            'upTo' => $upTo,
            'preview' => $upTo ? BackupRun::preview($upTo) : null,
            'carry' => $upTo ? BackupRun::tankCarryForward($upTo) : null,
            'tanks' => Tank::withTrashed()->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        // Up to yesterday at most: today's records may still be changed.
        $upTo = $request->validate(['up_to' => 'required|date|before:today'])['up_to'];

        try {
            $backup = BackupRun::run($upTo, auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->route('backup.index')->withErrors(['up_to' => $e->getMessage()]);
        }

        Audit::log('backup', 'up to ' . $upTo . ', ' . $backup->file . ', cleared ' . json_encode($backup->cleared));

        return redirect()->route('backup.index')->with('status', __('admin.backup_done', ['file' => $backup->file]));
    }

    public function download($id)
    {
        $backup = Backup::findOrFail($id);
        $path = BackupRun::path($backup->file);
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }
}
