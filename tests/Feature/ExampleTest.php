<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La raíz exige autenticación: sin sesión redirige al login.
     */
    public function test_la_raiz_redirige_al_login_sin_sesion(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
