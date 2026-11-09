<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    // audit_logs only has created_at, and the database fills it in.
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id', 'office_id', 'action', 'entity_type', 'entity_id', 'field_name',
        'old_value', 'new_value', 'description', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Write one audit line for the signed-in user. */
    public static function record(string $action, string $entityType, ?int $entityId, ?string $description = null, array $extra = []): self
    {
        $user = Auth::user();
        $request = app()->bound('request') ? request() : null;

        return static::create($extra + [
            'user_id' => $user?->getAuthIdentifier(),
            'office_id' => $user?->office_id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description !== null ? mb_substr($description, 0, 255) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
