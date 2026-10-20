<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncError;
use App\Support\Api;
use App\Support\Push;
use App\Support\RejectedRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// What the Smart210 pushes to the web app: a batch of rows of one table. Each row is stored or rejected on its own;
// the reply lists both, so the Smart210 marks the saved ones synced and keeps the rest. A row that fails for another reason than a
// refusal (a database error) is reported as refused too, so one such row cannot block the others.
class PushController extends Controller
{
    const MAX_ROWS = 200;

    public function store(Request $request, $table)
    {
        $device = $request->attributes->get('device');
        $device->last_synced_at = now();
        $device->save();

        $validator = Validator::make($request->all(), [
            'rows' => 'required|array|max:' . self::MAX_ROWS,
            'rows.*' => 'array',
        ]);
        if ($validator->fails()) {
            return Api::error($validator->errors()->first(), 422);
        }

        $saved = [];
        $rejected = [];
        foreach ($request->input('rows') as $row) {
            $key = Push::keyOf($table, $row);

            try {
                Push::apply($table, $device, $row);
                $saved[] = $key;
            } catch (RejectedRow $e) {
                $rejected[] = ['key' => $key, 'error' => $e->getMessage()];
                SyncError::record($device->id, $table, $key, json_encode($row), $e->getMessage());
            } catch (\Throwable $e) {
                // A database error or a lock timeout on one row must not fail the whole batch (the rows before it are stored and the
                // device would send them all again): this row is reported as refused, the terminal keeps it and sends it again later.
                report($e);
                $message = 'the server could not store this row: ' . $e->getMessage();
                $rejected[] = ['key' => $key, 'error' => substr($message, 0, 255)];
                SyncError::record($device->id, $table, $key, json_encode($row), $message);
            }
        }

        return Api::response([
            'status' => 'ok',
            'server_time' => now()->format('Y-m-d H:i:s'),
            'table' => $table,
            'saved' => $saved,
            'rejected' => $rejected,
        ]);
    }
}
