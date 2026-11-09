<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    /** Cached permission codes for the current request. */
    protected ?array $permissionCodes = null;

    protected $fillable = [
        'office_id',
        'role_id',
        'email',
        'username',
        'password_hash',
        'first_name',
        'middle_name',
        'last_name',
        'position_title',
        'phone_number',
        'is_active',
        'must_change_password',
    ];

    protected $hidden = [
        'password_hash',
        'totp_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password_hash'        => 'hashed',
            'is_active'            => 'boolean',
            'must_change_password' => 'boolean',
            'totp_enabled'         => 'boolean',
            'locked_until'         => 'datetime',
            'last_login_at'        => 'datetime',
            'password_changed_at'  => 'datetime',
        ];
    }

    /* ---- Tell Laravel your password column is not "password" ---- */

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /* ---- Your table has no remember_token column ---- */

    public function getRememberTokenName(): string
    {
        return '';
    }

    /* ---- Starter-kit views expect $user->name and initials() ---- */

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /* ---- Relationships ---- */

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /* ---- Roles & permissions ---- */

    public function isSuperAdmin(): bool
    {
        return $this->role?->code === 'super_admin';
    }

    public function hasPermission(string $code): bool
    {
        $this->permissionCodes ??= $this->role
            ? $this->role->permissions()->pluck('code')->all()
            : [];

        return in_array($code, $this->permissionCodes, true);
    }

    /**
     * Whether this user may edit / activate / deactivate the given account.
     * Super admins manage everyone. Other managers (e.g. IMO admins) can only
     * manage office-scoped accounts in their own office.
     */
    public function canManageUser(self $target): bool
    {
        if (! $this->hasPermission('user.manage')) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return (int) $target->office_id === (int) $this->office_id
            && $target->role?->data_scope === 'office';
    }
}
