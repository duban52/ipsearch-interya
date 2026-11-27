<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Services\Mikrotik\MikrotikException;
use App\Services\Mikrotik\MikrotikRestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouterHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    private function router(string $name, int $priority = 1): MikrotikRouter
    {
        return MikrotikRouter::create([
            'name' => $name,
            'host' => '10.0.0.'.(10 + $priority),
            'api_port' => 8729,
            'api_user' => 'api-lectura',
            'api_password' => encrypt('secreto'),
            'is_active' => true,
            'priority' => $priority,
        ]);
    }

    public function test_actualiza_el_estado_de_cada_router(): void
    {
        $sano = $this->router('Router Sano', 1);
        $caido = $this->router('Router Caído', 2);

        $client = \Mockery::mock(MikrotikRestClient::class);
        $client->shouldReceive('testConnection')
            ->with(\Mockery::on(fn ($router) => $router->is($sano)))
            ->andReturn(['version' => '7.16', 'board_name' => 'RB4011', 'uptime' => '1w', 'latency_ms' => 42]);
        $client->shouldReceive('testConnection')
            ->with(\Mockery::on(fn ($router) => $router->is($caido)))
            ->andThrow(MikrotikException::auth());

        $this->app->instance(MikrotikRestClient::class, $client);

        $this->artisan('routers:health')->assertSuccessful();

        $this->assertSame('ok', $sano->fresh()->connectivity_status);
        $this->assertNotNull($sano->fresh()->last_success_at);
        $this->assertSame('fail', $caido->fresh()->connectivity_status);
        $this->assertNull($caido->fresh()->last_success_at);
        $this->assertNotNull($caido->fresh()->last_checked_at);
    }

    public function test_sin_routers_activos_no_falla(): void
    {
        $this->artisan('routers:health')->assertSuccessful();
    }

    public function test_puede_comprobar_un_solo_router(): void
    {
        $uno = $this->router('Router Uno', 1);
        $dos = $this->router('Router Dos', 2);

        $client = \Mockery::mock(MikrotikRestClient::class);
        $client->shouldReceive('testConnection')->once()->andReturn([
            'version' => '7.16', 'board_name' => 'RB4011', 'uptime' => '1d', 'latency_ms' => 10,
        ]);

        $this->app->instance(MikrotikRestClient::class, $client);

        $this->artisan('routers:health', ['--router' => $uno->id])->assertSuccessful();

        $this->assertSame('ok', $uno->fresh()->connectivity_status);
        $this->assertSame('unknown', $dos->fresh()->connectivity_status);
    }
}
