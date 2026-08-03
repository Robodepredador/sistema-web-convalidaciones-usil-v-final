<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cursos_externos', function (Blueprint $table) {
            $table->foreignId('malla_externa_id')->nullable()->after('id')->constrained('mallas_externas')->cascadeOnDelete();
        });

        // Migrate data
        $carrerasWithCursos = DB::table('cursos_externos')->select('carrera_externa_id')->distinct()->pluck('carrera_externa_id');
        foreach ($carrerasWithCursos as $carreraId) {
            $mallaId = DB::table('mallas_externas')->insertGetId([
                'carrera_externa_id' => $carreraId,
                'anio' => date('Y'),
                'version' => '1',
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('cursos_externos')->where('carrera_externa_id', $carreraId)->update(['malla_externa_id' => $mallaId]);
        }

        Schema::table('cursos_externos', function (Blueprint $table) {
            $table->dropForeign(['carrera_externa_id']);
            $table->dropColumn('carrera_externa_id');
            // Assuming MySQL or similar, changing nullable to false after data is filled
            $table->unsignedBigInteger('malla_externa_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cursos_externos', function (Blueprint $table) {
            $table->foreignId('carrera_externa_id')->nullable()->constrained('carreras_externas')->cascadeOnDelete();
        });

        // We can't perfectly restore carrera_externa_id if mallas_externas were deleted or modified heavily,
        // but for a simple rollback we can join back.
        $cursos = DB::table('cursos_externos')
            ->join('mallas_externas', 'cursos_externos.malla_externa_id', '=', 'mallas_externas.id')
            ->select('cursos_externos.id', 'mallas_externas.carrera_externa_id')
            ->get();

        foreach ($cursos as $c) {
            DB::table('cursos_externos')->where('id', $c->id)->update(['carrera_externa_id' => $c->carrera_externa_id]);
        }

        Schema::table('cursos_externos', function (Blueprint $table) {
            $table->dropForeign(['malla_externa_id']);
            $table->dropColumn('malla_externa_id');
        });
    }
};
