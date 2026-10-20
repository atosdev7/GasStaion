<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

// A row a Smart210 pushed that the server rejected, or a ticket change that conflicted with what the web knew; kept so
// staff can review it. The Smart210 retries a rejected row on every sync, so an identical open error is counted, not repeated.
class SyncError extends Model
{
    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }

    public static function record($deviceId, $entity, $recordId, $payload, $reason)
    {
        $reason = substr($reason, 0, 255);
        $recordId = $recordId === null ? null : substr($recordId, 0, 40);

        $open = static::whereNull('resolved_at')->where('device_id', $deviceId)->where('entity', $entity)->where('reason', $reason);
        $recordId === null ? $open->whereNull('record_id') : $open->where('record_id', $recordId);

        $error = $open->first();
        if ($error) {
            $error->forceFill(['occurrences' => $error->occurrences + 1, 'payload' => $payload])->save();

            return $error;
        }

        return static::forceCreate([
            'device_id' => $deviceId, 'entity' => $entity, 'record_id' => $recordId, 'payload' => $payload, 'reason' => $reason,
        ]);
    }
}
