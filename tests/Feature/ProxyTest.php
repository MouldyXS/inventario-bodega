<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La aplicación detrás de `tailscale serve` (Requerimientos §6.3): las
 * cabeceras X-Forwarded-* solo se aceptan desde 127.0.0.1.
 */
class ProxyTest extends TestCase
{
    private const CABECERAS_TAILSCALE = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'bodega-servidor.ejemplo.ts.net',
        'X-Forwarded-Port' => '443',
    ];

    public function test_desde_el_proxy_local_los_enlaces_salen_en_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(self::CABECERAS_TAILSCALE)
            ->get('/login')
            ->assertOk()
            ->assertSee('action="https://bodega-servidor.ejemplo.ts.net/login"', false);
    }

    public function test_las_redirecciones_desde_el_proxy_van_a_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(self::CABECERAS_TAILSCALE)
            ->get('/')
            ->assertRedirect('https://bodega-servidor.ejemplo.ts.net/login');
    }

    public function test_desde_otra_direccion_las_cabeceras_se_ignoran(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->withHeaders(self::CABECERAS_TAILSCALE)
            ->get('/login')
            ->assertOk()
            ->assertDontSee('https://bodega-servidor.ejemplo.ts.net', false);
    }
}
