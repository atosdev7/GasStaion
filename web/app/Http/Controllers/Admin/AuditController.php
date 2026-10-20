<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

// The audit log: what the app writes with App\Support\Audit (one file a day, storage/logs/audit-YYYY-MM-DD.log, kept about 90 days). A line is
// "time - who - ACTION - detail". Read-only: the user picks a period (the last 7 days at first) and may narrow it to one user or one action.
class AuditController extends Controller
{
    const PER_PAGE = 100;
    const MAX_DAYS = 92;

    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'user' => ['nullable', 'string', 'max:60'],
            'action' => ['nullable', 'string', 'max:60'],
        ]);

        $to = Carbon::parse($data['to'] ?? today()->toDateString());
        $from = Carbon::parse($data['from'] ?? $to->copy()->subDays(6)->toDateString());
        if ($from->gt($to)) {
            $from = $to->copy();
        }
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->copy()->subDays(self::MAX_DAYS);
        }

        $user = trim($data['user'] ?? '');
        $action = trim($data['action'] ?? '');

        $entries = collect(self::read($from, $to))->filter(function ($e) use ($user, $action) {
            return ($user === '' || stripos($e->who, $user) !== false) && ($action === '' || stripos($e->action, $action) !== false);
        })->reverse()->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $entries->forPage($page, self::PER_PAGE)->values(), $entries->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('audit.index', [
            'entries' => $paginator,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'user' => $user,
            'action' => $action,
        ]);
    }

    /** The entries of the days from..to, oldest first. @return object[] with at, who, action, detail */
    public static function read(Carbon $from, Carbon $to)
    {
        $entries = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $file = storage_path('logs/audit-' . $day->toDateString() . '.log');
            if (!is_file($file)) {
                continue;
            }
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $parts = explode(' - ', $line, 4);
                if (count($parts) < 3) {
                    continue;   // not an audit line
                }
                $entries[] = (object) ['at' => $parts[0], 'who' => $parts[1], 'action' => $parts[2], 'detail' => $parts[3] ?? ''];
            }
        }

        return $entries;
    }
}
