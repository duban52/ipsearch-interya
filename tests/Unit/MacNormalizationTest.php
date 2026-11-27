<?php

namespace Tests\Unit;

use App\Services\MacAddressResolverService;
use PHPUnit\Framework\TestCase;

class MacNormalizationTest extends TestCase
{
    public function test_normaliza_formatos_habituales(): void
    {
        foreach (['AA:BB:CC:DD:EE:FF', 'aa-bb-cc-dd-ee-ff', 'aabbccddeeff', ' AA:BB:CC:DD:EE:FF '] as $input) {
            [$normalized, $error] = MacAddressResolverService::normalizeMac($input);

            $this->assertSame('AA:BB:CC:DD:EE:FF', $normalized, "Entrada no normalizada: {$input}");
            $this->assertNull($error);
        }
    }

    public function test_rechaza_longitudes_y_caracteres_invalidos(): void
    {
        foreach (['', 'AA:BB:CC', 'ZZ:BB:CC:DD:EE:FF', 'AABBCCDDEEFFF'] as $input) {
            [$normalized, $error] = MacAddressResolverService::normalizeMac($input);

            $this->assertSame('', $normalized);
            $this->assertNotNull($error);
        }
    }
}
