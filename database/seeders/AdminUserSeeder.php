<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Administrador inicial del sistema.
 *
 * Crea la cuenta si no existe y NO la toca si ya está. Antes usaba
 * `updateOrCreate` con la contraseña `Admin#2026` escrita aquí mismo, y como el
 * runbook manda ejecutar `db:seed` en cada actualización, cada despliegue
 * reponía la contraseña que cualquiera puede leer en el repositorio.
 *
 * La contraseña se genera al azar y se imprime UNA vez, al crearla. No se lee de
 * una variable de entorno a propósito: `config:cache` —que el propio arranque
 * ejecuta— impide que `env()` vea el fichero .env, así que una contraseña
 * definida ahí se ignoraría en silencio y volveríamos a un valor por defecto.
 *
 * Si se pierde: `php artisan usuario:password admin@usil.edu.pe`.
 */
class AdminUserSeeder extends Seeder
{
    public const EMAIL = 'admin@usil.edu.pe';

    public function run(): void
    {
        $rolAdmin = Role::where('nombre', Role::ADMIN)->firstOrFail();

        if (User::where('email', self::EMAIL)->exists()) {
            $this->command?->info('Administrador inicial ya existe: no se modifica su contraseña.');

            return;
        }

        $temporal = Str::password(16);

        User::create([
            'nombre' => 'Administrador del Sistema',
            'email' => self::EMAIL,
            'password_hash' => Hash::make($temporal),
            'rol_id' => $rolAdmin->id,
            'activo' => true,
            'primer_acceso' => true,   // se cambia obligatoriamente al entrar (RF-42)
        ]);

        $this->command?->warn('╔══════════════════════════════════════════════════════════════╗');
        $this->command?->warn('║  ADMINISTRADOR INICIAL CREADO — anote estos datos AHORA      ║');
        $this->command?->warn('╚══════════════════════════════════════════════════════════════╝');
        $this->command?->line('   Usuario:    '.self::EMAIL);
        $this->command?->line('   Contraseña: '.$temporal);
        $this->command?->warn('   No vuelve a mostrarse. Se cambia obligatoriamente al primer acceso.');
    }
}
