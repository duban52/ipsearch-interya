<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guarda de regresión visual: el sistema de temas usa variables CSS.
 * Cualquier superficie de color fijo ("bg-white", "table-light") rompe el
 * contraste en modo oscuro, porque el texto pasa a ser claro.
 */
class ViewThemeTest extends TestCase
{
    use RefreshDatabase;
    /**
     * @return array<int,string>
     */
    private function viewFiles(): array
    {
        $base = resource_path('views');
        $dirs = ['layouts', 'auth', 'admin', 'search', 'profile', 'partials'];
        $files = [];

        foreach ($dirs as $dir) {
            foreach (glob($base.DIRECTORY_SEPARATOR.$dir.'/*.blade.php') ?: [] as $file) {
                $files[] = $file;
            }
            foreach (glob($base.DIRECTORY_SEPARATOR.$dir.'/*/*.blade.php') ?: [] as $file) {
                $files[] = $file;
            }
        }

        return $files;
    }

    public function test_ninguna_vista_usa_superficies_de_color_fijo(): void
    {
        $infracciones = [];

        foreach ($this->viewFiles() as $file) {
            $contenido = file_get_contents($file);

            foreach (['bg-white', 'table-light'] as $clase) {
                if (str_contains($contenido, $clase)) {
                    $infracciones[] = basename($file)." usa {$clase}";
                }
            }
        }

        $this->assertSame([], $infracciones, 'Clases de superficie fija encontradas: '.implode(', ', $infracciones));
    }

    public function test_el_layout_define_el_tema_y_ofrece_selector(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('data-bs-theme="light"', $layout);
        $this->assertStringContainsString('[data-bs-theme="dark"]', $layout);
        $this->assertStringContainsString('id="themeToggle"', $layout);
        $this->assertStringContainsString('localStorage.setItem(\'theme\'', $layout);
    }

    public function test_todas_las_pantallas_de_autenticacion_comparten_el_selector_de_tema(): void
    {
        foreach (['login', 'forgot-password', 'reset-password', 'invite-accept'] as $vista) {
            $contenido = file_get_contents(resource_path("views/auth/{$vista}.blade.php"));

            $this->assertStringContainsString("partials.theme", $contenido, "La vista {$vista} no incluye el selector de tema.");
        }
    }

    public function test_el_pie_de_pagina_es_fijo_y_no_supera_75px(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        // Fijo al fondo del viewport.
        $this->assertMatchesRegularExpression('/\.app-footer\s*\{[^}]*position:\s*fixed/s', $layout);
        $this->assertMatchesRegularExpression('/\.app-footer\s*\{[^}]*bottom:\s*0/s', $layout);

        // Altura máxima solicitada.
        $this->assertMatchesRegularExpression('/\.app-footer\s*\{[^}]*max-height:\s*75px/s', $layout);

        // El contenido reserva espacio para que el pie no lo tape (6rem > 75px).
        $this->assertMatchesRegularExpression('/\.main\s*\{[^}]*padding:[^;]*6rem/s', $layout);
    }

    public function test_el_tema_por_defecto_es_claro(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        // El tema oscuro solo se activa por elección guardada, no por el sistema.
        $this->assertStringNotContainsString('prefers-color-scheme', $layout);
    }

    public function test_el_pie_de_pagina_aparece_alineado_a_la_derecha(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('todos los derechos reservados', false)
            ->assertSee('Duban Restrepo', false);

        // El contenedor lleva la clase de alineación a la derecha y tamaño pequeño.
        $this->assertMatchesRegularExpression(
            '/<footer class="[^"]*text-end[^"]*small[^"]*"/',
            (string) $response->getContent(),
        );
    }
}
