<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Carga de la malla de una institución externa por plantilla Excel, sin IA.
 *
 * Sustituye UN paso del flujo existente: la lista de cursos deja de salir de la
 * extracción con IA y sale de un archivo que llena una persona. La revisión en
 * pantalla y el guardado no cambian, así que lo que se prueba aquí es que la
 * lista tenga la misma forma y que los errores del archivo se puedan corregir.
 */
class MallaExternaPlantillaTest extends TestCase
{
    use RefreshDatabase;

    private User $especialista; // tiene mallas_externas.gestionar

    private User $asesor;      // no lo tiene: solo registra postulantes

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->especialista = $this->usuarioCon(Role::ESPECIALISTA);
        $this->asesor = $this->usuarioCon(Role::ASESOR);
    }

    private function usuarioCon(string $rol): User
    {
        return User::create([
            'nombre' => $rol, 'email' => str_replace(' ', '', strtolower($rol)).'@usil.edu.pe',
            'password_hash' => Hash::make('x'),
            'rol_id' => Role::where('nombre', $rol)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    /**
     * CSV en vez de xlsx: la validación lo admite y el importador lo lee igual,
     * y así no entra un binario de fixture al repositorio.
     */
    private function archivo(string $contenido, string $nombre = 'cursos.csv'): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'malla').'.csv';
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombre, 'text/csv', null, true);
    }

    public function test_la_plantilla_se_descarga(): void
    {
        $this->actingAs($this->especialista)->get('/mallas-externas/plantilla')->assertOk();
    }

    public function test_la_plantilla_exige_el_permiso_de_mallas_externas(): void
    {
        $this->actingAs($this->asesor)->get('/mallas-externas/plantilla')->assertForbidden();
    }

    /** La lista debe salir con la misma forma que devuelve la extracción con IA. */
    public function test_un_excel_valido_devuelve_los_cursos(): void
    {
        $csv = "codigo,nombre,creditos\nMAT101,Cálculo I,4\nPRG101,Fundamentos de Programación,3\n";

        $this->actingAs($this->especialista)
            ->post('/mallas-externas/previsualizar', ['archivo' => $this->archivo($csv)])
            ->assertOk()
            ->assertJsonPath('cursos.0.codigo', 'MAT101')
            ->assertJsonPath('cursos.0.nombre', 'Cálculo I')
            ->assertJsonPath('cursos.0.creditos', 4)
            ->assertJsonPath('cursos.1.nombre', 'Fundamentos de Programación')
            ->assertJsonCount(2, 'cursos')
            ->assertJsonCount(0, 'omitidas');
    }

    /** Solo el nombre es obligatorio: lo demás puede venir vacío. */
    public function test_admite_cursos_sin_codigo_ni_creditos(): void
    {
        $csv = "codigo,nombre,creditos\n,Seminario de investigación,\n";

        $this->actingAs($this->especialista)
            ->post('/mallas-externas/previsualizar', ['archivo' => $this->archivo($csv)])
            ->assertOk()
            ->assertJsonPath('cursos.0.nombre', 'Seminario de investigación')
            ->assertJsonPath('cursos.0.codigo', null)
            ->assertJsonPath('cursos.0.creditos', null);
    }

    /**
     * Con un archivo que llena una persona, decir QUÉ línea se descartó es la
     * diferencia entre corregirlo y volver a intentarlo a ciegas.
     */
    public function test_las_filas_sin_nombre_se_omiten_y_se_reportan_con_su_linea(): void
    {
        // Cabecera = línea 1, así que la fila sin nombre es la línea 3.
        $csv = "codigo,nombre,creditos\nMAT101,Cálculo I,4\nX999,,2\n";

        $this->actingAs($this->especialista)
            ->post('/mallas-externas/previsualizar', ['archivo' => $this->archivo($csv)])
            ->assertOk()
            ->assertJsonCount(1, 'cursos')
            ->assertJsonCount(1, 'omitidas')
            ->assertJsonPath('omitidas.0.linea', 3);
    }

    /** Los límites de columna son los mismos que ya aplica el guardado. */
    public function test_trunca_codigo_y_nombre_a_lo_que_cabe_en_la_columna(): void
    {
        $codigo = str_repeat('C', 40);
        $nombre = str_repeat('N', 250);
        $csv = "codigo,nombre,creditos\n{$codigo},{$nombre},4\n";

        $respuesta = $this->actingAs($this->especialista)
            ->post('/mallas-externas/previsualizar', ['archivo' => $this->archivo($csv)])
            ->assertOk()
            ->json('cursos.0');

        $this->assertSame(30, mb_strlen($respuesta['codigo']));
        $this->assertSame(200, mb_strlen($respuesta['nombre']));
    }

    public function test_rechaza_un_archivo_que_no_es_excel(): void
    {
        $this->actingAs($this->especialista)
            ->postJson('/mallas-externas/previsualizar', ['archivo' => $this->archivo('%PDF-1.4', 'malla.pdf')])
            ->assertStatus(422);
    }

    public function test_previsualizar_exige_el_permiso_de_mallas_externas(): void
    {
        $csv = "codigo,nombre,creditos\nMAT101,Cálculo I,4\n";

        $this->actingAs($this->asesor)
            ->post('/mallas-externas/previsualizar', ['archivo' => $this->archivo($csv)])
            ->assertForbidden();
    }
}
