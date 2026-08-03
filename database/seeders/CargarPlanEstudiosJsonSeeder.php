<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\CursoUsil;
use App\Models\MallaCurricular;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga el plan de estudios USIL desde database/data/plan_estudios.json
 * hacia la estructura real (mallas_curriculares → ciclos → cursos_usil).
 *
 * Idempotente: solo llena las mallas que aún no tienen cursos, por lo que
 * no duplica los planes ya cargados.
 */
class CargarPlanEstudiosJsonSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('data/plan_estudios.json');
        if (! is_file($ruta)) {
            $this->command?->warn("No se encontró {$ruta}.");

            return;
        }

        $plan = json_decode(file_get_contents($ruta), true);
        if (! is_array($plan)) {
            $this->command?->warn('plan_estudios.json inválido.');

            return;
        }

        foreach ($plan as $nombreCarrera => $data) {
            $carrera = $this->buscarCarrera($nombreCarrera);
            if (! $carrera) {
                $this->command?->warn("Carrera no encontrada: {$nombreCarrera}");

                continue;
            }

            $malla = $this->mallaDe($carrera);
            if (! $malla) {
                $this->command?->warn("Sin malla para: {$nombreCarrera}");

                continue;
            }

            if ($malla->ciclos()->whereHas('cursos')->exists()) {
                $this->command?->line("· {$nombreCarrera}: ya tiene cursos, se omite.");

                continue;
            }

            $insertados = $this->cargar($malla, $data);
            $this->command?->info("✓ {$nombreCarrera}: {$insertados} cursos cargados en malla {$malla->id}.");
        }
    }

    private function buscarCarrera(string $nombre): ?Carrera
    {
        return Carrera::where('nombre', $nombre)->first()
            ?? Carrera::where('nombre', 'like', '%'.trim($nombre).'%')->first();
    }

    private function mallaDe(Carrera $carrera): ?MallaCurricular
    {
        return MallaCurricular::where('carrera_id', $carrera->id)
            ->orderByDesc('activa')->orderByDesc('anio')->orderByDesc('id')->first();
    }

    private function cargar(MallaCurricular $malla, array $data): int
    {
        return DB::transaction(function () use ($malla, $data) {
            $n = 0;

            // Obligatorios
            foreach ($data['obligatorios'] ?? [] as $c) {
                $this->crearCurso($malla, $c, false, ++$n);
            }

            // Electivos (agrupados por mención)
            foreach ($data['electivos'] ?? [] as $lista) {
                foreach ((array) $lista as $c) {
                    if (! is_array($c)) {
                        continue;
                    }
                    $this->crearCurso($malla, $c, true, ++$n);
                }
            }

            return $n;
        });
    }

    private function crearCurso(MallaCurricular $malla, array $c, bool $electivo, int $seq): void
    {
        $numeroCiclo = (int) ($c['ciclo'] ?? 1);

        $ciclo = Ciclo::firstOrCreate(
            ['malla_id' => $malla->id, 'numero' => $numeroCiclo],
            ['nombre' => "Ciclo {$numeroCiclo}"],
        );

        CursoUsil::create([
            'ciclo_id' => $ciclo->id,
            'codigo' => sprintf('M%d-%02d-%03d', $malla->id, $numeroCiclo, $seq),
            'nombre' => $c['curso'] ?? 'Curso',
            'creditos' => (float) ($c['cr'] ?? 0),
            'horas_teoria' => (int) ($c['th'] ?? 0),
            'horas_practica' => 0,
            'es_electivo' => $electivo,
            'tipo_curso' => 'teorico_practico',
        ]);
    }
}
