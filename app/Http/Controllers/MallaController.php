<?php

namespace App\Http\Controllers;

use App\Exports\MallaCursosExport;
use App\Exports\MallaPlantillaExport;
use App\Http\Requests\StoreMallaRequest;
use App\Http\Requests\UpdateMallaRequest;
use App\Jobs\ImportarMallaExcel;
use App\Models\CargaMasiva;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\CursoUsil;
use App\Models\Facultad;
use App\Models\MallaCurricular;
use App\Models\UnidadNegocio;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * CU-01: Gestionar Mallas Curriculares (alta manual).
 * RF-01..05, RF-07; reglas RN-01, RN-02, RN-03.
 */
class MallaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // RF-40: el coordinador solo ve sus carreras asignadas (alcance base).
        $scopeIds = $user->esAdministrador()
            ? null
            : $user->carrerasPermitidas()->pluck('carreras.id');

        $base = MallaCurricular::query()
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('carrera_id', $scopeIds));

        // RF-04: filtros por unidad de negocio, facultad, carrera y año.
        $mallas = (clone $base)
            ->with('carrera.facultad.unidadNegocio')
            ->when($request->unidad_negocio_id, fn ($q, $v) => $q->whereHas('carrera.facultad', fn ($s) => $s->where('unidad_negocio_id', $v)))
            ->when($request->facultad_id, fn ($q, $v) => $q->whereHas('carrera', fn ($s) => $s->where('facultad_id', $v)))
            ->when($request->carrera_id, fn ($q, $v) => $q->where('carrera_id', $v))
            ->when($request->anio, fn ($q, $v) => $q->where('anio', $v))
            ->orderByDesc('anio')->orderBy('version')
            ->paginate(10)->withQueryString()
            ->through(fn (MallaCurricular $m) => [
                'id' => $m->id,
                'unidad' => $m->carrera->facultad->unidadNegocio->nombre ?? '—',
                'facultad' => $m->carrera->facultad->nombre ?? '—',
                'carrera' => $m->carrera->nombre,
                'anio' => $m->anio,
                'version' => $m->version,
                'modalidad' => $m->modalidad,
                'periodo' => $m->periodo,
                'activa' => $m->activa,
                'origen' => $m->origen_carga,
            ]);

        [$unidades, $facultades, $carreras] = $this->opcionesFiltro($user, $scopeIds);

        return inertia('Mallas/Index', [
            'mallas' => $mallas,
            'mallasActivas' => (clone $base)->where('activa', true)->count(),
            'unidades' => $unidades,
            'facultades' => $facultades,
            'carreras' => $carreras,
            'filtros' => $request->only(['unidad_negocio_id', 'facultad_id', 'carrera_id', 'anio']),
        ]);
    }

    /**
     * Opciones para los filtros en cascada, respetando el alcance del usuario.
     *
     * @return array{0:Collection,1:Collection,2:Collection}
     */
    private function opcionesFiltro($user, $scopeIds): array
    {
        if ($user->esAdministrador()) {
            $carreras = Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'facultad_id']);
            $facultades = Facultad::orderBy('nombre')->get(['id', 'nombre', 'unidad_negocio_id']);
            $unidades = UnidadNegocio::orderBy('nombre')->get(['id', 'nombre']);

            return [$unidades, $facultades, $carreras];
        }

        $carreras = Carrera::whereIn('id', $scopeIds)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'facultad_id']);
        $facultades = Facultad::whereIn('id', $carreras->pluck('facultad_id')->unique())->orderBy('nombre')->get(['id', 'nombre', 'unidad_negocio_id']);
        $unidades = UnidadNegocio::whereIn('id', $facultades->pluck('unidad_negocio_id')->unique())->orderBy('nombre')->get(['id', 'nombre']);

        return [$unidades, $facultades, $carreras];
    }

    public function create(Request $request)
    {
        return inertia('Mallas/Form', [
            'carreras' => $this->carrerasDelUsuario($request),
        ]);
    }

    public function store(StoreMallaRequest $request): RedirectResponse
    {
        $this->autorizarCarrera($request, (int) $request->carrera_id);

        $datos = $request->validated();

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
                'origen_carga' => 'manual',
                'usuario_id' => $request->user()->id,
            ]);

            foreach ($datos['ciclos'] as $cicloData) {
                $ciclo = $malla->ciclos()->create(['numero' => $cicloData['numero']]);

                foreach ($cicloData['cursos'] ?? [] as $curso) {
                    $ciclo->cursos()->create([
                        'codigo' => $curso['codigo'],
                        'nombre' => $curso['nombre'],
                        'creditos' => $curso['creditos'],
                    ]);
                }
            }

            return $malla;
        });

        AuditoriaService::registrar('crear', 'mallas_curriculares', $malla->id, null, [
            'carrera_id' => $malla->carrera_id, 'anio' => $malla->anio, 'version' => $malla->version,
        ]);

        return redirect()->route('mallas.index')->with('status', 'Malla registrada correctamente.');
    }

    public function edit(Request $request, MallaCurricular $malla)
    {
        $this->autorizarCarrera($request, $malla->carrera_id);

        $malla->load('carrera');

        return inertia('Mallas/Editar', [
            'malla' => [
                'id' => $malla->id,
                'carrera' => $malla->carrera->nombre,
                'anio' => $malla->anio,
                'version' => $malla->version,
                'modalidad' => $malla->modalidad,
                'periodo' => $malla->periodo,
                'activa' => $malla->activa,
            ],
        ]);
    }

    public function update(UpdateMallaRequest $request, MallaCurricular $malla): RedirectResponse
    {
        $this->autorizarCarrera($request, $malla->carrera_id);

        $datos = $request->validated();
        $antes = $malla->only(['anio', 'version', 'modalidad', 'periodo', 'activa']);

        DB::transaction(function () use ($datos, $malla) {
            // RN-02: si se marca activa, desactivar las demás de la carrera.
            if (! empty($datos['activa'])) {
                MallaCurricular::where('carrera_id', $malla->carrera_id)
                    ->whereKeyNot($malla->id)
                    ->update(['activa' => false]);
            }

            $malla->update([
                'anio' => $datos['anio'],
                'version' => $datos['version'],
                'modalidad' => $datos['modalidad'],
                'periodo' => $datos['periodo'] ?? null,
                'activa' => $datos['activa'] ?? false,
            ]);
        });

        AuditoriaService::registrar('editar', 'mallas_curriculares', $malla->id, $antes, $malla->only([
            'anio', 'version', 'modalidad', 'periodo', 'activa',
        ]));

        return redirect()->route('mallas.index')->with('status', 'Malla actualizada correctamente.');
    }

    /**
     * Elimina la malla (borrado lógico, RF-05: se conserva el histórico de
     * convalidaciones que ya la referencian). Los ciclos y cursos quedan
     * huérfanos pero intactos en la base de datos, no se borran en cascada.
     */
    public function destroy(Request $request, MallaCurricular $malla): RedirectResponse
    {
        $this->autorizarCarrera($request, $malla->carrera_id);

        $resumen = ['carrera_id' => $malla->carrera_id, 'anio' => $malla->anio, 'version' => $malla->version];
        $malla->delete();

        AuditoriaService::registrar('eliminar', 'mallas_curriculares', $malla->id, null, $resumen);

        return redirect()->route('mallas.index')->with('status', 'Malla eliminada.');
    }

    // ===================== Mantenimiento del currículo (CU-01 / RF-05..07) =====================

    /** Vista de mantenimiento de la malla: ciclos, cursos y resumen. */
    public function show(Request $request, MallaCurricular $malla)
    {
        $this->autorizarCarrera($request, $malla->carrera_id);

        $malla->load([
            'carrera.facultad',
            'ciclos' => fn ($q) => $q->orderBy('numero'),
            'ciclos.cursos' => fn ($q) => $q->orderBy('codigo'),
            'ciclos.cursos.prerequisito',
            'ciclos.cursos.detallesSimulacion.simulacion',
        ]);

        $cursos = $malla->ciclos->flatMap->cursos;

        return inertia('Mallas/Show', [
            'malla' => [
                'id' => $malla->id,
                'carrera' => $malla->carrera->nombre,
                'facultad' => $malla->carrera->facultad->nombre ?? null,
                'anio' => $malla->anio,
                'version' => $malla->version,
                'periodo' => $malla->periodo,
                'modalidad' => $malla->modalidad,
                'activa' => $malla->activa,
                'max_ciclos' => $malla->carrera->max_ciclos,
            ],
            'ciclos' => $malla->ciclos->map(fn (Ciclo $c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'nombre' => $c->nombre,
                'cursos' => $c->cursos->map(fn (CursoUsil $cu) => [
                    'id' => $cu->id,
                    'codigo' => $cu->codigo,
                    'nombre' => $cu->nombre,
                    'creditos' => (float) $cu->creditos,
                    'horas_teoria' => $cu->horas_teoria,
                    'horas_practica' => $cu->horas_practica,
                    'es_electivo' => $cu->es_electivo,
                    'convalidable' => $cu->convalidable,
                    'mencion' => $cu->mencion,
                    'prerequisito_id' => $cu->prerequisito_id,
                    'prerequisito' => $cu->prerequisito?->nombre ?? $cu->prerequisito_texto,
                    'silabo_texto' => $cu->silabo_texto,
                    'tipo_curso' => $cu->tipo_curso,
                    'area' => $cu->area,
                    'competencias' => $cu->competencias ?? [],
                    'resultados_aprendizaje' => $cu->resultados_aprendizaje,
                    'creado' => $cu->created_at?->format('Y-m-d H:i'),
                    'actualizado' => $cu->updated_at?->format('Y-m-d H:i'),
                    'convalidaciones' => $cu->detallesSimulacion->map(fn ($d) => [
                        'estudiante' => trim(($d->simulacion?->nombres ?? '').' '.($d->simulacion?->apellidos ?? '')),
                        'estado' => $d->simulacion?->estado,
                        'creditos' => (float) $d->creditos_reconocidos,
                        'excluido' => $d->excluido,
                    ])->values(),
                ]),
            ]),
            'resumen' => [
                'cursos' => $cursos->count(),
                'creditos' => (float) $cursos->sum('creditos'),
                'ciclos' => $malla->ciclos->count(),
                'obligatorios' => $cursos->where('es_electivo', false)->count(),
                'electivos' => $cursos->where('es_electivo', true)->count(),
                'menciones' => $cursos->whereNotNull('mencion')->pluck('mencion')->unique()->count(),
            ],
            'cursosMalla' => $cursos->map(fn ($cu) => ['id' => $cu->id, 'nombre' => $cu->codigo.' — '.$cu->nombre])->values(),
        ]);
    }

    public function agregarCiclo(Request $request, MallaCurricular $malla): RedirectResponse
    {
        $this->autorizarCarrera($request, $malla->carrera_id);

        $datos = $request->validate([
            'numero' => ['required', 'integer', 'min:1', 'max:'.$malla->carrera->max_ciclos,
                Rule::unique('ciclos', 'numero')->where('malla_id', $malla->id)],
            'nombre' => ['nullable', 'string', 'max:50'],
        ], [
            'numero.max' => "El ciclo excede el máximo de la carrera ({$malla->carrera->max_ciclos}).",
            'numero.unique' => 'Ese número de ciclo ya existe en la malla.',
        ]);

        $malla->ciclos()->create($datos);
        AuditoriaService::registrar('editar', 'mallas_curriculares', $malla->id, null, ['ciclo_agregado' => $datos['numero']]);

        return back()->with('status', "Ciclo {$datos['numero']} agregado.");
    }

    public function eliminarCiclo(Request $request, MallaCurricular $malla, Ciclo $ciclo): RedirectResponse
    {
        $this->autorizarCarrera($request, $malla->carrera_id);
        abort_unless($ciclo->malla_id === $malla->id, 404);
        abort_if($ciclo->cursos()->exists(), 422, 'No se puede eliminar un ciclo con cursos. Elimine primero los cursos.');

        $numero = $ciclo->numero;
        $ciclo->delete();
        AuditoriaService::registrar('eliminar', 'mallas_curriculares', $malla->id, null, ['ciclo_eliminado' => $numero]);

        return back()->with('status', "Ciclo {$numero} eliminado.");
    }

    public function agregarCurso(Request $request, MallaCurricular $malla, Ciclo $ciclo): RedirectResponse
    {
        $this->autorizarCarrera($request, $malla->carrera_id);
        abort_unless($ciclo->malla_id === $malla->id, 404);

        $datos = $this->validarCurso($request, $malla);
        $ciclo->cursos()->create($datos);
        AuditoriaService::registrar('crear', 'mallas_curriculares', $malla->id, null, ['curso' => $datos['codigo']]);

        return back()->with('status', "Curso {$datos['codigo']} agregado.");
    }

    public function actualizarCurso(Request $request, MallaCurricular $malla, CursoUsil $curso): RedirectResponse
    {
        $this->autorizarCarrera($request, $malla->carrera_id);
        abort_unless($curso->ciclo->malla_id === $malla->id, 404);

        $curso->update($this->validarCurso($request, $malla, $curso->id));
        AuditoriaService::registrar('editar', 'mallas_curriculares', $malla->id, null, ['curso' => $curso->codigo]);

        return back()->with('status', "Curso {$curso->codigo} actualizado.");
    }

    public function eliminarCurso(Request $request, MallaCurricular $malla, CursoUsil $curso): RedirectResponse
    {
        $this->autorizarCarrera($request, $malla->carrera_id);
        abort_unless($curso->ciclo->malla_id === $malla->id, 404);

        // RF-05: borrado lógico para no romper convalidaciones históricas.
        $codigo = $curso->codigo;
        $curso->delete();
        AuditoriaService::registrar('eliminar', 'mallas_curriculares', $malla->id, null, ['curso' => $codigo]);

        return back()->with('status', "Curso {$codigo} eliminado.");
    }

    /** Plantilla de importación de cursos (Excel con cabeceras y ejemplos). */
    public function plantilla()
    {
        return Excel::download(new MallaPlantillaExport, 'plantilla_cursos_malla.xlsx');
    }

    /** RF-37: exportar los cursos de la malla a Excel. */
    public function exportarCursos(Request $request, MallaCurricular $malla)
    {
        $this->autorizarCarrera($request, $malla->carrera_id);

        $nombre = 'malla_'.str_replace(' ', '_', $malla->version).'_'.$malla->anio.'.xlsx';

        return Excel::download(new MallaCursosExport($malla), $nombre);
    }

    /** RF-08..12: importar cursos a la malla existente desde Excel. */
    public function importarCursos(Request $request, MallaCurricular $malla): RedirectResponse
    {
        $this->autorizarCarrera($request, $malla->carrera_id);

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $ruta = $request->file('archivo')->store('cargas');

        $carga = CargaMasiva::create([
            'usuario_id' => $request->user()->id,
            'malla_id' => $malla->id,
            'archivo' => $ruta,
            'estado' => 'pendiente',
        ]);

        // Con QUEUE_CONNECTION=sync corre de inmediato; con redis, en segundo plano (RF-11).
        ImportarMallaExcel::dispatch($carga->id, $malla->id);

        return back()->with('status', 'Cursos importados desde el archivo.');
    }

    /** Validación de un curso (RF-06): código, créditos, horas, carácter, prerrequisito. */
    private function validarCurso(Request $request, MallaCurricular $malla, ?int $cursoId = null): array
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:30'],
            'nombre' => ['required', 'string', 'max:200'],
            'creditos' => ['required', 'numeric', 'min:0.5', 'max:30'],
            'horas_teoria' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'horas_practica' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'es_electivo' => ['boolean'],
            'convalidable' => ['boolean'],
            'mencion' => ['nullable', 'string', 'max:150'],
            'tipo_curso' => ['nullable', 'in:teorico,practico,teorico_practico'],
            'area' => ['nullable', 'string', 'max:100'],
            'competencias' => ['nullable', 'string', 'max:500'],
            'resultados_aprendizaje' => ['nullable', 'string'],
            // El prerrequisito debe ser un curso de la misma malla (distinto del actual).
            'prerequisito_id' => ['nullable', $cursoId ? Rule::notIn([$cursoId]) : 'nullable',
                Rule::exists('cursos_usil', 'id')->where(fn ($q) => $q->whereIn('ciclo_id', $malla->ciclos()->pluck('id')))],
            'silabo_texto' => ['nullable', 'string'],
        ]);

        // Competencias: de texto separado por comas a arreglo.
        $datos['competencias'] = ! empty($datos['competencias'])
            ? array_values(array_filter(array_map('trim', explode(',', $datos['competencias']))))
            : null;

        return $datos;
    }

    private function carrerasDelUsuario(Request $request)
    {
        $user = $request->user();

        return $user->esAdministrador()
            ? Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'max_ciclos'])
            : $user->carrerasPermitidas()->where('activo', true)->orderBy('nombre')->get(['carreras.id', 'nombre', 'max_ciclos']);
    }

    private function autorizarCarrera(Request $request, int $carreraId): void
    {
        $user = $request->user();

        if ($user->esAdministrador()) {
            return;
        }

        // RF-40: el coordinador solo puede registrar en sus carreras.
        abort_unless(
            $user->carrerasPermitidas()->whereKey($carreraId)->exists(),
            403,
            'No tiene permiso sobre la carrera seleccionada.'
        );
    }
}
