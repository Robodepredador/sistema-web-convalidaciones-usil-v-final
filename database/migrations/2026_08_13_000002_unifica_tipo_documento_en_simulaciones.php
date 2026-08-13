<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mismo dominio, dos ENUM distintos: postulantes admite DNI, CE, PASAPORTE, PTP
 * y TEMP; simulaciones solo los tres primeros. Un postulante con carné de
 * extranjería temporal generaba una simulación cuyo tipo de documento no podía
 * representarse, y el valor terminaba escrito como 'DNI'.
 *
 * El documento que firma la universidad decía entonces que la persona tiene DNI
 * cuando no lo tiene. Se amplía el ENUM y se reparan las filas ya falsificadas
 * copiando el valor verdadero desde el postulante.
 *
 * (La Fase 2 elimina esta columna: el tipo de documento es del postulante y no
 * debe duplicarse aquí. Mientras exista, que al menos no mienta.)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE simulaciones
            MODIFY tipo_documento ENUM('DNI','CE','PASAPORTE','PTP','TEMP') NOT NULL");

        DB::statement('UPDATE simulaciones s
            INNER JOIN postulantes p ON p.id = s.postulante_id
            SET s.tipo_documento = p.tipo_documento
            WHERE s.postulante_id IS NOT NULL
              AND s.tipo_documento <> p.tipo_documento');
    }

    public function down(): void
    {
        // Las filas con PTP/TEMP no caben en el ENUM viejo; se llevan a DNI, que es
        // exactamente el estado defectuoso del que veníamos.
        DB::statement("UPDATE simulaciones SET tipo_documento = 'DNI'
            WHERE tipo_documento IN ('PTP','TEMP')");

        DB::statement("ALTER TABLE simulaciones
            MODIFY tipo_documento ENUM('DNI','CE','PASAPORTE') NOT NULL");
    }
};
