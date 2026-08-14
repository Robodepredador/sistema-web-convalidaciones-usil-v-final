<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El sistema tenía ocho roles porque modelaba una cadena de aprobación —el
 * coordinador propone, el director aprueba, el decano visa— que el flujo real
 * del cliente no tiene. Ahí no hay visto bueno posterior: el administrativo
 * atiende la simulación y ahí termina.
 *
 * Director de Carrera, Decano, Auditor y Consulta salen. Coordinador de Carrera
 * se renombra a Administrativo de Facultad, que es lo que de verdad hace: el
 * cliente aclaró que puede ser cualquier administrativo, coordinador o profesor
 * designado. Y entra el Especialista en Convalidaciones, que no existía.
 *
 * Los usuarios de los roles retirados se mueven a Administrativo de Facultad,
 * el permiso más cercano a lo que hacían: no se borra a nadie ni se le deja
 * apuntando a un rol inexistente.
 */
return new class extends Migration
{
    private const RETIRADOS = ['Director de Carrera', 'Decano', 'Auditor', 'Consulta / Alta Dirección'];

    public function up(): void
    {
        DB::table('roles')->where('nombre', 'Coordinador de Carrera')
            ->update(['nombre' => 'Administrativo de Facultad']);

        $destino = DB::table('roles')->where('nombre', 'Administrativo de Facultad')->value('id');

        if ($destino !== null) {
            DB::table('usuarios')
                ->whereIn('rol_id', DB::table('roles')->whereIn('nombre', self::RETIRADOS)->pluck('id'))
                ->update(['rol_id' => $destino]);
        }

        DB::table('roles')->whereIn('nombre', self::RETIRADOS)->delete();

        DB::table('roles')->insertOrIgnore([
            'nombre' => 'Especialista en Convalidaciones',
            'descripcion' => 'Registra mallas USIL y equivalencias de sus carreras asignadas',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('nombre', 'Especialista en Convalidaciones')->delete();

        DB::table('roles')->where('nombre', 'Administrativo de Facultad')
            ->update(['nombre' => 'Coordinador de Carrera']);

        // Los roles retirados se recrean vacíos: a qué usuario pertenecía cada
        // uno no se puede saber después de haberlos movido.
        foreach (self::RETIRADOS as $nombre) {
            DB::table('roles')->insertOrIgnore([
                'nombre' => $nombre, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
};
