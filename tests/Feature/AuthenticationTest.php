<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_de_login_es_accesible_sin_sesion(): void
    {
        $this->get('/login')->assertOk()->assertSee('IPSearch Interya');
    }

    public function test_el_login_no_entra_en_bucle_de_redireccion(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertStringNotContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function test_un_usuario_activo_puede_iniciar_sesion(): void
    {
        $user = User::factory()->create([
            'email' => 'operador@interya.com',
            'password' => bcrypt('Password#2026'),
        ]);

        $response = $this->post('/login', [
            'email' => 'operador@interya.com',
            'password' => 'Password#2026',
        ]);

        $response->assertRedirect(route('search.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_success', 'result' => 'success']);
    }

    public function test_un_usuario_suspendido_no_puede_iniciar_sesion(): void
    {
        User::factory()->suspended()->create([
            'email' => 'suspendido@interya.com',
            'password' => bcrypt('Password#2026'),
        ]);

        $response = $this->post('/login', [
            'email' => 'suspendido@interya.com',
            'password' => 'Password#2026',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_credenciales_invalidas_no_autentican(): void
    {
        User::factory()->create([
            'email' => 'operador@interya.com',
            'password' => bcrypt('Password#2026'),
        ]);

        $this->post('/login', [
            'email' => 'operador@interya.com',
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_el_registro_libre_redirige_al_login(): void
    {
        $this->get('/register')->assertRedirect(route('login'));
    }

    public function test_la_recuperacion_de_contrasena_es_accesible(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_el_cierre_de_sesion_invalida_la_sesion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_las_auditorias_se_registran_sin_columna_updated_at(): void
    {
        AuditLog::record('prueba', null, 'evento de prueba', '127.0.0.1');

        $this->assertDatabaseHas('audit_logs', ['action' => 'prueba', 'result' => 'success']);
    }
}
