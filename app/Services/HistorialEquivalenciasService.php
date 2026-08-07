<?php

namespace App\Services;

use App\Models\Convalidacion;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Base de conocimiento histórica: qué curso externo se convalidó con qué curso
 * USIL, y cuántas veces.
 *
 * No hay tabla ni caché que mantener. La evidencia se agrega en vivo sobre
 * `simulacion_detalle`, que es donde el sistema ya venía anotando cada
 * equivalencia decidida por un evaluador.
 *
 * Esta clase sigue siendo PURAMENTE DERIVADA y no consulta `equivalencias_malla`,
 * donde vive el criterio que el coordinador declara por adelantado. Las dos fuentes
 * se juntan más arriba, en `HistorialEquivalenciasController::antecedentes()`, y
 * mantenerlas separadas es deliberado: es lo que permite ver que se contradicen. Si
 * una absorbiera a la otra, esa señal —lo declarado contra lo practicado— dejaría de
 * ser observable.
 *
 * El servicio solo INFORMA. Nunca asigna un curso ni modifica una simulación:
 * el emparejamiento sigue siendo íntegramente manual.
 */
class HistorialEquivalenciasService
{
    /**
     * A partir de qué similitud se considera que dos nombres son el mismo curso.
     *
     * Más alto que el 0.55 de ConvalidacionEngine::asignacionOptima() porque aquí
     * no se busca "un curso parecido" sino "este mismo curso en otro expediente":
     * 0.8 admite «Matemática 1» ↔ «Matemática I» y descarta «Cálculo I» ↔
     * «Cálculo diferencial». Súbelo si aparecen antecedentes que no vienen a cuento.
     */
    public const UMBRAL_MISMO_CURSO = 0.8;

    public function __construct(private ConvalidacionEngine $engine) {}

    /**
     * Pares realmente decididos por un evaluador: fila convalidable, con destino
     * USIL, no excluida y de una simulación viva.
     *
     * @param  array|null  $carrerasPermitidas  null = sin restricción de alcance
     */
    private function base(?array $carrerasPermitidas): Builder
    {
        return DB::table('simulacion_detalle as sd')
            ->join('simulaciones as s', 's.id', '=', 'sd.simulacion_id')
            ->join('cursos_usil as cu', 'cu.id', '=', 'sd.curso_usil_id')
            ->join('carreras as c', 'c.id', '=', 's.carrera_usil_id')
            ->join('carreras_externas as ce', 'ce.id', '=', 's.carrera_externa_id')
            ->join('instituciones_externas as ie', 'ie.id', '=', 'ce.institucion_id')
            // Marca de autoridad: la simulación llegó a memorándum oficial.
            ->leftJoin('convalidaciones as cv', fn ($j) => $j
                ->on('cv.simulacion_id', '=', 's.id')
                ->where('cv.estado', '=', Convalidacion::CONFIRMADA))
            ->whereNull('s.deleted_at')
            ->whereNotNull('sd.curso_usil_id')
            ->where('sd.clasificacion', 'convalidable')
            ->where('sd.excluido', false)
            // Alcance (RF-40): el histórico expone decisiones de evaluación, así que
            // se limita por carrera USIL destino igual que el resto del módulo. El
            // lado de origen (universidad/carrera externa) no se restringe: sirve
            // ver cómo se resolvió el mismo curso viniendo de otra institución.
            ->when($carrerasPermitidas !== null,
                fn ($q) => $q->whereIn('s.carrera_usil_id', $carrerasPermitidas ?: [0]));
    }

    /**
     * Un renglón por (curso de origen → curso USIL) dentro de un contexto
     * universidad/carrera, con su frecuencia.
     *
     * `COUNT(DISTINCT)` y no `COUNT(*)`: el LEFT JOIN a convalidaciones no debe
     * poder inflar el conteo si algún día una simulación tuviera más de una.
     */
    private function agregado(?array $carrerasPermitidas): Builder
    {
        return $this->base($carrerasPermitidas)
            ->selectRaw('sd.curso_origen_nombre as origen_nombre,
                sd.curso_usil_id,
                cu.nombre as curso_usil,
                cu.codigo as codigo_usil,
                s.carrera_usil_id,
                c.nombre as carrera_usil,
                s.carrera_externa_id,
                ce.nombre as carrera_externa,
                ie.id as institucion_id,
                ie.nombre as institucion,
                COUNT(DISTINCT s.id) as veces,
                COUNT(DISTINCT cv.id) as confirmadas')
            ->groupBy('sd.curso_origen_nombre', 'sd.curso_usil_id', 'cu.nombre', 'cu.codigo',
                's.carrera_usil_id', 'c.nombre', 's.carrera_externa_id', 'ce.nombre',
                'ie.id', 'ie.nombre');
    }

    /**
     * Pares (curso de origen, carrera destino) resueltos con más de un curso USIL:
     * criterio dividido.
     *
     * Se cuenta por `cu.codigo` y no por `curso_usil_id` porque `cursos_usil` cuelga de
     * ciclo → malla → plan de estudio: un cambio de plan crea ids nuevos para los mismos
     * cursos, y por id cada actualización de malla parecería una divergencia.
     *
     * Agrupa por nombre exacto y no por similitud como `antecedentes()`, y es
     * deliberado: así la pantalla conserva paginación y filtros en SQL. La colación
     * `utf8mb4_unicode_ci` ya une mayúsculas y tildes; lo que se escapa son los
     * numerales («Matemática 1» vs «Matemática I»), de modo que la pantalla puede
     * quedarse CORTA —nunca de más— frente al panel del evaluador.
     */
    private function divergentes(?array $carrerasPermitidas): Builder
    {
        return $this->base($carrerasPermitidas)
            ->selectRaw('sd.curso_origen_nombre as nombre,
                s.carrera_usil_id as carrera,
                COUNT(DISTINCT cu.codigo) as criterios')
            ->groupBy('sd.curso_origen_nombre', 's.carrera_usil_id')
            ->havingRaw('COUNT(DISTINCT cu.codigo) > 1');
    }

    /**
     * Antecedentes de UN curso de origen, para el panel del espacio de trabajo.
     *
     * El nombre se compara con `similitud()` —el mismo comparador que ya sustenta
     * «Re-sugerir por similitud»— porque el histórico guarda el nombre tal cual lo
     * escribió cada evaluador: no hay id de curso externo al que agarrarse
     * (`curso_externo_id` viene nulo en todo el histórico).
     *
     * `criterios` cuenta cuántos cursos USIL distintos se han usado para este mismo
     * curso DENTRO de la carrera destino preguntada: ≥2 significa que no hay criterio
     * establecido. Es `null` si no se pregunta por una carrera concreta, porque entre
     * carreras la malla es otra y divergir ahí es lo normal. Se cuenta por `codigo` y
     * no por id: un cambio de plan crea ids nuevos para los mismos cursos.
     *
     * La otra mitad de esta misma regla vive en `divergentes()`, que la resuelve en SQL
     * para la pantalla. Las dos definiciones deben leerse juntas.
     *
     * La lista va ordenada por afinidad de contexto.
     *
     * @return array{antecedentes: array<int,array<string,mixed>>, criterios: int|null}
     */
    public function antecedentes(
        string $nombreOrigen,
        ?int $carreraUsilId = null,
        ?int $carreraExternaId = null,
        ?array $carrerasPermitidas = null,
    ): array {
        $nombreOrigen = trim($nombreOrigen);
        if ($nombreOrigen === '') {
            return ['antecedentes' => [], 'criterios' => null];
        }

        // 1) Qué nombres del histórico son este mismo curso.
        // ponytail: la comparación se hace en PHP sobre TODOS los nombres distintos
        // del alcance. Con miles de nombres son unos pocos ms; si algún día pasa de
        // ~50k, el camino es acotar por carrera destino antes de comparar, o guardar
        // el nombre normalizado en una columna indexada.
        $equivalentes = $this->base($carrerasPermitidas)
            ->distinct()
            ->pluck('sd.curso_origen_nombre')
            ->filter(fn ($h) => $this->engine->similitud($nombreOrigen, (string) $h) >= self::UMBRAL_MISMO_CURSO)
            ->values();

        if ($equivalentes->isEmpty()) {
            return ['antecedentes' => [], 'criterios' => null];
        }

        // 2) Frecuencia de cada destino que se les dio.
        $filas = $this->agregado($carrerasPermitidas)
            ->whereIn('sd.curso_origen_nombre', $equivalentes->all())
            ->get();

        // 3) Primero lo más comparable: mismo destino USIL pesa más que misma
        //    universidad de origen. Se ordena en PHP porque son un puñado de filas
        //    y así la regla queda legible en un sitio.
        $afinidad = fn ($f) => ($carreraUsilId && (int) $f->carrera_usil_id === $carreraUsilId ? 2 : 0)
            + ($carreraExternaId && (int) $f->carrera_externa_id === $carreraExternaId ? 1 : 0);

        $lista = $filas
            ->sortByDesc(fn ($f) => [$afinidad($f), (int) $f->veces, (int) $f->confirmadas])
            ->map(fn ($f) => [
                'curso_usil_id' => (int) $f->curso_usil_id,
                'curso_usil' => $f->curso_usil,
                'codigo_usil' => $f->codigo_usil,
                'origen_nombre' => $f->origen_nombre,
                'institucion' => $f->institucion,
                'carrera_externa' => $f->carrera_externa,
                'carrera_usil' => $f->carrera_usil,
                'veces' => (int) $f->veces,
                'confirmadas' => (int) $f->confirmadas,
                'mismo_destino' => $carreraUsilId !== null && (int) $f->carrera_usil_id === $carreraUsilId,
                'mismo_origen' => $carreraExternaId !== null && (int) $f->carrera_externa_id === $carreraExternaId,
            ])
            ->values();

        return [
            'antecedentes' => $lista->all(),
            'criterios' => $carreraUsilId === null ? null
                : $lista->where('mismo_destino', true)->unique('codigo_usil')->count(),
        ];
    }

    /**
     * Consulta libre del histórico para la pantalla de referencia.
     *
     * Devuelve el Builder sin ejecutar para que quien llama decida entre paginar
     * (pantalla) y traer todo (exportación) sin duplicar los filtros.
     *
     * @param  array{q?:string,institucion_id?:mixed,carrera_externa_id?:mixed,carrera_usil_id?:mixed,curso_usil_id?:mixed,solo_divergentes?:bool}  $filtros
     */
    public function consulta(array $filtros, ?array $carrerasPermitidas): Builder
    {
        $q = trim((string) ($filtros['q'] ?? ''));
        $divergentes = (bool) ($filtros['solo_divergentes'] ?? false);

        return $this->agregado($carrerasPermitidas)
            // Apagado, la consulta es exactamente la de siempre: ni join ni columna extra.
            ->when($divergentes, fn ($qq) => $qq
                ->joinSub($this->divergentes($carrerasPermitidas), 'dv', fn ($j) => $j
                    ->on('dv.nombre', '=', 'sd.curso_origen_nombre')
                    ->on('dv.carrera', '=', 's.carrera_usil_id'))
                ->addSelect('dv.criterios')
                // MySQL con ONLY_FULL_GROUP_BY no deduce la dependencia funcional a
                // través de la subconsulta, aunque (nombre, carrera) ya estén agrupados.
                ->groupBy('dv.criterios'))
            // Un solo cuadro de búsqueda para los dos lados de la equivalencia:
            // el evaluador busca «Matemática» sin saber si es el nombre de origen
            // o el de la malla USIL.
            ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w
                ->where('sd.curso_origen_nombre', 'like', "%{$q}%")
                ->orWhere('cu.nombre', 'like', "%{$q}%")))
            ->when($filtros['institucion_id'] ?? null, fn ($qq, $v) => $qq->where('ie.id', $v))
            ->when($filtros['carrera_externa_id'] ?? null, fn ($qq, $v) => $qq->where('s.carrera_externa_id', $v))
            ->when($filtros['carrera_usil_id'] ?? null, fn ($qq, $v) => $qq->where('s.carrera_usil_id', $v))
            ->when($filtros['curso_usil_id'] ?? null, fn ($qq, $v) => $qq->where('sd.curso_usil_id', $v))
            // Revisando divergencias manda lo más repartido, y las filas del mismo curso
            // quedan contiguas para poder compararlas de un vistazo.
            ->when($divergentes,
                fn ($qq) => $qq->orderByDesc('dv.criterios')
                    ->orderBy('sd.curso_origen_nombre')
                    ->orderByDesc('veces'),
                fn ($qq) => $qq->orderByDesc('veces')
                    ->orderByDesc('confirmadas')
                    ->orderBy('sd.curso_origen_nombre'));
    }
}
