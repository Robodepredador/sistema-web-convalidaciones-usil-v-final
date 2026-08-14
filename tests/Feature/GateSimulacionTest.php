<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Postulante;
use App\Models\Role;
use App\Models\UnidadNegocio;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GateSimulacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Carrera real con su facultad. Es necesaria para que el alcance por rol
     * (RF-40) no sea lo que bloquee: esta prueba mide el gate de revisión, así
     * que el coordinador debe tener la carrera destino dentro de su alcance.
     */
    private function carrera(): Carrera
    {
        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);

        return Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'SW', 'codigo' => 'SW']);
    }

    private function coordinador(): User
    {
        $this->seed(RoleSeeder::class);
        $rol = Role::where('nombre', Role::ADMINISTRATIVO)->firstOrFail();
        $u = User::create([
            'nombre' => 'Coord', 'email' => 'coord@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
        $u->carrerasPermitidas()->sync(Carrera::pluck('id'));

        return $u;
    }

    private function postulante(string $estado, Carrera $carrera): Postulante
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-'.random_int(10000, 99999),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(10000000, 99999999),
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => uniqid().'@ex.com',
            'revision_estado' => $estado, 'carrera_destino_id' => $carrera->id,
        ]);
        $p->destinos()->create(['carrera_id' => $carrera->id]);

        return $p;
    }

    public function test_crear_simulacion_bloqueada_sin_aprobacion(): void
    {
        $carrera = $this->carrera();
        $coord = $this->coordinador();
        $p = $this->postulante('pendiente', $carrera);

        $this->actingAs($coord)->get("/simulaciones/simular/{$p->id}")->assertForbidden();
    }

    public function test_gate_se_levanta_al_aprobar(): void
    {
        $carrera = $this->carrera();
        $coord = $this->coordinador();
        $p = $this->postulante('aprobada', $carrera);

        // Robusto ante datos faltantes: basta comprobar que el gate ya no bloquea (no es 403).
        $status = $this->actingAs($coord)->get("/simulaciones/simular/{$p->id}")->getStatusCode();
        $this->assertNotSame(403, $status, 'Un expediente aprobado no debe ser bloqueado por el gate.');
    }
}
