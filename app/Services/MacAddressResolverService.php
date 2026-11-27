<?php

namespace App\Services;

use App\Exceptions\SearchUnauthorizedException;
use App\Models\AuditLog;
use App\Models\MacSearch;
use App\Models\MacSearchResult;
use App\Models\MikrotikRouter;
use App\Models\User;
use App\Services\Mikrotik\MikrotikException;
use App\Services\Mikrotik\MikrotikRestClient;
use Illuminate\Support\Facades\Log;

class MacAddressResolverService
{
    public function __construct(private readonly MikrotikRestClient $mikrotik) {}

    /**
     * Resuelve una MAC en todos los routers activos.
     *
     * @throws SearchUnauthorizedException
     */
    public function resolve(string $macInput, User $user): array
    {
        if (! $user->isActive()) {
            throw new SearchUnauthorizedException('Se requiere un usuario autenticado y activo.');
        }

        [$normalized, $validationError] = self::normalizeMac($macInput);

        if ($validationError !== null) {
            throw new \InvalidArgumentException($validationError);
        }

        $started = microtime(true);

        $routers = MikrotikRouter::where('is_active', true)->orderBy('priority')->get();

        $results = [];
        $reachable = 0;
        $failures = [];

        foreach ($routers as $router) {
            $routerStart = microtime(true);
            $routerResults = [];

            try {
                $sources = $this->mikrotik->collectSources($router);

                if ($sources['error'] !== null) {
                    $router->forceFill([
                        'last_checked_at' => now(),
                        'connectivity_status' => 'fail',
                    ])->save();

                    $failures[] = [
                        'router' => $router->name,
                        'error' => $sources['error'],
                        'detail' => $sources['error_detail'] ?? null,
                    ];

                    Log::warning('Mikrotik source error', [
                        'router' => $router->name,
                        'router_id' => $router->id,
                        'error' => $sources['error'],
                    ]);

                    continue;
                }

                $reachable++;
                $router->forceFill([
                    'last_checked_at' => now(),
                    'last_success_at' => now(),
                    'connectivity_status' => 'ok',
                ])->save();

                $routerResults = $this->searchInSources($sources, $normalized, $router);
            } catch (MikrotikException $e) {
                $failures[] = [
                    'router' => $router->name,
                    'error' => $e->errorType,
                    'detail' => $e->detail,
                ];

                $router->forceFill([
                    'last_checked_at' => now(),
                    'connectivity_status' => 'fail',
                ])->save();

                Log::warning('Mikrotik connection failed', [
                    'router' => $router->name,
                    'router_id' => $router->id,
                    'type' => $e->errorType,
                ]);
            }

            $latency = (int) round((microtime(true) - $routerStart) * 1000);

            foreach ($routerResults as $r) {
                $r['response_ms'] = $latency;
                $results[] = $r;
            }
        }

        $duration = (int) round((microtime(true) - $started) * 1000);

        $status = $this->classifyStatus($routers->count(), $reachable, count($results));

        return [
            'mac_input' => $macInput,
            'mac_normalized' => $normalized,
            'total_routers' => $routers->count(),
            'reachable_routers' => $reachable,
            'result_count' => count($results),
            'status' => $status,
            'duration_ms' => $duration,
            'failures' => $failures,
            'results' => $results,
        ];
    }

    /**
     * Guarda la búsqueda, sus resultados y audita la operación.
     */
    public function persist(array $resolved, User $user, ?string $requestIp, ?string $userAgent = null): MacSearch
    {
        $firstActive = collect($resolved['results'])->firstWhere('is_active_match', true);
        $first = $firstActive ?? ($resolved['results'][0] ?? null);

        $search = MacSearch::create([
            'user_id' => $user->id,
            'mac_normalized' => $resolved['mac_normalized'],
            'mac_input' => $resolved['mac_input'],
            'total_routers' => $resolved['total_routers'],
            'reachable_routers' => $resolved['reachable_routers'],
            'result_count' => $resolved['result_count'],
            'status' => $resolved['status'],
            'duration_ms' => $resolved['duration_ms'],
            'ip_address' => $first['ip_address'] ?? null,
            'request_ip' => $requestIp,
            'user_agent' => substr((string) $userAgent, 0, 255),
            'meta' => ['failures' => $resolved['failures']],
        ]);

        foreach ($resolved['results'] as $r) {
            MacSearchResult::create([
                'mac_search_id' => $search->id,
                'router_id' => $r['router_id'],
                'router_name' => $r['router_name'],
                'municipality' => $r['municipality'] ?? null,
                'ip_address' => $r['ip_address'],
                'mac_address' => $r['mac_address'],
                'mechanism' => $r['mechanism'],
                'mechanisms' => $r['mechanisms'] ?? [$r['mechanism']],
                'status_label' => $r['status_label'],
                'hostname' => $r['hostname'],
                'server' => $r['server'],
                'interface' => $r['interface'],
                'comment' => $r['comment'],
                'is_active_match' => $r['is_active_match'],
                'response_ms' => $r['response_ms'],
                'raw' => $r['raw'],
            ]);
        }

        AuditLog::record(
            'mac_search',
            $user,
            "Búsqueda {$resolved['mac_normalized']} — {$resolved['result_count']} resultado(s), {$resolved['reachable_routers']}/{$resolved['total_routers']} routers",
            $requestIp,
            'success',
            (string) $search->id,
            ['mac' => $resolved['mac_normalized'], 'results' => $resolved['result_count']],
        );

        return $search;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public static function normalizeMac(string $mac): array
    {
        $clean = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');

        if (strlen($clean) !== 12 || ! ctype_xdigit($clean)) {
            return ['', 'La dirección MAC ingresada no tiene un formato válido. Verifica que contenga 12 caracteres hexadecimales.'];
        }

        return [implode(':', str_split($clean, 2)), null];
    }

    /**
     * @param  array<string,mixed>  $sources
     * @return list<array<string,mixed>>
     */
    private function searchInSources(array $sources, string $normalized, MikrotikRouter $router): array
    {
        $found = [];

        // PPPoE activo: la MAC aparece en caller-id.
        foreach ($sources['ppp'] ?? [] as $row) {
            $caller = $row['caller-id'] ?? null;
            if (! $caller) {
                continue;
            }

            [$norm] = self::normalizeMac($caller);
            if ($norm !== $normalized) {
                continue;
            }

            $found[] = $this->row($router, $normalized, $row['address'] ?? null, 'pppoe_active', 'active', [
                'hostname' => $row['name'] ?? null,
                'raw' => $row,
                'is_active_match' => true,
            ]);
        }

        // Leases DHCP (dinámicos y estáticos).
        foreach ($sources['dhcp'] ?? [] as $row) {
            $mac = $row['mac-address'] ?? null;
            if (! $mac) {
                continue;
            }

            [$norm] = self::normalizeMac($mac);
            if ($norm !== $normalized) {
                continue;
            }

            $dynamic = filter_var($row['dynamic'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $disabled = filter_var($row['disabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $status = strtolower((string) ($row['status'] ?? ''));

            $found[] = $this->row(
                $router,
                $normalized,
                $row['address'] ?? null,
                $dynamic ? 'dhcp_active' : 'dhcp_static',
                $status ?: ($disabled ? 'disabled' : ($dynamic ? 'bound' : 'static')),
                [
                    'hostname' => $row['host-name'] ?? null,
                    'server' => $row['server'] ?? null,
                    'comment' => $row['comment'] ?? null,
                    'raw' => $row,
                    'is_active_match' => $dynamic && ! $disabled && $status === 'bound',
                ],
            );
        }

        // Tabla ARP.
        foreach ($sources['arp'] ?? [] as $row) {
            $mac = $row['mac-address'] ?? null;
            if (! $mac) {
                continue;
            }

            [$norm] = self::normalizeMac($mac);
            if ($norm !== $normalized) {
                continue;
            }

            $complete = filter_var($row['complete'] ?? true, FILTER_VALIDATE_BOOLEAN);

            $found[] = $this->row($router, $normalized, $row['address'] ?? null, 'arp_complete', $complete ? 'complete' : 'incomplete', [
                'interface' => $row['interface'] ?? null,
                'raw' => $row,
                'is_active_match' => $complete,
            ]);
        }

        // Hotspot IP bindings (asociaciones estáticas MAC → IP).
        foreach ($sources['hotspot_binding'] ?? [] as $row) {
            $mac = $row['mac-address'] ?? null;
            if (! $mac) {
                continue;
            }

            [$norm] = self::normalizeMac($mac);
            if ($norm !== $normalized) {
                continue;
            }

            $toAddress = $row['to-address'] ?? null;
            $address = $row['address'] ?? null;
            $ip = ($toAddress && $toAddress !== '0.0.0.0') ? $toAddress : $address;

            $type = strtolower((string) ($row['type'] ?? 'regular'));
            $disabled = filter_var($row['disabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $found[] = $this->row($router, $normalized, $ip, 'hotspot_binding', $type, [
                'server' => $row['server'] ?? null,
                'comment' => $row['comment'] ?? null,
                'raw' => $row,
                'is_active_match' => ! $disabled && $type === 'bypassed',
            ]);
        }

        // Descartar filas sin IP válida, duplicados exactos y consolidar por IP:
        // una misma IP confirmada por DHCP y ARP es un solo dispositivo.
        $found = array_values(array_filter($found, fn ($row) => $row !== []));
        $found = array_values(array_unique($found, SORT_REGULAR));

        return $this->consolidate($found);
    }

    /**
     * Agrupa por router + IP y deja un solo resultado por dispositivo.
     *
     * El mecanismo principal es el más específico (DHCP activo > DHCP estático >
     * PPPoE > hotspot > ARP) y los demás se conservan como confirmación.
     * Los resultados activos van primero.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return list<array<string,mixed>>
     */
    private function consolidate(array $rows): array
    {
        $weights = [
            'dhcp_active' => 1,
            'dhcp_static' => 2,
            'pppoe_active' => 3,
            'hotspot_binding' => 4,
            'arp_complete' => 5,
        ];

        $grouped = [];

        foreach ($rows as $row) {
            $key = ($row['router_id'] ?? 0).'|'.($row['ip_address'] ?? '');

            if (! isset($grouped[$key])) {
                $row['mechanisms'] = [$row['mechanism']];
                $grouped[$key] = $row;

                continue;
            }

            $current = $grouped[$key];

            $mechanisms = array_values(array_unique(array_merge(
                [$current['mechanism']],
                $current['mechanisms'] ?? [],
                [$row['mechanism']],
            )));

            $active = $current['is_active_match'] || $row['is_active_match'];

            $moreSpecific = ($weights[$row['mechanism']] ?? 99) < ($weights[$current['mechanism']] ?? 99);
            $winsOnActive = ! $current['is_active_match'] && $row['is_active_match'];

            if ($moreSpecific || $winsOnActive) {
                $merged = $row;

                // Heredar datos que el ganador no traiga.
                foreach (['hostname', 'server', 'interface', 'comment'] as $field) {
                    if (blank($merged[$field] ?? null) && filled($current[$field] ?? null)) {
                        $merged[$field] = $current[$field];
                    }
                }
            } else {
                $merged = $current;

                foreach (['hostname', 'server', 'interface', 'comment'] as $field) {
                    if (blank($merged[$field] ?? null) && filled($row[$field] ?? null)) {
                        $merged[$field] = $row[$field];
                    }
                }

                if (blank($merged['raw'] ?? null) && filled($row['raw'] ?? null)) {
                    $merged['raw'] = $row['raw'];
                }
            }

            $merged['mechanisms'] = $mechanisms;
            $merged['is_active_match'] = $active;
            $grouped[$key] = $merged;
        }

        // El resultado principal primero: activo y con el mecanismo más específico.
        $results = array_values($grouped);

        usort($results, function (array $a, array $b) use ($weights) {
            if ($a['is_active_match'] !== $b['is_active_match']) {
                return $a['is_active_match'] ? -1 : 1;
            }

            return ($weights[$a['mechanism']] ?? 99) <=> ($weights[$b['mechanism']] ?? 99);
        });

        return $results;
    }

    /**
     * @return array<string,mixed>
     */
    private function row(MikrotikRouter $router, string $mac, ?string $ip, string $mechanism, string $statusLabel, array $extra): array
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return [];
        }

        return array_merge([
            'router_id' => $router->id,
            'router_name' => $router->name,
            'municipality' => $router->municipality,
            'ip_address' => $ip,
            'mac_address' => $mac,
            'mechanism' => $mechanism,
            'mechanisms' => [$mechanism],
            'status_label' => $statusLabel,
            'hostname' => null,
            'server' => null,
            'interface' => null,
            'comment' => null,
            'is_active_match' => false,
            'raw' => null,
        ], $extra);
    }

    private function classifyStatus(int $total, int $reachable, int $resultCount): string
    {
        if ($total === 0) {
            return 'no_routers';
        }

        if ($reachable === 0) {
            return 'error';
        }

        if ($resultCount === 0) {
            return 'no_results';
        }

        return $reachable < $total ? 'partial' : 'completed';
    }
}
