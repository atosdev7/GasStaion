<?php

namespace App\Models\Admin;

use App\Models\Concerns\ResetsSynced;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

// The only model that still has `active` — every other model relies on soft delete alone.
class User extends Authenticatable
{
    use ResetsSynced, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'username', 'password', 'role', 'pos_salt', 'pos_hash', 'active',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'pos_salt', 'pos_hash',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Role permissions come from config/permissions.php; "x.manage" implies "x.view".
     */
    public function hasPermission($permission)
    {
        if (!$this->active) {
            return false;
        }

        $granted = config('permissions.roles.' . $this->role, []);
        if (in_array('*', $granted, true) || in_array($permission, $granted, true)) {
            return true;
        }

        if (substr($permission, -5) === '.view') {
            return in_array(substr($permission, 0, -5) . '.manage', $granted, true);
        }

        return false;
    }
}
