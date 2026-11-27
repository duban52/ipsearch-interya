<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MacSearchResult extends Model
{
    use HasFactory;

    /**
     * Etiquetas en español de los mecanismos de asignación.
     */
    public const MECHANISM_LABELS = [
        'pppoe_active' => 'PPPoE activo',
        'dhcp_active' => 'DHCP activo',
        'dhcp_static' => 'DHCP estático',
        'arp_complete' => 'ARP',
        'hotspot_binding' => 'Hotspot binding',
    ];

    protected $fillable = [
        'mac_search_id', 'router_id', 'router_name', 'municipality', 'ip_address',
        'mac_address', 'mechanism', 'mechanisms', 'status_label', 'hostname',
        'server', 'interface', 'comment', 'is_active_match',
        'response_ms', 'raw',
    ];

    protected $casts = [
        'is_active_match' => 'boolean',
        'response_ms' => 'integer',
        'raw' => 'array',
        'mechanisms' => 'array',
    ];

    public static function mechanismLabel(string $mechanism): string
    {
        return self::MECHANISM_LABELS[$mechanism] ?? $mechanism;
    }

    /**
     * Mecanismos que confirmaron esta IP, con el principal primero.
     *
     * @return array<int,string>
     */
    public function mechanismLabels(): array
    {
        $mechanisms = $this->mechanisms ?: [$this->mechanism];

        // El mecanismo principal siempre primero, sin duplicados.
        $mechanisms = array_values(array_unique(array_merge([$this->mechanism], $mechanisms)));

        return array_map(fn (string $m) => self::mechanismLabel($m), $mechanisms);
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(MacSearch::class, 'mac_search_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'router_id');
    }
}