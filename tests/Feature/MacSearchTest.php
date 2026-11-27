<?php

namespace Tests\Feature;

use App\Models\MacSearch;
use App\Models\MikrotikRouter;
use App\Models\User;
use App\Services\Mikrotik\MikrotikRestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MacSearchTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSources(array $overrides = []): array
    {
        return array_merge([
            'dhcp' => [],
            'ppp' => [],
            'arp' => [],
            'hotspot_binding' => [],
            'error' => null,
            'partial' => false,
        ], $overrides);
    }

    private function router(array $attributes = []): MikrotikRouter
    {
        return MikrotikRouter::create(array_merge([
            'name' => 'Router Centro',
            'host' => '10.10.10.1',
            'api_port' => 8729,
            'api_user' => 'api-lectura',
            'api_password' => encrypt('secreto'),
            'use_ssl' => true,
            'verify_ssl' => true,
            'municipality' => 'Medellín',
            'is_active' => true,
            'priority' => 1,
            'connect_timeout_ms' => 1000,
            'read_timeout_ms' => 2000,
        ], $attributes));
    }

    public function test_sin_routers_activos_devuelve_estado_no_routers(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/search', ['mac' => 'AA:BB:CC:DD:EE:FF']);

        $response->assertOk()
            ->assertJsonPath('status', 'no_routers')
            ->assertJsonPath('count', 0);

        $this->assertDatabaseHas('mac_searches', [
            'user_id' => $user->id,
            'mac_normalized' => 'AA:BB:CC:DD:EE:FF',
            'status' => 'no_routers',
        ]);
    }

    public function test_una_mac_invalida_devuelve_error_de_validacion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/search', ['mac' => 'XYZ'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mac');

        $this->assertSame(0, MacSearch::count());
    }

    public function test_una_coincidencia_dhcp_se_persiste_con_su_mecanismo(): void
    {
        $this->router();

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSources')
            ->once()
            ->andReturn($this->fakeSources([
                'dhcp' => [[
                    'address' => '100.64.10.25',
                    'mac-address' => 'AA:BB:CC:DD:EE:FF',
                    'host-name' => 'cliente-centro',
                    'server' => 'dhcp-lan',
                    'dynamic' => 'true',
                    'status' => 'bound',
                ]],
            ]));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/search', ['mac' => 'aabbccddeeff']);

        $response->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('results.0.ip_address', '100.64.10.25')
            ->assertJsonPath('results.0.mechanism', 'dhcp_active')
            ->assertJsonPath('results.0.hostname', 'cliente-centro');

        $this->assertDatabaseHas('mac_search_results', [
            'ip_address' => '100.64.10.25',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'mechanism' => 'dhcp_active',
            'municipality' => 'Medellín',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'mac_search', 'result' => 'success']);
    }

    public function test_pppoe_y_arp_de_la_misma_ip_se_consolidan_en_un_solo_dispositivo(): void
    {
        $this->router();

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSources')
            ->once()
            ->andReturn($this->fakeSources([
                'ppp' => [['name' => 'cliente1', 'address' => '10.20.0.5', 'caller-id' => 'AA:BB:CC:DD:EE:FF']],
                'arp' => [['address' => '10.20.0.5', 'mac-address' => 'AA:BB:CC:DD:EE:FF', 'interface' => 'ether1', 'complete' => 'true']],
            ]));

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/search', ['mac' => 'AA-BB-CC-DD-EE-FF']);

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('results.0.ip_address', '10.20.0.5')
            ->assertJsonPath('results.0.mechanism', 'pppoe_active');

        $mechanisms = $response->json('results.0.mechanisms');
        sort($mechanisms);

        $this->assertSame(['arp_complete', 'pppoe_active'], $mechanisms);

        // Se persiste una sola fila por dispositivo, con ambos mecanismos.
        $this->assertSame(1, \App\Models\MacSearchResult::count());
        $this->assertSame(['pppoe_active', 'arp_complete'], \App\Models\MacSearchResult::first()->mechanisms);
    }

    public function test_dhcp_y_arp_de_la_misma_ip_priorizan_dhcp_como_mecanismo(): void
    {
        $this->router();

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSources')
            ->once()
            ->andReturn($this->fakeSources([
                'dhcp' => [[
                    'address' => '100.64.10.25',
                    'mac-address' => 'AA:BB:CC:DD:EE:FF',
                    'host-name' => 'cliente-centro',
                    'dynamic' => 'true',
                    'status' => 'bound',
                ]],
                'arp' => [['address' => '100.64.10.25', 'mac-address' => 'AA:BB:CC:DD:EE:FF', 'complete' => 'true']],
            ]));

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/search', ['mac' => 'AA:BB:CC:DD:EE:FF']);

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('results.0.mechanism', 'dhcp_active')
            ->assertJsonPath('results.0.hostname', 'cliente-centro')
            ->assertJsonPath('results.0.is_active_match', true);

        $this->assertSame(1, \App\Models\MacSearchResult::count());
    }

    public function test_dos_ips_distintas_devuelven_dos_dispositivos_con_el_activo_primero(): void
    {
        $this->router();

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSources')
            ->once()
            ->andReturn($this->fakeSources([
                // ARP histórico (incompleto) en una IP y DHCP activo en otra.
                'arp' => [['address' => '10.0.0.50', 'mac-address' => 'AA:BB:CC:DD:EE:FF', 'complete' => 'false']],
                'dhcp' => [[
                    'address' => '100.64.10.99',
                    'mac-address' => 'AA:BB:CC:DD:EE:FF',
                    'dynamic' => 'true',
                    'status' => 'bound',
                ]],
            ]));

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/search', ['mac' => 'AA:BB:CC:DD:EE:FF']);

        $response->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('results.0.ip_address', '100.64.10.99')
            ->assertJsonPath('results.0.is_active_match', true)
            ->assertJsonPath('results.1.ip_address', '10.0.0.50');
    }

    public function test_un_binding_de_hotspot_se_interpreta_como_mecanismo_propio(): void
    {
        $this->router(['name' => 'Router Hotspot']);

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSources')
            ->once()
            ->andReturn($this->fakeSources([
                'hotspot_binding' => [[
                    'mac-address' => 'AA:BB:CC:DD:EE:FF',
                    'address' => '192.168.88.50',
                    'to-address' => '0.0.0.0',
                    'type' => 'bypassed',
                    'server' => 'hotspot1',
                ]],
            ]));

        $this->actingAs(User::factory()->create())
            ->postJson('/search', ['mac' => 'AA:BB:CC:DD:EE:FF'])
            ->assertOk()
            ->assertJsonPath('results.0.mechanism', 'hotspot_binding')
            ->assertJsonPath('results.0.ip_address', '192.168.88.50')
            ->assertJsonPath('results.0.is_active_match', true);
    }

    public function test_una_coincidencia_sin_ip_no_provoca_error_500(): void
    {
        $this->router();

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSources')
            ->once()
            ->andReturn($this->fakeSources([
                'arp' => [
                    ['address' => null, 'mac-address' => 'AA:BB:CC:DD:EE:FF', 'complete' => 'false'],
                    ['address' => '10.0.0.9', 'mac-address' => 'AA:BB:CC:DD:EE:FF', 'complete' => 'true'],
                ],
            ]));

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/search', ['mac' => 'AA:BB:CC:DD:EE:FF']);

        $response->assertOk()->assertJsonPath('count', 1);
        $this->assertSame('10.0.0.9', $response->json('results.0.ip_address'));
    }

    public function test_un_router_inalcanzable_marca_estado_error_y_no_rompe_la_busqueda(): void
    {
        $this->router();

        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSources')
            ->once()
            ->andReturn($this->fakeSources(['error' => 'unreachable']));

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/search', ['mac' => 'AA:BB:CC:DD:EE:FF']);

        $response->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('count', 0)
            ->assertJsonPath('failures.0.error', 'unreachable');

        $this->assertDatabaseHas('mikrotik_routers', [
            'name' => 'Router Centro',
            'connectivity_status' => 'fail',
        ]);
    }

    public function test_un_endpoint_no_disponible_no_invalida_el_router(): void
    {
        $this->router();

        // partial = true: PPP no configurado, pero DHCP respondió.
        $this->mock(MikrotikRestClient::class)
            ->shouldReceive('collectSources')
            ->once()
            ->andReturn($this->fakeSources([
                'partial' => true,
                'dhcp' => [['address' => '10.30.0.7', 'mac-address' => 'AA:BB:CC:DD:EE:FF', 'dynamic' => 'false', 'status' => 'bound']],
            ]));

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/search', ['mac' => 'AA:BB:CC:DD:EE:FF']);

        $response->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('reachable', 1)
            ->assertJsonPath('results.0.mechanism', 'dhcp_static');
    }

    public function test_el_historial_solo_muestra_las_busquedas_propias(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        MacSearch::create([
            'user_id' => $user->id,
            'mac_normalized' => 'AA:00:00:00:00:01',
            'mac_input' => 'AA0000000001',
            'status' => 'completed',
            'result_count' => 1,
        ]);

        MacSearch::create([
            'user_id' => $otro->id,
            'mac_normalized' => 'BB:00:00:00:00:02',
            'mac_input' => 'BB0000000002',
            'status' => 'completed',
            'result_count' => 1,
        ]);

        $this->actingAs($user)->get('/search/history')
            ->assertOk()
            ->assertSee('AA:00:00:00:00:01')
            ->assertDontSee('BB:00:00:00:00:02');
    }

    public function test_no_se_puede_ver_el_detalle_de_una_busqueda_ajena(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $search = MacSearch::create([
            'user_id' => $otro->id,
            'mac_normalized' => 'CC:00:00:00:00:03',
            'mac_input' => 'CC0000000003',
            'status' => 'completed',
        ]);

        $this->actingAs($user)->get('/search/'.$search->id)->assertNotFound();
    }
}
