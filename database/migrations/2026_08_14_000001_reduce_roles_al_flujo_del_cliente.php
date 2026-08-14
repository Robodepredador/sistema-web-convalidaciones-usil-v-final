<?php

use Database\Seeders\RoleSeeder;
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
 *
 * Mover los roles no basta: la matriz de permisos (Permiso::CATALOGO / POR_ROL)
 * vive en PHP, y en una base ya sembrada nadie vuelve a correr RoleSeeder solo
 * porque esta migración corrió. Sin aplicarla aquí, cualquier instalación
 * existente queda con el Especialista sin ningún permiso y el Administrativo
 * conservando los que esta tarea le retira -sus filas de rol_permiso sobreviven
 * porque renombrar un rol conserva su id-. Por eso up() también borra del
 * catálogo las llaves de la cadena de aprobación y resincroniza rol_permiso
 * para los 5 roles finales, reutilizando RoleSeeder en vez de duplicar la
 * matriz aquí. Ese resincronizado solo corre si `roles` ya tenía datos: una
 * instalación nueva no arrastra nada que corregir, y sembrarla de una vez aquí
 * mezclaría schema con datos de negocio -detalle en el primer paso de up()-.
 */
return new class extends Migration
{
    private const RETIRADOS = ['Director de Carrera', 'Decano', 'Auditor', 'Consulta / Alta Dirección'];

    /** Llaves de la cadena de aprobación: salen del catálogo junto con los roles que las usaban. */
    private const PERMISOS_RETIRADOS = [
        'evaluacion.aprobar', 'evaluacion.observar', 'evaluacion.reasignar',
        'evaluacion.proponer', 'solicitudes.asignar',
    ];

    /** Lo que down() recrea: módulo y descripción tal como estaban en el catálogo antes de up(). */
    private const CATALOGO_PERMISOS_RETIRADOS = [
        'evaluacion.aprobar' => ['Evaluación', 'Aprobar la evaluación'],
        'evaluacion.observar' => ['Evaluación', 'Observar / devolver para corrección'],
        'evaluacion.reasignar' => ['Evaluación', 'Reasignar evaluaciones'],
        'evaluacion.proponer' => ['Evaluación', 'Generar propuesta de preconvalidación'],
        'solicitudes.asignar' => ['Solicitudes', 'Asignar solicitud a un coordinador'],
    ];

    public function up(): void
    {
        // Una instalación NUEVA llega aquí con `roles` recién creada por el
        // schema y todavía vacía -así arranca cualquier base de pruebas antes
        // de sembrar-: no hay una matriz vieja que corregir, y sembrarla aquí
        // sería la migración, no el seeder, inventando datos de negocio. El
        // flujo normal de instalación (DatabaseSeeder) la deja bien desde el
        // primer `db:seed`. El caso que sí importa es la base que YA tenía el
        // esquema de 8 roles con datos reales: solo ahí hace falta aplicar el
        // ajuste, porque nadie vuelve a correr el seeder por su cuenta.
        $habiaEsquemaPrevio = DB::table('roles')->exists();

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

        if ($habiaEsquemaPrevio) {
            // rol_permiso tiene cascadeOnDelete sobre permiso_id: borrar aquí
            // arrastra las asignaciones viejas sin dejar huérfanos. equivalencias.gestionar
            // (llave nueva) y el resto del catálogo los inserta/actualiza el propio seeder.
            DB::table('permisos')->whereIn('clave', self::PERMISOS_RETIRADOS)->delete();

            (new RoleSeeder)->run();
        }
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

        // Mismo límite que con los roles: se recrean las llaves de permiso para
        // no perder el catálogo, pero qué rol tenía cada una ya se perdió al
        // correr up(). La reversión restituye la MATRIZ, no quién tenía qué.
        //
        // A propósito NO se llama a RoleSeeder aquí (a diferencia de up()):
        // Permiso::POR_ROL solo conoce los 5 roles nuevos, así que sincronizar
        // en este punto recrearía exactamente Especialista y Administrativo
        // -los dos roles que este método acaba de retirar dos líneas más
        // arriba-, deshaciendo el propio down(). Las asignaciones vuelven solas
        // la próxima vez que algo corra RoleSeeder (un `migrate` que reaplique
        // esta migración, o un `db:seed` manual).
        foreach (self::CATALOGO_PERMISOS_RETIRADOS as $clave => [$modulo, $descripcion]) {
            DB::table('permisos')->updateOrInsert(
                ['clave' => $clave],
                ['modulo' => $modulo, 'descripcion' => $descripcion, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
};
