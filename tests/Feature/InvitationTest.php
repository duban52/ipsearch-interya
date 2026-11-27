<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFromFlash(string $message): string
    {
        $this->assertMatchesRegularExpression('#/invite/([A-Za-z0-9]+)#', $message);

        preg_match('#/invite/([A-Za-z0-9]+)#', $message, $matches);

        return $matches[1];
    }

    public function test_el_superadministrador_invita_y_el_usuario_activa_su_cuenta(): void
    {
        $admin = User::factory()->superadmin()->create();

        $response = $this->actingAs($admin)->post('/admin/users/invite/create', [
            'name' => 'Ana',
            'lastname' => 'Gómez',
            'email' => 'ana@interya.com',
            'username' => 'ana_gomez',
            'role' => 'user',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $invitado = User::where('email', 'ana@interya.com')->firstOrFail();
        $this->assertFalse($invitado->is_active);
        $this->assertSame('user', $invitado->role);
        $this->assertSame(1, Invitation::where('user_id', $invitado->id)->count());

        $token = $this->tokenFromFlash((string) session('success'));

        // La página de activación es pública.
        $this->app['auth']->forgetGuards();

        $this->get('/invite/'.$token)->assertOk()->assertSee('ana@interya.com');

        $this->post('/invite/'.$token, [
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
        ])->assertRedirect(route('search.index'));

        $this->assertAuthenticated();
        $this->assertTrue($invitado->fresh()->is_active);
        $this->assertNotNull($invitado->fresh()->email_verified_at);
        $this->assertNotNull(Invitation::first()->accepted_at);
    }

    public function test_una_invitacion_ya_usada_no_se_puede_reutilizar(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->post('/admin/users/invite/create', [
            'name' => 'Luis',
            'email' => 'luis@interya.com',
            'username' => 'luis',
            'role' => 'user',
        ]);

        $token = $this->tokenFromFlash((string) session('success'));

        $this->app['auth']->forgetGuards();

        $this->post('/invite/'.$token, [
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
        ]);

        $this->app['auth']->forgetGuards();

        $this->get('/invite/'.$token)->assertStatus(410);
    }

    public function test_un_invitado_no_puede_generar_invitaciones(): void
    {
        $this->get('/admin/users/invite/create')->assertRedirect(route('login'));

        $this->post('/admin/users/invite/create', [
            'name' => 'Intruso',
            'email' => 'intruso@interya.com',
            'username' => 'intruso',
            'role' => 'superadmin',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'intruso@interya.com']);
    }

    public function test_un_usuario_normal_no_puede_generar_invitaciones(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post('/admin/users/invite/create', [
            'name' => 'Intruso',
            'email' => 'intruso2@interya.com',
            'username' => 'intruso2',
            'role' => 'superadmin',
        ])->assertForbidden();
    }
}
