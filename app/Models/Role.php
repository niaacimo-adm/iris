<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /**
     * Allowed values for roles.data_scope  =>  label shown in the UI.
     * Adjust the keys if your database uses different values.
     */
    public const DATA_SCOPES = [
        'office' => 'Office only',
        'region' => 'Region-wide',
    ];

    /** This role always holds every permission and can never be deleted. */
    public const SUPER_ADMIN_CODE = 'super_admin';

    // The roles table has no created_at / updated_at.
    public $timestamps = false;

    protected $table = 'roles';

    protected $fillable = [
        'code',
        'name',
        'description',
        'data_scope',
        'require_otp_every_login',
    ];

    protected function casts(): array
    {
        return [
            'require_otp_every_login' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Protected roles cannot be deleted, re-coded or have permissions removed. */
    public function isProtected(): bool
    {
        return $this->code === self::SUPER_ADMIN_CODE;
    }
}
