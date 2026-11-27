<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MacSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'mac_normalized', 'mac_input', 'total_routers',
        'reachable_routers', 'result_count', 'status', 'duration_ms',
        'ip_address', 'request_ip', 'user_agent', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'total_routers' => 'integer',
        'reachable_routers' => 'integer',
        'result_count' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function results(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MacSearchResult::class);
    }
}