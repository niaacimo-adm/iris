<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Permission extends Model
{
    /**
     * Codes the application itself checks. Their code cannot be changed and
     * they cannot be deleted (the description can still be edited).
     */
    public const PROTECTED_CODES = [
        'user.view',
        'user.manage',
        'role.view',
        'role.manage',
        'permission.view',
        'permission.manage',
    ];

    // The permissions table has no created_at / updated_at.
    public $timestamps = false;

    protected $table = 'permissions';

    protected $fillable = [
        'code',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'permission_id',
            'role_id'
        );
    }

    public function isProtected(): bool
    {
        return in_array($this->code, self::PROTECTED_CODES, true);
    }

    /** "user.manage" => "user". Codes without a dot fall into "general". */
    public function module(): string
    {
        return self::moduleOf($this->code);
    }

    public static function moduleOf(string $code): string
    {
        return str_contains($code, '.') ? Str::before($code, '.') : 'general';
    }
}
