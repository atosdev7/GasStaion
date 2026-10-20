<?php

namespace App\Models\Concerns;

// For rows the Smart210 pulls: any change made on the web puts the row back to synced = false, so the next pull sends it
// again. Covers save() (including restore) and soft delete. Bulk updates and raw inserts bypass model events and must
// set `synced` themselves. A change that came from the Smart210 itself is saved with saveFromDevice() and is not sent back.
trait ResetsSynced
{
    protected $savedFromDevice = false;

    // Columns whose change means nothing to the Smart210 (login bookkeeping); a model may override this.
    public function syncIgnoredColumns()
    {
        return ['remember_token'];
    }

    public function isSavedFromDevice()
    {
        return $this->savedFromDevice;
    }

    // Saves attributes the Smart210 sent. The caller passes 'synced' => true when the table has that column.
    public function saveFromDevice(array $attributes = [])
    {
        $this->savedFromDevice = true;

        try {
            return $this->forceFill($attributes)->save();
        } finally {
            $this->savedFromDevice = false;
        }
    }

    public static function bootResetsSynced()
    {
        // Written straight to the row: an acked row is true in the table, but a model loaded before the ack still says
        // false, and assigning false again would not count as a change.
        static::saved(function ($model) {
            if ($model->isSavedFromDevice()) {
                return;
            }

            $ignored = array_merge(['updated_at', 'synced'], $model->syncIgnoredColumns());
            if (array_diff(array_keys($model->getChanges()), $ignored)) {
                $model->newQueryWithoutScopes()->whereKey($model->getKey())->toBase()->update(['synced' => false]);
            }
        });

        // A soft delete is a bare UPDATE of deleted_at, which fires no saved event.
        static::deleted(function ($model) {
            if (method_exists($model, 'trashed') && $model->trashed()) {
                $model->newQueryWithoutScopes()->whereKey($model->getKey())->toBase()->update(['synced' => false]);
            }
        });
    }
}
