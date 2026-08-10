<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportarMallaRequest;
use App\Jobs\ImportarMallaExcel;
use App\Models\CargaMasiva;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\MallaCurricular;
use App\Services\AlcanceService;
use App\Services\AuditoriaService;
use App\Services\LectorMallaExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Carga masiva de mallas por Excel (RF-08..12).
 *
 * Todo punto de entrada comprueba el alcance por carrera (RF-40). Antes no lo
 * hacía ninguno: `carrera_id` llega por el cuerpo de la petición, así que un
 * coordinador restringido a una carrera podía importar la malla de cualquier
 * otra y, marcándola activa, desactivar el plan de estudios vigente de una
 * carrera ajena.
 */
class MallaImportController extends Controller
{
    public function create(Request $request)
    {
        return inertia('Mallas/Importar', [
            'carreras' => $this->carrerasDelUsuario($request),
        ]);
    }

    /** Carreras activas dentro del alcance del usuario. */
    private function carrerasDelUsuario(Request $request)
    {
        $visibles = AlcanceService::carrerasVisibles($request->user());

        return Carrera::where('activo', true)
            ->when($visibles !== null, fn ($q) => $q->whereIn('id', $visibles ?: [0]))
            ->orderBy('nombre')->get(['id', 'nombre']);
    }

    /**
     * La carga pertenece a la carrera de su malla. Sin esto, `estado` y
     * `progreso` dejaban consultar el avance —y los mensajes de error, que
     * citan nombres de curso— de las importaciones de cualquier otro.
     */
    private function autorizarCarga(Request $request, CargaMasiva $carga): void
    {
        $carga->loadMissing('malla');

        AlcanceService::autorizarCarrera($request->user(), $carga->malla?->carrera_id);
    }

    /**
     * Lee el archivo y muestra la pantalla de revisión (nada se guarda todavía).
     * El sistema propone un código por curso y separa las menciones de los ciclos.
     */
    public function previsualizar(Request $request, LectorMallaExcel $lector): RedirectResponse|Response
    {
        $datos = $request->validate([
            'carrera_id' => ['required', 'exists:carreras,id'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'version' => ['required', 'string', 'max:20'],
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_id']);

        $carrera = Carrera::findOrFail($datos['carrera_id']);

        try {
            $parsed = $lector->parse($request->file('archivo')->getRealPath(), $carrera->nombre, $carrera->codigo);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo leer el archivo: '.$e->getMessage());
        }

        if ($parsed['resumen']['cursos'] === 0) {
            return back()->with('error', 'El archivo no contiene cursos reconocibles. Verifica el formato (Ciclo · Curso · CR · TH · Pre-requisito).');
        }

        return inertia('Mallas/RevisarImportacion', [
            'carrera' => ['id' => $carrera->id, 'nombre' => $carrera->nombre, 'codigo' => $carrera->codigo],
            'cabecera' => [
                'anio' => $parsed['meta']['anio'] ?? (int) $datos['anio'],
                'version' => $parsed['meta']['version'] ?? $datos['version'],
                'facultad' => $parsed['meta']['facultad'],
            ],
            'ciclos' => $parsed['ciclos'],
            'menciones' => $parsed['menciones'],
            'resumen' => $parsed['resumen'],
            'hoja' => $parsed['hoja'],
        ]);
    }

    /**
     * Guarda la malla ya revisada y corregida por el usuario (ciclos + menciones).
     */
    public function guardarRevisada(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'carrera_id' => ['required', 'exists:carreras,id'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'version' => ['required', 'string', 'max:20',
                Rule::unique('mallas_curriculares', 'version')
                    ->where(fn ($q) => $q->where('carrera_id', $request->carrera_id)->where('anio', $request->anio))],
            'modalidad' => ['required', 'in:presencial,hibrido,virtual'],
            'periodo' => ['nullable', 'string', 'max:10'],
            'activa' => ['boolean'],

            'ciclos' => ['array'],
            'ciclos.*.numero' => ['required', 'integer', 'min:1', 'max:14'],
            'ciclos.*.cursos' => ['array'],
            'ciclos.*.cursos.*.codigo' => ['required', 'string', 'max:30'],
            'ciclos.*.cursos.*.nombre' => ['required', 'string', 'max:200'],
            'ciclos.*.cursos.*.creditos' => ['required', 'numeric', 'min:0.5', 'max:30'],
            'ciclos.*.cursos.*.horas' => ['nullable', 'numeric', 'min:0', 'max:40'],
            'ciclos.*.cursos.*.prerequisito' => ['nullable', 'string', 'max:255'],
            'ciclos.*.cursos.*.es_electivo' => ['boolean'],
            'ciclos.*.cursos.*.convalidable' => ['boolean'],

            'menciones' => ['array'],
            'menciones.*.nombre' => ['required', 'string', 'max:150'],
            'menciones.*.cursos' => ['array'],
            'menciones.*.cursos.*.codigo' => ['required', 'string', 'max:30'],
            'menciones.*.cursos.*.nombre' => ['required', 'string', 'max:200'],
            'menciones.*.cursos.*.ciclo' => ['required', 'integer', 'min:1', 'max:14'],
            'menciones.*.cursos.*.creditos' => ['required', 'numeric', 'min:0.5', 'max:30'],
            'menciones.*.cursos.*.horas' => ['nullable', 'numeric', 'min:0', 'max:40'],
            'menciones.*.cursos.*.prerequisito' => ['nullable', 'string', 'max:255'],
            'menciones.*.cursos.*.es_electivo' => ['boolean'],
            'menciones.*.cursos.*.convalidable' => ['boolean'],
        ], [
            'version.unique' => 'Ya existe una malla para esa carrera, año y versión (RN-01 / RN-03).',
        ]);

        // Antes de tocar nada: marcar esta malla activa desactiva las demás de la
        // carrera, así que registrar en una carrera ajena tumbaba su plan vigente.
        AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_id']);

        $totalCursos = collect($datos['ciclos'] ?? [])->sum(fn ($c) => count($c['cursos'] ?? []))
            + collect($datos['menciones'] ?? [])->sum(fn ($m) => count($m['cursos'] ?? []));
        abort_if($totalCursos === 0, 422, 'La malla no tiene cursos que registrar.');

        $malla = DB::transaction(function () use ($datos, $request) {
            // RN-02: si esta malla se marca activa, desactivar las demás de la carrera.
            if (! empty($datos['activa'])) {
                MallaCurricular::where('carrera_id', $datos['carrera_id'])->update(['activa' => false]);
            }

            $malla = MallaCurricular::create([
                'carrera_id' => $datos['carrera_id'],
                'anio' => $datos['anio'],
                'version' => $datos['version'],
                'modalidad' => $datos['modalidad'],
                'periodo' => $datos['periodo'] ?? null,
                'activa' => $datos['activa'] ?? false,
                'origen_carga' => 'excel',
                'usuario_id' => $request->user()->id,
            ]);

            // Cursos del plan regular.
            foreach ($datos['ciclos'] ?? [] as $c) {
                $ciclo = $malla->ciclos()->firstOrCreate(['numero' => $c['numero']]);
                foreach ($c['cursos'] ?? [] as $cu) {
                    $this->crearCurso($ciclo, $cu, null);
                }
            }

            // Cursos de mención (cada curso mantiene su número de ciclo).
            foreach ($datos['menciones'] ?? [] as $m) {
                foreach ($m['cursos'] ?? [] as $cu) {
                    $ciclo = $malla->ciclos()->firstOrCreate(['numero' => $cu['ciclo']]);
                    $this->crearCurso($ciclo, $cu, $m['nombre']);
                }
            }

            return $malla;
        });

        AuditoriaService::registrar('crear', 'mallas_curriculares', $malla->id, null, [
            'origen' => 'excel', 'cursos' => $totalCursos,
            'anio' => $malla->anio, 'version' => $malla->version,
        ]);

        return redirect()->route('mallas.index')
            ->with('status', "Malla importada correctamente ({$totalCursos} cursos).");
    }

    /** Crea un curso de la malla. prerequisito_texto conserva el texto original del archivo. */
    private function crearCurso(Ciclo $ciclo, array $cu, ?string $mencion): void
    {
        $pre = trim((string) ($cu['prerequisito'] ?? ''));

        $ciclo->cursos()->create([
            'codigo' => $cu['codigo'],
            'nombre' => $cu['nombre'],
            'creditos' => $cu['creditos'],
            // El archivo trae TH (horas totales); se guarda en horas_teoria, igual que la ficha existente.
            'horas_teoria' => $cu['horas'] ?? null,
            'es_electivo' => (bool) ($cu['es_electivo'] ?? false),
            'convalidable' => (bool) ($cu['convalidable'] ?? true),
            'mencion' => $mencion,
            'prerequisito_texto' => ($pre === '' || $pre === '—') ? null : $pre,
        ]);
    }

    public function store(ImportarMallaRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_id']);

        // Crea la malla cabecera (RN-01/03 aplican vía índice único).
        $carga = DB::transaction(function () use ($datos, $request) {
            $malla = MallaCurricular::create([
                'carrera_id' => $datos['carrera_id'],
                'anio' => $datos['anio'],
                'version' => $datos['version'],
                'activa' => false,
                'origen_carga' => 'excel',
                'usuario_id' => $request->user()->id,
            ]);

            $ruta = $request->file('archivo')->store('cargas');

            $carga = CargaMasiva::create([
                'usuario_id' => $request->user()->id,
                'malla_id' => $malla->id,
                'archivo' => $ruta,
                'estado' => 'pendiente',
            ]);

            // RF-11: procesamiento en segundo plano. `afterCommit` porque el
            // worker vive fuera de esta transacción: sin esto podía tomar el
            // trabajo antes del COMMIT y fallar al buscar la carga que aún no
            // existe para él.
            ImportarMallaExcel::dispatch($carga->id, $malla->id)->afterCommit();

            return $carga;
        });

        return redirect()->route('mallas.importar.estado', $carga->id)
            ->with('status', 'Carga iniciada. El procesamiento corre en segundo plano.');
    }

    public function estado(Request $request, CargaMasiva $carga)
    {
        $this->autorizarCarga($request, $carga);

        return inertia('Mallas/CargaEstado', ['cargaId' => $carga->id]);
    }

    /** Endpoint de progreso para el sondeo del frontend (RF-11). */
    public function progreso(Request $request, CargaMasiva $carga): JsonResponse
    {
        $this->autorizarCarga($request, $carga);

        return response()->json([
            'estado' => $carga->estado,
            'total' => $carga->total,
            'procesados' => $carga->procesados,
            'errores' => $carga->errores,
            'porcentaje' => $carga->porcentaje(),
            'detalle' => $carga->detalle_errores ?? [],
        ]);
    }
}
