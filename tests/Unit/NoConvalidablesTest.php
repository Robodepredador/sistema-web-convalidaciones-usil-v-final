<?php

namespace Tests\Unit;

use App\Models\CursoNoConvalidable;
use App\Services\ConvalidacionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Política de cursos no convalidables: vive entera en la tabla gestionable, y
 * la coincidencia es por palabra completa (antes bastaba con que la clave
 * apareciera como sílaba dentro del nombre).
 */
class NoConvalidablesTest extends TestCase
{
    use RefreshDatabase;

    private ConvalidacionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(ConvalidacionEngine::class);
        CursoNoConvalidable::limpiarCache();
    }

    /** La migración trasladó a la BD lo que estaba fijo en el código. */
    public function test_la_politica_del_codigo_vive_ahora_en_la_tabla(): void
    {
        foreach (['ingles', 'practica', 'arte', 'ofimatica', 'empleabilidad'] as $clave) {
            $this->assertContains($clave, CursoNoConvalidable::clavesActivas(), "Falta la clave «{$clave}».");
        }
    }

    public function test_descarta_los_cursos_de_las_categorias_excluidas(): void
    {
        $this->assertTrue($this->engine->esNoConvalidable('Inglés Intermedio'));
        $this->assertTrue($this->engine->esNoConvalidable('Prácticas Preprofesionales'));
        $this->assertTrue($this->engine->esNoConvalidable('Apreciación Artística'));
        $this->assertTrue($this->engine->esNoConvalidable('Educación Física'));
    }

    /** El defecto que corrige la coincidencia por palabra completa. */
    public function test_no_descarta_cursos_que_solo_contienen_la_clave_como_silaba(): void
    {
        $this->assertFalse($this->engine->esNoConvalidable('Gestión de Cartera'), '«cartera» contiene «arte».');
        $this->assertFalse($this->engine->esNoConvalidable('Descarte de Hipótesis'), '«descarte» contiene «arte».');
        $this->assertFalse($this->engine->esNoConvalidable('Contabilidad de Costos'));
    }

    /** Desactivar una clave desde Configuración cambia la política, sin desplegar. */
    public function test_desactivar_una_clave_la_saca_de_la_politica(): void
    {
        $this->assertTrue($this->engine->esNoConvalidable('Inglés Técnico'));

        CursoNoConvalidable::where('clave_normalizada', 'ingles')->update(['activo' => false]);
        CursoNoConvalidable::limpiarCache();

        $this->assertFalse($this->engine->esNoConvalidable('Inglés Técnico'));
    }
}
