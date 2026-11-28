<?php

namespace Tests\Feature;

use App\Models\MacSearch;
use App\Models\MikrotikRouter;
use App\Models\User;
use App\Services\Mikrotik\MikrotikRestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PaginationAndClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_paginacion_usa_bootstrap_y_no_tailwind(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 30) as $i) {
            MacSearch::create([
                'user_id' => $user->id,
                'mac_normalized' => sprintf('AA:00:00:00:00:%02d', $i),
                'mac_input' => 'x',
                'status' => 'no_results',
            ]);
        }

        $this->actingAs($user)->get('/search/history')
            ->assertOk()
            ->assertSee('page-link', false)
            ->assertDontSee('relative inline-flex', false);
    }

    public function test_el_cliente_real_consulta_varios_routers_en_una_pasada(): void
    {
        // Puerto cerrado en localhost: conexión rechazada al instante, sin red real.
        $make = fn (string $name, int $port) => MikrotikRouter::create([
            'name' => $name,
            'host' => '127.0.0.1',
            'api_port' => $port,
            'api_user' => 'api',
            'api_password' => Crypt::encryptString('x'),
            'use_ssl' => false,
            'verify_ssl' => false,
            'is_active' => true,
            'priority' => 1,
            'connect_timeout_ms' => 1000,
            'read_timeout_ms' => 2000,
        ]);

        $a = $make('A', 1);
        $b = $make('B', 9);
        $roto = $make('Credenciales rotas', 1);
        $roto->forceFill(['api_password' => 'no-cifrada'])->save();

        $out = app(MikrotikRestClient::class)->collectSourcesMany(
            MikrotikRouter::orderBy('id')->get(),
            'AA:BB:CC:DD:EE:FF',
        );

        $this->assertEqualsCanonicalizing([$a->id, $b->id, $roto->id], array_keys($out));
        // Windows reintenta el SYN en puertos cerrados y acaba en timeout; Linux rechaza al instante.
        $this->assertContains($out[$a->id]['error'], ['unreachable', 'timeout']);
        $this->assertContains($out[$b->id]['error'], ['unreachable', 'timeout']);
        $this->assertSame('auth', $out[$roto->id]['error'], 'Un router con credenciales ilegibles no debe tumbar a los demás.');
        $this->assertStringContainsString('?mac-address=AA%3ABB%3ACC%3ADD%3AEE%3AFF', $out[$a->id]['error_detail']);
    }
}
