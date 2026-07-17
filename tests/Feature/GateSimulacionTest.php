<?php

namespace Tests\Feature;

use App\Models\Postulante;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GateSimulacionTest extends TestCase
{
    use RefreshDatabase;

    private function coordinador(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $rol = Role::where('nombre', Role::COORDINADOR)->firstOrFail();
        $u = User::create([
            'nombre' => 'Coord', 'email' => 'coord@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
        $u->carrerasPermitidas()->sync(\App\Models\Carrera::pluck('id'));

        return $u;
    }

    private function postulante(string $estado): Postulante
    {
        return Postulante::create([
            'codigo' => 'POST-2026-' . random_int(10000, 99999),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(10000000, 99999999),
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => uniqid() . '@ex.com',
            'revision_estado' => $estado,
        ]);
    }

    public function test_crear_simulacion_bloqueada_sin_aprobacion(): void
    {
        $coord = $this->coordinador();
        $p = $this->postulante('pendiente');

        $this->actingAs($coord)->get("/simulaciones/simular/{$p->id}")->assertForbidden();
    }

    public function test_gate_se_levanta_al_aprobar(): void
    {
        $coord = $this->coordinador();
        $p = $this->postulante('aprobada');

        // Robusto ante datos faltantes: basta comprobar que el gate ya no bloquea (no es 403).
        $status = $this->actingAs($coord)->get("/simulaciones/simular/{$p->id}")->getStatusCode();
        $this->assertNotSame(403, $status, 'Un expediente aprobado no debe ser bloqueado por el gate.');
    }
}
