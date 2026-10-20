<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Price/density settings per fuel type. Fuel type identity (letter key + display name) lives in
// config/fuel_types.php, not here — this table only holds the two things an admin actually sets.
// Keeps SoftDeletes for consistency with every other master-data screen (same delete/restore UI
// via CrudController), even though nothing references this row by foreign key any more (other
// tables store the config fuel-type key directly, validated against config, not against this table).
class FuelType extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'fuel_type';
    protected $keyType = 'string';
    public $incrementing = false;
}
