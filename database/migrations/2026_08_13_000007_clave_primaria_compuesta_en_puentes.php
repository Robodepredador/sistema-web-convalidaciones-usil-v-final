<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las dos tablas de alcance son puentes puros: solo relacionan un usuario con
 * una carrera o una facultad, sin atributos propios. Llevaban un `id`
 * autonumérico y además un UNIQUE sobre el par, o sea dos identificadores para
 * la misma fila y un índice de más en cada escritura.
 *
 * rol_permiso, en este mismo esquema, ya usa clave primaria compuesta sin id
 * sustituto (rol_id, permiso_id). Estas dos eran la desviación, no la norma.
 *
 * El orden importa: MySQL exige que una columna AUTO_INCREMENT sea parte de
 * una clave, así que primero hay que quitarle el AUTO_INCREMENT y solo
 * después soltar la clave primaria y la columna. Hacerlo al revés falla.
 *
 * FK sin índice que la respalde (error 1553, el mismo que resolvió
 * 2026_08_13_000005_unicidad_en_catalogos): aquí no hace falta reponer nada.
 * carrera_id/facultad_id no son la columna líder del UNIQUE viejo, así que
 * desde que la tabla se creó ya tienen su propio índice simple
 * (..._foreign), separado del UNIQUE. Ningún paso de abajo lo toca; sigue
 * respaldando esa FK todo el tiempo. La FK de usuario_id, columna líder,
 * pasa a quedar respaldada por la PK compuesta nueva en cuanto esta existe.
 *
 * MySQL no tiene DDL transaccional: cada ALTER hace commit por su cuenta. Si
 * el proceso se corta a medio bucle (una tabla lista, la otra a medias), un
 * reintento debe poder retomar donde se quedó en vez de romper contra un
 * `id` que ya no existe o una clave que ya está puesta; de ahí que cada paso
 * compruebe su propia precondición antes de correr.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'permisos_carrera' => ['carrera_id', 'permisos_carrera_usuario_id_carrera_id_unique'],
            'permisos_facultad' => ['facultad_id', 'permisos_facultad_usuario_id_facultad_id_unique'],
        ] as $tabla => [$columna, $indiceUnico]) {
            if (Schema::hasColumn($tabla, 'id')) {
                DB::statement("ALTER TABLE `{$tabla}` MODIFY `id` BIGINT UNSIGNED NOT NULL");
                DB::statement("ALTER TABLE `{$tabla}` DROP PRIMARY KEY, DROP COLUMN `id`");
            }

            if (! $this->existeIndice($tabla, 'PRIMARY')) {
                DB::statement("ALTER TABLE `{$tabla}` ADD PRIMARY KEY (`usuario_id`, `{$columna}`)");
            }

            if ($this->existeIndice($tabla, $indiceUnico)) {
                DB::statement("ALTER TABLE `{$tabla}` DROP INDEX `{$indiceUnico}`");
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'permisos_carrera' => ['carrera_id', 'permisos_carrera_usuario_id_carrera_id_unique'],
            'permisos_facultad' => ['facultad_id', 'permisos_facultad_usuario_id_facultad_id_unique'],
        ] as $tabla => [$columna, $indiceUnico]) {
            if (! $this->existeIndice($tabla, $indiceUnico)) {
                DB::statement("ALTER TABLE `{$tabla}` ADD UNIQUE `{$indiceUnico}` (`usuario_id`, `{$columna}`)");
            }

            if ($this->existeIndice($tabla, 'PRIMARY')) {
                DB::statement("ALTER TABLE `{$tabla}` DROP PRIMARY KEY");
            }

            if (! Schema::hasColumn($tabla, 'id')) {
                DB::statement("ALTER TABLE `{$tabla}` ADD `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
            }
        }
    }

    /** information_schema.STATISTICS es donde MySQL expone si un índice existe (PRIMARY incluida). */
    private function existeIndice(string $tabla, string $indice): bool
    {
        return DB::selectOne(
            'SELECT COUNT(*) total FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$tabla, $indice]
        )->total > 0;
    }
};
