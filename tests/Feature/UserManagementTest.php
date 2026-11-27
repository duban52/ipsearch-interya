<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superadmin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Carlos',
            'lastname' => 'Pérez',
            'email' => 'carlos@interya.com',
            'username' => 'carlos',
            'password' => 'ClaveSegura#2026',
            'password_confirmation' => 'ClaveSegura#2026',
            'role' => 'user',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_el_superadministrador_crea_un_usuario_con_contrasena_valida(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', $this->payload())
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'carlos@interya.com')->firstOrFail();

        $this->assertSame('carlos', $user->username);
        $this->assertSame('user', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('ClaveSegura#2026', $user->password));
        $this->assertNotSame('ClaveSegura#2026', $user->password);
    }

    public function test_la_creacion_de_usuario_no_falla_por_reglas_de_contrasena_inexistentes(): void
    {
        // Regresión: 'letters', 'mixed_case', 'numbers' y 'symbols' son métodos
        // del objeto Password, no reglas de validación en texto.
        $response = $this->actingAs($this->admin())->post('/admin/users', $this->payload());

        $response->assertSessionHasNoErrors();
    }

    public function test_una_contrasena_debil_es_rechazada(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', $this->payload([
                'password' => 'debil',
                'password_confirmation' => 'debil',
            ]))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'carlos@interya.com']);
    }

    public function test_la_confirmacion_de_contrasena_debe_coincidir(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', $this->payload([
                'password_confirmation' => 'OtraClave#2026',
            ]))
            ->assertSessionHasErrors('password');
    }

    public function test_el_checkbox_de_activacion_se_respeta(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', $this->payload(['is_active' => null]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(User::where('email', 'carlos@interya.com')->firstOrFail()->is_active);
    }

    public function test_el_administrador_puede_cambiar_la_contrasena_de_un_usuario(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['password' => bcrypt('Antigua#2026')]);

        $this->actingAs($admin)->put('/admin/users/'.$target->id, [
            'name' => $target->name,
            'lastname' => $target->lastname,
            'email' => $target->email,
            'username' => $target->username,
            'role' => $target->role,
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NuevaClave#2026', $target->fresh()->password));
    }

    public function test_actualizar_sin_contrasena_conserva_la_actual(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['password' => bcrypt('Antigua#2026')]);

        $this->actingAs($admin)->put('/admin/users/'.$target->id, [
            'name' => 'Nombre Nuevo',
            'lastname' => $target->lastname,
            'email' => $target->email,
            'username' => $target->username,
            'role' => $target->role,
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Antigua#2026', $target->fresh()->password));
        $this->assertSame('Nombre Nuevo', $target->fresh()->name);
    }

    public function test_el_formulario_de_alta_renderiza_los_dos_campos_de_contrasena(): void
    {
        $this->actingAs($this->admin())->get('/admin/users/create')
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false);
    }
}
