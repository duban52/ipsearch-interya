<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikrotikRouter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'host', 'api_port', 'api_user',
        'api_password', 'use_ssl', 'verify_ssl', 'location',
        'municipality', 'is_active', 'priority', 'connect_timeout_ms',
        'read_timeout_ms', 'last_success_at', 'last_checked_at',
        'connectivity_status',
    ];

    protected $casts = [
        'use_ssl' => 'boolean',
        'verify_ssl' => 'boolean',
        'is_active' => 'boolean',
        'connect_timeout_ms' => 'integer',
        'read_timeout_ms' => 'integer',
        'priority' => 'integer',
        'last_success_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    protected $hidden = [
        'api_password',
    ];

    public function searches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MacSearchResult::class, 'router_id');
    }

    public function isReachable(): bool
    {
        return $this->connectivity_status === 'ok';
    }
}