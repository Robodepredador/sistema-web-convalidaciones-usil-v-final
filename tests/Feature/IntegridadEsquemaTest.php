<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Restricciones de integridad del esquema (Fase 1 de la normalización).
 *
 * Cada prueba comprueba que la BASE DE DATOS rechaza un dato inválido, no que
 * la aplicación lo valide. Es la diferencia entre una regla y una costumbre:
 * la validación de Laravel se puede saltar con un seeder, un comando artisan o
 * una importación; una restricción de InnoDB no.
 */
class IntegridadEsquemaTest extends TestCase
{
    use RefreshDatabase;

    /** El estado de equivalencias vive en postulante_destinos, no duplicado en el padre. */
    public function test_postulantes_no_conserva_las_columnas_duplicadas_de_destinos(): void
    {
        foreach (['estado_equivalencias', 'equivalencias_revisado_por', 'equivalencias_revisado_en'] as $columna) {
            $this->assertFalse(
                Schema::hasColumn('postulantes', $columna),
                "postulantes.{$columna} duplica a postulante_destinos y debió eliminarse."
            );
        }
    }
}
