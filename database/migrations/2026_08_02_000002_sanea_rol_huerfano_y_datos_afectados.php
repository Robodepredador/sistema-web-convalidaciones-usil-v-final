<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Informe de Auditoría Técnica (2026-08-02): saneamiento de datos.
 *
 *  BD-05  Rol huérfano 'Servicios Académicos' (dividido en Asesor + Ejecutivo).
 *  BD-02  Convalidaciones confirmadas sobre simulaciones eliminadas.
 *  BD-03  Convalidaciones confirmadas sin ningún curso convalidado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->saneaRolHuerfano();
        $this->anulaConvalidacionesInvalidas();
        $this->neutralizaCuentasDemoEnProduccion();
    }

    /**
     * Cierra el hueco en instalaciones que ya corrieron `db:seed --force` antes
     * de la guarda del seeder. No se borran —`auditoria_log.usuario_id` las
     * referencia y la traza debe conservarse— sino que se desactivan y se les
     * invalida la contraseña pública.
     */
    private function neutralizaCuentasDemoEnProduccion(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $demo = DB::table('usuarios')->where('email', 'like', '%.demo@usil.edu.pe')->pluck('email', 'id');
        if ($demo->isEmpty()) {
            return;
        }

        DB::table('usuarios')->whereIn('id', $demo->keys())->update([
            'activo' => false,
            'password_hash' => bcrypt(bin2hex(random_bytes(32))),
            'updated_at' => now(),
        ]);

        echo PHP_EOL."  [SEGURIDAD] {$demo->count()} cuenta(s) demo desactivada(s) en producción:".PHP_EOL;
        foreach ($demo as $email) {
            echo "         - {$email}".PHP_EOL;
        }
    }

    /**
     * BD-05. El rol viejo tenía la unión de permisos de Asesor y Ejecutivo. Se
     * reasigna a Asesor (el de menor privilegio): otorgar 'solicitudes.validar'
     * automáticamente sería una elevación de privilegios encubierta.
     */
    private function saneaRolHuerfano(): void
    {
        $huerfano = DB::table('roles')->where('nombre', 'Servicios Académicos')->first();
        if (! $huerfano) {
            return;
        }

        $asesor = DB::table('roles')->where('nombre', Role::ASESOR)->first();
        if (! $asesor) {
            return; // Sin destino válido no se toca nada.
        }

        $afectados = DB::table('usuarios')->where('rol_id', $huerfano->id)->pluck('email');

        if ($afectados->isNotEmpty()) {
            DB::table('usuarios')->where('rol_id', $huerfano->id)->update(['rol_id' => $asesor->id]);

            echo PHP_EOL."  [BD-05] {$afectados->count()} usuario(s) reasignado(s) de 'Servicios Académicos' a '".Role::ASESOR."':".PHP_EOL;
            foreach ($afectados as $email) {
                echo "         - {$email}".PHP_EOL;
            }
            echo '         Revise si alguno requiere el perfil Ejecutivo Comercial de Admisión.'.PHP_EOL;
        }

        DB::table('rol_permiso')->where('rol_id', $huerfano->id)->delete();
        DB::table('roles')->where('id', $huerfano->id)->delete();
    }

    /**
     * BD-02 y BD-03. Resoluciones que nunca debieron emitirse. No se borran
     * —son documentos oficiales con traza— sino que se anulan dejando constancia.
     */
    private function anulaConvalidacionesInvalidas(): void
    {
        // BD-02: la simulación que las sustenta fue eliminada.
        $sinSustento = DB::table('convalidaciones as c')
            ->join('simulaciones as s', 's.id', '=', 'c.simulacion_id')
            ->where('c.estado', 'confirmada')
            ->whereNotNull('s.deleted_at')
            ->pluck('c.id');

        // BD-03: no reconocen ningún curso.
        $sinCursos = DB::table('convalidaciones as c')
            ->where('c.estado', 'confirmada')
            ->whereRaw('(SELECT COUNT(*) FROM simulacion_detalle d
                         WHERE d.simulacion_id = c.simulacion_id
                           AND d.clasificacion = "convalidable"
                           AND d.excluido = 0
                           AND d.curso_usil_id IS NOT NULL) = 0')
            ->pluck('c.id');

        $this->anular($sinSustento, 'Anulada por auditoría técnica (2026-08-02): la simulación que la sustenta fue eliminada.');
        $this->anular($sinCursos->diff($sinSustento), 'Anulada por auditoría técnica (2026-08-02): no reconoce ningún curso convalidado.');
    }

    private function anular(Collection $ids, string $motivo): void
    {
        if ($ids->isEmpty()) {
            return;
        }

        DB::table('convalidaciones')->whereIn('id', $ids)->update([
            'estado' => 'anulada',
            'motivo_anulacion' => $motivo,
            'updated_at' => now(),
        ]);

        echo PHP_EOL."  [BD-02/03] {$ids->count()} convalidación(es) anulada(s): ".$ids->implode(', ').PHP_EOL;
        echo "         Motivo: {$motivo}".PHP_EOL;
    }

    public function down(): void
    {
        // No reversible: recrear el rol huérfano o reconfirmar resoluciones
        // inválidas reintroduciría los defectos que esta migración corrige.
    }
};
