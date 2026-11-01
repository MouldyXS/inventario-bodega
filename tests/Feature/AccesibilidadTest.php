<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Salto al contenido y destino del salto (rediseño, etapa 8). El contraste, el foco y los
 * desbordes se auditan en el navegador (ver CLAUDE.md); aquí solo el marcado que los hace posibles.
 */
class AccesibilidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_pantalla_ofrece_saltar_al_contenido(): void
    {
        foreach ([$this->get(route('login')), $this->actingAs(User::factory()->create())->get(route('inicio'))] as $respuesta) {
            $html = $respuesta->assertOk()->getContent();

            // El enlace es lo primero del documento y apunta a un destino que puede recibir el foco.
            $this->assertMatchesRegularExpression('/<body[^>]*>\s*<a class="visually-hidden-focusable saltar-contenido" href="#contenido">Saltar al contenido<\/a>/', $html);
            $this->assertMatchesRegularExpression('/<main[^>]*id="contenido"[^>]*tabindex="-1"/', $html);
        }
    }

    public function test_la_navegacion_tiene_nombre_y_marca_la_pagina_actual(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('inventario.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<aside class="barra-lateral d-none d-xl-flex" aria-label="Menú principal">', $html);
        $this->assertStringContainsString('<nav class="barra-inferior d-xl-none" aria-label="Accesos rápidos">', $html);
        $this->assertMatchesRegularExpression('/class="enlace-menu\s+active\s*"[^>]*aria-current="page"/', $html);
    }
}
