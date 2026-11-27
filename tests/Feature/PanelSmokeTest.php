<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MacSearch;
use App\Models\MikrotikRouter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renderiza todas las vistas principales para detectar errores de plantilla
 * (variables indefinidas, directivas mal cerradas, relaciones nulas).
 */
class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superadmin()->create();
    }

    public function test_todas_las_pantallas_del_panel_renderizan(): void
    {
        MikrotikRouter::create([
            'name' => 'Router Norte',
            'host' => '10.0.0.2',
            'api_port' => 8729,
            'api_user' => 'api-lectura',
            'api_password' => encrypt('secreto'),
            'municipality' => 'Bello',
            'is_active' => true,
            'priority' => 2,
        ]);

        AuditLog::record('login_success', null, 'Evento de prueba', '127.0.0.1');
        MacSearch::create([
            'user_id' => $this->admin()->id,
            'mac_normalized' => 'AA:BB:CC:DD:EE:01',
            'mac_input' => 'AABBCCDDEE01',
            'status' => 'no_results',
        ]);

        $admin = $this->admin();

        $routes = [
            '/admin/dashboard',
            '/admin/routers',
            '/admin/routers/create',
            '/admin/users',
            '/admin/users/create',
            '/admin/users/invite/create',
            '/admin/auditoria',
            '/admin/config',
            '/admin/salud',
            '/admin/estadisticas',
        ];

        foreach ($routes as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_editar_router_y_usuario_renderiza(): void
    {
        $router = MikrotikRouter::create([
            'name' => 'Router Sur',
            'host' => '10.0.0.3',
            'api_port' => 8729,
            'api_user' => 'api-lectura',
            'api_password' => encrypt('secreto'),
            'is_active' => true,
            'priority' => 3,
        ]);

        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)->get('/admin/routers/'.$router->id.'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/users/'.$target->id.'/edit')->assertOk();
    }

    public function test_detalle_de_auditoria_renderiza(): void
    {
        $log = AuditLog::record('router_created', null, 'Router creado', '127.0.0.1');

        $this->actingAs($this->admin())->get('/admin/auditoria/'.$log->id)->assertOk();
    }

    public function test_pantallas_del_usuario_renderizan(): void
    {
        $user = User::factory()->create();

        $search = MacSearch::create([
            'user_id' => $user->id,
            'mac_normalized' => 'AA:BB:CC:DD:EE:02',
            'mac_input' => 'AABBCCDDEE02',
            'status' => 'completed',
            'result_count' => 0,
        ]);

        foreach (['/', '/search/history', '/perfil', '/search/'.$search->id] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_el_superadmin_puede_crear_un_router_cifrado(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/routers', [
            'name' => 'Router Nuevo',
            'host' => '10.0.0.9',
            'api_port' => 8729,
            'api_user' => 'api-lectura',
            'api_password' => 'ClaveSegura#1',
            'priority' => 4,
            'connect_timeout_ms' => 3000,
            'read_timeout_ms' => 8000,
            'is_active' => '1',
            'use_ssl' => '1',
            'verify_ssl' => '1',
        ])->assertRedirect(route('admin.routers.index'));

        $router = MikrotikRouter::where('name', 'Router Nuevo')->firstOrFail();

        $this->assertNotSame('ClaveSegura#1', $router->api_password);
        $this->assertSame('ClaveSegura#1', \Illuminate\Support\Facades\Crypt::decryptString($router->api_password));
        $this->assertArrayNotHasKey('api_password', $router->toArray());
    }

    public function test_el_administrador_puede_suspender_y_reactivar_usuarios(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)->post('/admin/users/'.$target->id.'/suspend')->assertRedirect();
        $this->assertFalse($target->fresh()->is_active);

        $this->actingAs($admin)->post('/admin/users/'.$target->id.'/reactivate')->assertRedirect();
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_el_superadministrador_no_puede_suspenderse_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users/'.$admin->id.'/suspend')->assertRedirect();

        $this->assertTrue($admin->fresh()->is_active);
    }
}
