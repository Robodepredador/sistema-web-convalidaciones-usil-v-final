<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Models\Postulante;
use App\Models\Role;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alta de postulantes por HTTP: identificadores y adjuntos.
 *
 * Cubre dos defectos que solo aparecen en el camino real (el formulario), no
 * creando modelos a mano: el código se calculaba con `max(id) + 1` antes del
 * INSERT, y cada adjunto creaba una fila nueva en vez de sustituir la anterior.
 */
class RegistroPostulanteTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);
        $carrera = Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'SW', 'codigo' => 'SW']);
        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carExt = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);

        $asesor = User::create([
            'nombre' => 'Asesor', 'email' => 'asesor@usil.edu.pe', 'password_hash' => Hash::make('x'),
            'rol_id' => Role::where('nombre', Role::ASESOR)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);

        $this->ctx = compact('carrera', 'inst', 'carExt', 'asesor');
    }

    /** @param array<string, mixed> $extra */
    private function formulario(string $doc, array $extra = []): array
    {
        return array_merge([
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
            'email' => $doc.'@ext.com',
            'tipo_documento' => 'DNI', 'numero_documento' => $doc,
            'carrera_destino_ids' => [$this->ctx['carrera']->id],
            'ciclo_postulacion' => '2026-1',
            'institucion_origen_id' => $this->ctx['inst']->id,
            'carrera_externa_id' => $this->ctx['carExt']->id,
            'consentimiento_datos' => true,
        ], $extra);
    }

    /** El código sale del id que asigna la base, no de un max(id) leído antes. */
    public function test_el_codigo_se_deriva_del_id_asignado(): void
    {
        $this->actingAs($this->ctx['asesor'])
            ->post('/postulantes', $this->formulario('10000001'))
            ->assertRedirect('/postulantes');

        $p = Postulante::where('numero_documento', '10000001')->firstOrFail();

        $this->assertSame('POST-'.now()->year.'-'.str_pad((string) $p->id, 5, '0', STR_PAD_LEFT), $p->codigo);
        $this->assertStringNotContainsString('TMP-', $p->codigo, 'Quedó el marcador temporal en el código.');
    }

    /** Cada alta obtiene un código propio; ninguno queda con el marcador. */
    public function test_altas_consecutivas_no_repiten_codigo(): void
    {
        foreach (['10000002', '10000003', '10000004'] as $doc) {
            $this->actingAs($this->ctx['asesor'])->post('/postulantes', $this->formulario($doc));
        }

        $codigos = Postulante::pluck('codigo');

        $this->assertCount(3, $codigos);
        $this->assertCount(3, $codigos->unique(), 'Dos postulantes comparten código.');
        $this->assertTrue($codigos->every(fn ($c) => str_starts_with($c, 'POST-')));
    }

    /** Sin documento de identidad, el identificador temporal también sale del id. */
    public function test_el_documento_temporal_se_deriva_del_id(): void
    {
        $this->actingAs($this->ctx['asesor'])
            ->post('/postulantes', $this->formulario('', [
                'sin_documento' => true,
                'email' => 'sindoc@ext.com',
                'numero_documento' => null,
            ]))
            ->assertRedirect('/postulantes');

        $p = Postulante::where('email', 'sindoc@ext.com')->firstOrFail();
        $sufijo = str_pad((string) $p->id, 5, '0', STR_PAD_LEFT);

        $this->assertSame('TEMP', $p->tipo_documento);
        $this->assertSame('TMP-'.now()->year.'-'.$sufijo, $p->numero_documento);
        $this->assertSame('POST-'.now()->year.'-'.$sufijo, $p->codigo);
    }

    /**
     * Volver a subir un documento lo SUSTITUYE. Es lo normal cuando Admisión
     * observa el expediente y el postulante corrige: antes cada corrección
     * añadía una fila y dejaba el archivo anterior huérfano, y el portal —que
     * cuenta documentos— acababa diciendo que estaba todo entregado.
     */
    public function test_resubir_un_documento_lo_reemplaza_en_vez_de_duplicarlo(): void
    {
        Storage::fake();

        $this->actingAs($this->ctx['asesor'])->post('/postulantes', $this->formulario('10000005', [
            'dni' => UploadedFile::fake()->create('dni.pdf', 20, 'application/pdf'),
        ]));

        $p = Postulante::where('numero_documento', '10000005')->firstOrFail();
        $rutaVieja = $p->documentos()->where('tipo', 'dni')->firstOrFail()->ruta;
        Storage::assertExists($rutaVieja);

        // El asesor corrige el DNI y lo vuelve a subir.
        $this->actingAs($this->ctx['asesor'])->put("/postulantes/{$p->id}", $this->formulario('10000005', [
            'dni' => UploadedFile::fake()->create('dni-corregido.pdf', 25, 'application/pdf'),
        ]));

        $documentos = $p->fresh()->documentos()->where('tipo', 'dni')->get();

        $this->assertCount(1, $documentos, 'Resubir duplicó la fila del documento.');
        $this->assertSame('dni-corregido.pdf', $documentos->first()->nombre_original);
        $this->assertNotSame($rutaVieja, $documentos->first()->ruta);
        Storage::assertMissing($rutaVieja);
        Storage::assertExists($documentos->first()->ruta);
    }

    /** El contador del expediente cuenta TIPOS, no versiones. */
    public function test_el_portal_cuenta_tipos_de_documento_y_no_versiones(): void
    {
        Storage::fake();

        $this->actingAs($this->ctx['asesor'])->post('/postulantes', $this->formulario('10000006', [
            'dni' => UploadedFile::fake()->create('dni.pdf', 20, 'application/pdf'),
        ]));

        $p = Postulante::where('numero_documento', '10000006')->firstOrFail();

        // Dos correcciones más del mismo tipo.
        foreach (['v2.pdf', 'v3.pdf'] as $nombre) {
            $this->actingAs($this->ctx['asesor'])->put("/postulantes/{$p->id}", $this->formulario('10000006', [
                'dni' => UploadedFile::fake()->create($nombre, 20, 'application/pdf'),
            ]));
        }

        $p->forceFill([
            'password_hash' => Hash::make('Portal#2026'),
            'acceso_habilitado' => true, 'debe_cambiar_password' => false,
        ])->save();

        $this->actingAs($p->fresh(), 'postulante')->get('/portal/')
            ->assertInertia(fn ($page) => $page
                ->where('timeline.1.detalle', '1 de 2 documentos entregados'));
    }

    /** Subir documento directamente mediante el endpoint de reemplazo rápido sustituye el archivo. */
    public function test_subir_documento_directo_reemplaza_y_elimina_archivo_anterior(): void
    {
        Storage::fake();

        $this->actingAs($this->ctx['asesor'])->post('/postulantes', $this->formulario('10000007', [
            'certificado' => UploadedFile::fake()->create('record-inicial.pdf', 50, 'application/pdf'),
        ]));

        $p = Postulante::where('numero_documento', '10000007')->firstOrFail();
        $docInicial = $p->documentos()->where('tipo', 'certificado')->firstOrFail();
        $rutaVieja = $docInicial->ruta;
        Storage::assertExists($rutaVieja);

        // Reemplazo rápido directo mediante endpoint POST /postulantes/{id}/documentos
        $this->actingAs($this->ctx['asesor'])->post("/postulantes/{$p->id}/documentos", [
            'tipo' => 'certificado',
            'archivo' => UploadedFile::fake()->create('record-subsanado-oficial.pdf', 60, 'application/pdf'),
        ])->assertRedirect();

        $docs = $p->fresh()->documentos()->where('tipo', 'certificado')->get();
        $this->assertCount(1, $docs);
        $this->assertSame('record-subsanado-oficial.pdf', $docs->first()->nombre_original);
        $this->assertNotSame($rutaVieja, $docs->first()->ruta);
        Storage::assertMissing($rutaVieja);
        Storage::assertExists($docs->first()->ruta);
    }
}
