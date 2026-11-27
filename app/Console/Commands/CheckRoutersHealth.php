<?php

namespace App\Console\Commands;

use App\Models\MikrotikRouter;
use App\Services\Mikrotik\MikrotikException;
use App\Services\Mikrotik\MikrotikRestClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckRoutersHealth extends Command
{
    protected $signature = 'routers:health {--router= : Comprobar solo el router con este ID}';

    protected $description = 'Comprueba la conectividad de los routers MikroTik activos y actualiza su estado';

    public function handle(MikrotikRestClient $client): int
    {
        $routers = MikrotikRouter::query()
            ->where('is_active', true)
            ->when($this->option('router'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('priority')
            ->get();

        if ($routers->isEmpty()) {
            $this->warn('No hay routers activos que comprobar.');

            return self::SUCCESS;
        }

        $filas = [];

        foreach ($routers as $router) {
            try {
                $info = $client->testConnection($router);

                $router->forceFill([
                    'last_checked_at' => now(),
                    'last_success_at' => now(),
                    'connectivity_status' => 'ok',
                ])->save();

                $filas[] = [
                    $router->name,
                    'OK',
                    trim(($info['version'] ?? '').' / '.($info['board_name'] ?? ''), ' /'),
                    ($info['latency_ms'] ?? 0).' ms',
                ];
            } catch (MikrotikException $e) {
                $router->forceFill([
                    'last_checked_at' => now(),
                    'connectivity_status' => 'fail',
                ])->save();

                $filas[] = [$router->name, 'FALLO', $e->errorType, '—'];

                Log::warning('Comprobación de salud fallida', [
                    'router' => $router->name,
                    'router_id' => $router->id,
                    'type' => $e->errorType,
                ]);
            }
        }

        $this->table(['Router', 'Estado', 'Detalle', 'Latencia'], $filas);

        $fallidos = collect($filas)->where(1, 'FALLO')->count();

        if ($fallidos > 0) {
            $this->warn("{$fallidos} de ".count($filas).' routers no respondieron.');
        }

        return self::SUCCESS;
    }
}
