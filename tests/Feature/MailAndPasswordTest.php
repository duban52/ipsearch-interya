<?php

namespace Tests\Feature;

use App\Models\MailSetting;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Notifications\ResetPasswordNotification;
use App\Services\Mail\MailConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailAndPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_configuracion_smtp_activa_se_aplica_al_mailer(): void
    {
        MailSetting::create([
            'host' => 'smtp.interya.com',
            'port' => 587,
            'username' => 'notificaciones',
            'password' => Crypt::encryptString('ClaveSmtp#1'),
            'encryption' => 'tls',
            'from_address' => 'no-reply@interya.com',
            'from_name' => 'IPSearch Interya',
            'is_active' => true,
        ]);

        app(MailConfigurator::class)->apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.interya.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('ClaveSmtp#1', config('mail.mailers.smtp.password'));
        $this->assertSame('no-reply@interya.com', config('mail.from.address'));
    }

    public function test_ssl_usa_el_esquema_smtps(): void
    {
        MailSetting::create([
            'host' => 'smtp.interya.com',
            'port' => 465,
            'encryption' => 'ssl',
            'from_address' => 'no-reply@interya.com',
            'from_name' => 'IPSearch Interya',
            'is_active' => true,
        ]);

        app(MailConfigurator::class)->apply();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    public function test_sin_configuracion_activa_se_respeta_el_env(): void
    {
        $default = config('mail.default');
        $host = config('mail.mailers.smtp.host');

        MailSetting::create([
            'host' => 'smtp.inactivo.test',
            'port' => 587,
            'encryption' => 'tls',
            'from_address' => 'no-reply@interya.com',
            'from_name' => 'IPSearch',
            'is_active' => false,
        ]);

        app(MailConfigurator::class)->apply();

        $this->assertSame($default, config('mail.default'));
        $this->assertSame($host, config('mail.mailers.smtp.host'));
    }

    public function test_la_invitacion_se_envia_por_correo(): void
    {
        Notification::fake();

        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->post('/admin/users/invite/create', [
            'name' => 'Carla',
            'email' => 'carla@interya.com',
            'username' => 'carla',
            'role' => 'user',
        ])->assertRedirect();

        $invitado = User::where('email', 'carla@interya.com')->firstOrFail();

        Notification::assertSentTo($invitado, InvitationNotification::class, function (InvitationNotification $notification) {
            $mail = $notification->toMail(User::where('email', 'carla@interya.com')->firstOrFail());

            return str_contains($mail->actionUrl, '/invite/');
        });
    }

    public function test_con_correo_en_modo_log_el_enlace_se_muestra_al_administrador(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']);

        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->post('/admin/users/invite/create', [
            'name' => 'Sofía',
            'email' => 'sofia@interya.com',
            'username' => 'sofia',
            'role' => 'user',
        ])->assertRedirect();

        $invitado = User::where('email', 'sofia@interya.com')->firstOrFail();

        // Con el transporte "log" nada llega a una bandeja: no se simula envío.
        Notification::assertNotSentTo($invitado, InvitationNotification::class);

        // ...pero el enlace queda disponible para el administrador.
        $this->assertStringContainsString('/invite/', (string) session('success'));
        $this->assertStringContainsString('no pudo enviarse', (string) session('success'));
    }

    public function test_el_administrador_puede_enviar_un_correo_de_prueba(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = User::factory()->superadmin()->create(['email' => 'admin-prueba@interya.com']);

        $this->actingAs($admin)
            ->postJson('/admin/smtp/test')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('audit_logs', ['action' => 'smtp_test']);
    }

    public function test_la_prueba_de_correo_avisa_cuando_esta_en_modo_registro(): void
    {
        config(['mail.default' => 'log']);

        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->postJson('/admin/smtp/test')
            ->assertOk()
            ->assertJsonPath('success', false);

        $this->assertStringContainsString('modo registro', $this->actingAs($admin)
            ->postJson('/admin/smtp/test')
            ->json('message'));
    }

    public function test_un_usuario_normal_no_puede_enviar_correo_de_prueba(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/admin/smtp/test')->assertForbidden();
    }

    public function test_la_solicitud_de_recuperacion_envia_la_notificacion_en_espanol(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'olvido@interya.com']);
        $token = null;

        $this->post('/forgot-password', ['email' => 'olvido@interya.com'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->assertNotEmpty($token);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'olvido@interya.com',
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NuevaClave#2026', $user->fresh()->password));
    }

    public function test_un_usuario_suspendido_no_recibe_enlace_de_recuperacion(): void
    {
        Notification::fake();

        $user = User::factory()->suspended()->create(['email' => 'suspendido@interya.com']);

        $this->post('/forgot-password', ['email' => 'suspendido@interya.com'])
            ->assertSessionHas('status');

        Notification::assertNotSentTo($user, ResetPasswordNotification::class);
    }

    public function test_un_token_de_recuperacion_invalido_es_rechazado(): void
    {
        User::factory()->create(['email' => 'otro@interya.com']);

        $this->post('/reset-password', [
            'token' => 'token-invalido',
            'email' => 'otro@interya.com',
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
        ])->assertSessionHasErrors('email');
    }
}
