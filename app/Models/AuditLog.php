<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * La tabla audit_logs solo define created_at (registro inmutable).
     * Sin esto, Eloquent intentaría escribir updated_at y fallaría.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_label', 'action', 'description',
        'ip_address', 'result', 'operation_id', 'context',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(
        string $action,
        ?User $user = null,
        string $description = '',
        ?string $ipAddress = null,
        string $result = 'success',
        ?string $operationId = null,
        array $context = []
    ): self {
        return self::create([
            'user_id' => $user?->id,
            'user_label' => $user?->fullName() ?? $user?->name ?? 'sistema',
            'action' => $action,
            'description' => $description,
            'ip_address' => $ipAddress,
            'result' => $result,
            'operation_id' => $operationId,
            'context' => $context,
            'created_at' => now(),
        ]);
    }
}