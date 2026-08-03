<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\CursoExterno;
use App\Models\InstitucionExterna;
use App\Models\Postulante;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PostulanteSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@usil.edu.pe')->first();
        $isi = Carrera::where('codigo', 'ISI')->first();
        $unmsm = InstitucionExterna::where('nombre', 'Universidad Nacional Mayor de San Marcos')->first();
        $carreraExt = CarreraExterna::where('nombre', 'Ingeniería de Software')->first();
        // Segunda carrera USIL para demostrar múltiples destinos (una o más simulaciones).
        $otraCarrera = Carrera::where('activo', true)->where('id', '!=', $isi?->id)->orderBy('id')->first();

        // Récord histórico (transcript) de la carrera de origen para el emparejamiento.
        if ($carreraExt) {
            $this->sembrarRecordExterno($carreraExt->id);
        }

        // [tipo, doc, nombres, apPat, apMat, genero, email, estado, estado_equivalencias, docs_completos]
        $demo = [
            ['DNI', '70123456', 'Lucía', 'Ramírez', 'Quispe', 'femenino', 'lucia.ramirez@example.com', 'en_evaluacion', 'en_revision', true],
            ['DNI', '72654321', 'Carlos', 'Mendoza', 'Torres', 'masculino', 'carlos.mendoza@example.com', 'nuevo', 'pendiente', true],
            ['CE', '001234567', 'María', 'Flores', 'Núñez', 'femenino', 'maria.flores@example.com', 'admitido', 'pendiente', true],
        ];

        foreach ($demo as $n => [$tipo, $doc, $nombres, $apPat, $apMat, $sexo, $email, $estado, $estadoEq, $docsCompletos]) {
            $postulante = Postulante::updateOrCreate(
                ['tipo_documento' => $tipo, 'numero_documento' => $doc],
                [
                    'codigo' => 'POST-2026-9000'.($n + 1),
                    'password_hash' => Hash::make('Postulante#2026'),
                    'acceso_habilitado' => true,
                    'nombres' => $nombres,
                    'apellido_paterno' => $apPat,
                    'apellido_materno' => $apMat,
                    'genero' => $sexo,
                    'nacionalidad' => 'Peruana',
                    'email' => $email,
                    'institucion_origen_id' => $unmsm?->id,
                    'carrera_externa_id' => $carreraExt?->id,
                    'carrera_destino_id' => $isi?->id,
                    'ciclo_postulacion' => '2026-1',
                    'estado' => $estado,
                    'estado_equivalencias' => $estadoEq,
                    'usuario_id' => $admin?->id,
                ]
            );

            // Documentos del expediente (solo metadatos demo, sin archivo físico).
            if ($docsCompletos) {
                foreach (['certificado', 'silabos', 'constancia'] as $tipoDoc) {
                    $postulante->documentos()->firstOrCreate(
                        ['tipo' => $tipoDoc],
                        [
                            'nombre_original' => "{$tipoDoc}-{$doc}.pdf",
                            'ruta' => "postulantes/{$postulante->id}/{$tipoDoc}-demo.pdf",
                            'tamano' => 102400,
                        ]
                    );
                }
            }

            // Destino(s) USIL solicitados. El primario conserva el estado de revisión.
            if ($isi) {
                $postulante->destinos()->updateOrCreate(
                    ['carrera_id' => $isi->id],
                    ['estado_equivalencias' => $estadoEq]
                );
            }
            // El primer postulante solicita además una segunda carrera (varias simulaciones).
            if ($n === 0 && $otraCarrera) {
                $postulante->destinos()->firstOrCreate(['carrera_id' => $otraCarrera->id]);
            }
        }

        $this->command->info('Postulantes demo: 3 registrados con documentos, destinos y estado de equivalencias.');
    }

    /**
     * Récord académico (transcript) de la carrera de origen: cursos que el
     * postulante cursó y que se emparejan contra la malla USIL.
     */
    private function sembrarRecordExterno(int $carreraExternaId): void
    {
        $cursos = [
            ['EXT-PRG1', 'Introducción a la Programación', 4],
            ['EXT-PRG2', 'Programación Orientada a Objetos', 4],
            ['EXT-PRG3', 'Estructuras de Datos', 4],
            ['EXT-PRG4', 'Algoritmos Avanzados', 3],
            ['EXT-MAT1', 'Matemática Básica', 4],
            ['EXT-MAT2', 'Cálculo Diferencial', 4],
            ['EXT-MAT3', 'Cálculo Integral', 4],
            ['EXT-MAT4', 'Álgebra Lineal', 3],
            ['EXT-MAT5', 'Matemática Discreta', 3],
            ['EXT-EST1', 'Estadística Descriptiva', 3],
            ['EXT-EST2', 'Estadística Inferencial', 3],
            ['EXT-FIS1', 'Física General', 4],
            ['EXT-FIS2', 'Electricidad y Magnetismo', 4],
            ['EXT-BD1', 'Bases de Datos I', 4],
            ['EXT-BD2', 'Bases de Datos II', 4],
            ['EXT-WEB1', 'Desarrollo Web Frontend', 3],
            ['EXT-WEB2', 'Desarrollo Web Backend', 4],
            ['EXT-MOV', 'Desarrollo de Aplicaciones Móviles', 3],
            ['EXT-SO', 'Sistemas Operativos', 4],
            ['EXT-RED1', 'Redes de Computadoras', 4],
            ['EXT-RED2', 'Seguridad de Redes', 3],
            ['EXT-ARQ', 'Arquitectura de Computadoras', 3],
            ['EXT-ISW1', 'Ingeniería de Software I', 4],
            ['EXT-ISW2', 'Ingeniería de Software II', 4],
            ['EXT-REQ', 'Análisis de Requerimientos', 3],
            ['EXT-PMI', 'Gestión de Proyectos de TI', 3],
            ['EXT-CAL', 'Calidad de Software', 3],
            ['EXT-TST', 'Pruebas de Software', 3],
            ['EXT-IA', 'Inteligencia Artificial', 4],
            ['EXT-ML', 'Machine Learning', 3],
            ['EXT-DAT', 'Ciencia de Datos', 3],
            ['EXT-CLD', 'Computación en la Nube', 3],
            ['EXT-DOP', 'DevOps e Integración Continua', 3],
            ['EXT-COM1', 'Comunicación Efectiva', 2],
            ['EXT-COM2', 'Redacción Técnica', 2],
            ['EXT-ETI', 'Ética Profesional', 2],
            ['EXT-EMP', 'Emprendimiento e Innovación', 3],
            ['EXT-ECO', 'Economía para Ingenieros', 3],
            ['EXT-CON', 'Contabilidad General', 3],
            ['EXT-ING', 'Inglés Técnico', 2],
            ['EXT-INV', 'Metodología de la Investigación', 3],
        ];

        foreach ($cursos as [$codigo, $nombre, $creditos]) {
            CursoExterno::firstOrCreate(
                ['carrera_externa_id' => $carreraExternaId, 'codigo' => $codigo],
                ['nombre' => $nombre, 'creditos' => $creditos]
            );
        }
    }
}
