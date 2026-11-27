<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\User;
use App\Services\Mikrotik\MikrotikException;
use App\Services\Mikrotik\MikrotikRestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La prueba de conexión debe explicar el fallo, no solo decir "timeout":
 * es la diferencia entre "puerto equivocado", "red bloqueada" y "credenciales".
 */
class RouterDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private function router(): MikrotikRouter
    {
        return MikrotikRouter::create([
            'name' => 'Router Zona 100',
            'host' => '10.100.0.1',
            'api_port' => 8729,
            'api_user' => 'api-lectura',
            'api_password' => encrypt('secreto'),
            'use_ssl' => false,
            'verify_ssl' => false,
            'is_active' => true,
            'priority' => 1,
        ]);
    }

    public function test_un_timeout_devuelve_pista_accionable(): void
    {
        $router = $this->router();
        $admin = User::factory()->superadmin()->create();

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('endpoint')->andReturn('http://10.100.0.1:8729')
            ->shouldReceive('testConnection')
            ->andThrow(new MikrotikException('Tiempo de espera agotado.', 'timeout', null, 'http://10.100.0.1:8729/rest/system/resource → Timeout was reached'));

        $response = $this->actingAs($admin)
            ->postJson('/admin/routers/'.$router->id.'/test');

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('type', 'timeout')
            ->assertJsonPath('endpoint', 'http://10.100.0.1:8729');

        $this->assertStringContainsString('80', $response->json('hint'));
        $this->assertStringContainsString('8728/8729', $response->json('hint'));
        $this->assertStringContainsString('Timeout was reached', $response->json('detail'));

        $this->assertDatabaseHas('mikrotik_routers', [
            'id' => $router->id,
            'connectivity_status' => 'fail',
        ]);
    }

    public function test_un_fallo_de_credenciales_explica_permisos(): void
    {
        $router = $this->router();
        $admin = User::factory()->superadmin()->create();

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('endpoint')->andReturn('http://10.100.0.1:8729')
            ->shouldReceive('testConnection')
            ->andThrow(MikrotikException::auth('http://10.100.0.1:8729/rest/system/resource → HTTP 401'));

        $response = $this->actingAs($admin)->postJson('/admin/routers/'.$router->id.'/test');

        $response->assertOk()->assertJsonPath('type', 'auth');
        $this->assertStringContainsString('permisos', $response->json('hint'));
    }

    public function test_una_conexion_correcta_reporta_version_y_endpoint(): void
    {
        $router = $this->router();
        $admin = User::factory()->superadmin()->create();

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('endpoint')->andReturn('http://10.100.0.1:8729')
            ->shouldReceive('testConnection')
            ->andReturn([
                'version' => '7.16.2',
                'board_name' => 'RB4011iGS+',
                'uptime' => '3d',
                'latency_ms' => 18,
                'endpoint' => 'http://10.100.0.1:8729',
            ]);

        $response = $this->actingAs($admin)->postJson('/admin/routers/'.$router->id.'/test');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('info.version', '7.16.2')
            ->assertJsonPath('info.endpoint', 'http://10.100.0.1:8729');
    }

    public function test_un_usuario_normal_no_puede_probar_routers(): void
    {
        $router = $this->router();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/admin/routers/'.$router->id.'/test')
            ->assertForbidden();
    }

    public function test_el_comando_de_diagnostico_reporta_puerto_cerrado(): void
    {
        $this->artisan('routers:probe', ['host' => '127.0.0.1', '--port' => 1, '--plain' => true, '--timeout' => 1])
            ->expectsOutputToContain('TCP 1')
            ->assertSuccessful();
    }

    public function test_el_comando_advierte_sobre_los_puertos_de_la_api_binaria(): void
    {
        $this->artisan('routers:probe', ['host' => '127.0.0.1', '--port' => 8729, '--plain' => true, '--timeout' => 1])
            ->expectsOutputToContain('API BINARIA')
            ->assertSuccessful();
    }
}
