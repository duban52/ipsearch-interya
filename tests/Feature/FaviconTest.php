<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El favicon forma parte de la identidad y debe existir en todos los formatos
 * y estar referenciado en cada <head>.
 */
class FaviconTest extends TestCase
{
    public function test_existen_los_archivos_de_icono(): void
    {
        foreach ([
            'favicon.ico',
            'favicon.svg',
            'favicon-16x16.png',
            'favicon-32x32.png',
            'apple-touch-icon.png',
            'favicon-192x192.png',
            'favicon-512x512.png',
        ] as $file) {
            $path = public_path($file);

            $this->assertFileExists($path, "Falta {$file}");
            $this->assertGreaterThan(0, filesize($path), "{$file} está vacío");
        }
    }

    public function test_el_ico_es_un_icono_valido_y_multitamano(): void
    {
        $bytes = file_get_contents(public_path('favicon.ico'));

        // Cabecera ICO: reservado 0, tipo 1 (icono), número de imágenes.
        $this->assertSame("\x00\x00\x01\x00", substr($bytes, 0, 4));

        $count = unpack('v', substr($bytes, 4, 2))[1];
        $this->assertGreaterThanOrEqual(2, $count, 'El .ico debería incluir varios tamaños');
    }

    public function test_el_svg_es_xml_valido_y_usa_la_paleta_de_interya(): void
    {
        $svg = file_get_contents(public_path('favicon.svg'));

        $this->assertNotFalse(simplexml_load_string($svg), 'El SVG no es XML válido');
        $this->assertStringContainsString('#0071BC', $svg);
        $this->assertStringContainsString('#083A56', $svg);
        $this->assertStringContainsString('#FCEE21', $svg);
    }

    public function test_las_pantallas_referencian_el_favicon(): void
    {
        // Pantalla con layout principal
        $this->get('/login')
            ->assertOk()
            ->assertSee('favicon.svg', false)
            ->assertSee('apple-touch-icon.png', false)
            ->assertSee('theme-color', false);
    }

    public function test_todas_las_vistas_de_autenticacion_incluyen_el_parcial(): void
    {
        foreach (['login', 'forgot-password', 'reset-password', 'invite-accept'] as $vista) {
            $contenido = file_get_contents(resource_path("views/auth/{$vista}.blade.php"));

            $this->assertStringContainsString('partials.favicon', $contenido, "La vista {$vista} no incluye el favicon.");
        }
    }
}
