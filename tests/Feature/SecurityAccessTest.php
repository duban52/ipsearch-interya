<?php

namespace Tests\Feature;

use App\Models\MacSearch;
use App\Models\User;
use App\Services\Mikrotik\MikrotikRestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requisito de seguridad innegociable: ninguna consulta MAC → IP puede
 * ejecutarse sin sesión iniciada, cuenta activa y rol autorizado.
 */
class SecurityAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function protectedGetRoutes(): array
    {
        return [
            'buscador' => ['GET', '/'],
            'historial' => ['GET', '/search/history'],
            'perfil' => ['GET', '/perfil'],
            'panel admin' => ['GET', '/admin/dashboard'],
            'routers admin' => ['GET', '/admin/routers'],
            'usuarios admin' => ['GET', '/admin/users'],
            'auditoria admin' => ['GET', '/admin/auditoria'],
            'config admin' => ['GET', '/admin/config'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('protectedGetRoutes')]
    public function test_un_invitado_no_puede_acceder_a_rutas_protegidas(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_un_invitado_no_puede_ejecutar_la_busqueda_mac(): void
    {
        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSourcesMany')->never();

        $this->post('/search', ['mac' => 'AA:BB:CC:DD:EE:FF'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, MacSearch::count());
    }

    public function test_la_api_interna_rechaza_peticiones_sin_sesion(): void
    {
        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSourcesMany')->never();

        $this->postJson('/api/v1/mac-search', ['mac' => 'AA:BB:CC:DD:EE:FF'])
            ->assertUnauthorized();

        $this->assertSame(0, MacSearch::count());
    }

    public function test_un_usuario_normal_no_puede_entrar_al_panel_administrativo(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        foreach (['/admin/dashboard', '/admin/routers', '/admin/users', '/admin/auditoria', '/admin/config'] as $uri) {
            $this->actingAs($user)->get($uri)->assertForbidden();
        }
    }

    public function test_un_usuario_normal_no_puede_gestionar_routers_ni_usuarios(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post('/admin/routers', [
            'name' => 'Router intruso',
            'host' => '10.0.0.1',
            'api_port' => 8729,
            'api_user' => 'api',
            'api_password' => 'x',
            'priority' => 1,
            'connect_timeout_ms' => 3000,
            'read_timeout_ms' => 8000,
        ])->assertForbidden();

        $this->actingAs($user)->post('/admin/users', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'username' => 'intruso',
            'password' => 'Password#2026',
            'password_confirmation' => 'Password#2026',
            'role' => 'superadmin',
        ])->assertForbidden();

        $this->assertDatabaseMissing('mikrotik_routers', ['name' => 'Router intruso']);
        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }

    public function test_un_usuario_suspendido_con_sesion_activa_es_expulsado_y_no_puede_buscar(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user);

        $user->forceFill(['is_active' => false, 'suspended_at' => now()])->save();

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSourcesMany')->never();

        $this->post('/search', ['mac' => 'AA:BB:CC:DD:EE:FF'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, MacSearch::count());
    }

    public function test_un_superadministrador_si_puede_entrar_al_panel(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_el_endpoint_de_salud_publico_no_expone_informacion_sensible(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_un_invitado_no_puede_ver_la_auditoria(): void
    {
        $this->get('/admin/auditoria')->assertRedirect(route('login'));
    }
}
