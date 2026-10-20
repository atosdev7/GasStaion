<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

// For models whose primary key is a randomly generated CHAR(36) uuid, not an auto-increment int —
// needed so Smart210 can create these rows offline (before sync) without an id collision.
trait HasUuidPrimaryKey
{
    protected static function bootHasUuidPrimaryKey()
    {
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = $model->{$model->getKeyName()} ?: (string) Str::uuid();
        });
    }

    public function initializeHasUuidPrimaryKey()
    {
        $this->keyType = 'string';
        $this->incrementing = false;
    }
}
