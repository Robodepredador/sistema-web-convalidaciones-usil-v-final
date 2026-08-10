<?php

namespace App\Console\Commands;

use App\Models\Postulante;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Salida de emergencia cuando el correo no está disponible.
 *
 * El correo es el único canal por el que salen las contraseñas. Si el servidor
 * SMTP todavía no está configurado —o falla— nadie puede entrar y no hay forma
 * de arrancar el sistema. Este comando genera una contraseña temporal y la
 * imprime en la consola, para que quien administre el servidor pueda entregarla
 * por el medio que corresponda.
 *
 * Sirve para el personal y para el postulante: se busca en las dos tablas.
 */
class UsuarioPassword extends Command
{
    protected $signature = 'usuario:password {email : Correo del usuario o del postulante}';

    protected $description = 'Genera una contraseña temporal y la muestra en consola (para arrancar sin SMTP)';

    public function handle(): int
    {
        $email = trim($this->argument('email'));

        if ($usuario = User::where('email', $email)->first()) {
            return $this->restablecer(
                fn (string $hash) => $usuario->forceFill([
                    'password_hash' => $hash,
                    'primer_acceso' => true,
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta' => null,
                ])->save(),
                'Personal', $usuario->nombre, $email
            );
        }

        if ($postulante = Postulante::where('email', $email)->first()) {
            return $this->restablecer(
                fn (string $hash) => $postulante->forceFill([
                    'password_hash' => $hash,
                    'acceso_habilitado' => true,
                    'debe_cambiar_password' => true,
                ])->save(),
                'Postulante', $postulante->nombre_completo, $email
            );
        }

        $this->error("No existe ningún usuario ni postulante con el correo {$email}.");

        return self::FAILURE;
    }

    private function restablecer(callable $guardar, string $perfil, string $nombre, string $email): int
    {
        $temporal = Str::password(14);
        $guardar(Hash::make($temporal));

        $this->newLine();
        $this->info("{$perfil}: {$nombre}");
        $this->line("  Usuario:    {$email}");
        $this->line("  Contraseña: {$temporal}");
        $this->newLine();
        $this->warn('Se pedirá cambiarla en el primer acceso. Entréguela por un canal seguro:');
        $this->warn('no vuelve a mostrarse y queda en el historial de esta terminal.');

        return self::SUCCESS;
    }
}
