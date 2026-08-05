<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un solo número de memorándum: el guardado pasa a ser el impreso.
 *
 * Hasta ahora la BD guardaba «MEMO-2026-00012» (id de SIMULACIÓN) y el PDF
 * imprimía «0007 - 2026-1 / CPEL-USIL» (id de CONVALIDACIÓN). El buscador
 * consulta la columna, así que el número que el postulante trae en el papel no
 * se encontraba en el sistema. Se reencauzan las filas ya emitidas al formato
 * impreso, que es el que circula fuera.
 */
return new class extends Migration
{
    public function up(): void
    {
        $unidad = DB::table('configuraciones')->where('clave', 'memo_unidad')->value('valor') ?: 'CPEL-USIL';

        foreach ($this->emitidas() as $fila) {
            DB::table('convalidaciones')->where('id', $fila->id)->update([
                'memorandum_numero' => str_pad((string) $fila->id, 4, '0', STR_PAD_LEFT)
                    .' - '.($fila->ciclo_postulacion ?: '—').' / '.$unidad,
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->emitidas() as $fila) {
            DB::table('convalidaciones')->where('id', $fila->id)->update([
                'memorandum_numero' => 'MEMO-'.substr((string) $fila->created_at, 0, 4)
                    .'-'.str_pad((string) $fila->simulacion_id, 5, '0', STR_PAD_LEFT),
            ]);
        }
    }

    /** Convalidaciones con el periodo de su simulación (el número lo incluye). */
    private function emitidas()
    {
        return DB::table('convalidaciones as c')
            ->leftJoin('simulaciones as s', 's.id', '=', 'c.simulacion_id')
            ->select('c.id', 'c.simulacion_id', 'c.created_at', 's.ciclo_postulacion')
            ->get();
    }
};
