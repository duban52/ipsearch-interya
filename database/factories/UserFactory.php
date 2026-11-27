<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'username' => 'user_'.Str::lower(Str::random(10)),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password#2026'),
            'remember_token' => Str::random(10),
            'role' => 'user',
            'is_active' => true,
        ];
    }

    public function superadmin(): static
    {
        return $this->state(fn () => ['role' => 'superadmin']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'suspended_at' => now(),
            'suspended_reason' => 'Suspendido desde fábrica de pruebas.',
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
