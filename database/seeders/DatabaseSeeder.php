<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Siembra la configuración base y la cuenta superadministradora inicial.
     *
     * La contraseña se toma de IPS_ADMIN_PASSWORD. Si no está definida se
     * genera una aleatoria y se muestra una sola vez por consola.
     */
    public function run(): void
    {
        $this->seedSettings();
        $this->seedSuperAdmin();
    }

    private function seedSettings(): void
    {
        $defaults = [
            ['key' => 'app_name', 'value' => 'IPSearch Interya', 'type' => 'string'],
            ['key' => 'login_max_attempts', 'value' => '5', 'type' => 'int'],
            ['key' => 'login_decay_seconds', 'value' => '300', 'type' => 'int'],
            ['key' => 'device_port', 'value' => '80', 'type' => 'int'],
            ['key' => 'device_scheme', 'value' => 'http', 'type' => 'string'],
        ];

        foreach ($defaults as $setting) {
            SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }

    private function seedSuperAdmin(): void
    {
        $email = env('IPS_ADMIN_EMAIL', 'admin@interya.com');
        $username = env('IPS_ADMIN_USERNAME', 'admin');
        $password = env('IPS_ADMIN_PASSWORD');
        $generated = false;

        if (! $password) {
            $password = Str::password(16, symbols: true);
            $generated = true;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super',
                'lastname' => 'Administrador',
                'username' => $username,
                'password' => Hash::make($password),
                'role' => 'superadmin',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        if (! $user->isSuperAdmin()) {
            $user->forceFill(['role' => 'superadmin', 'is_active' => true])->save();
        }

        $this->command?->info("Superadministrador: {$user->email} (usuario: {$user->username})");

        if ($generated && $user->wasRecentlyCreated) {
            $this->command?->warn("Contraseña generada automáticamente: {$password}");
            $this->command?->warn('Cámbiala después del primer inicio de sesión.');
        } elseif (! $user->wasRecentlyCreated) {
            $this->command?->line('El superadministrador ya existía; no se modificó su contraseña.');
        }
    }
}
