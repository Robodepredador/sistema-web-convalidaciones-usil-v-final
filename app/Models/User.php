<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements AuthenticatableContract
{
    use Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre', 'email', 'password_hash', 'rol_id', 'activo',
        'primer_acceso', 'token_recuperacion', 'token_expira',
    ];

    protected $hidden = [
        'password_hash', 'remember_token', 'token_recuperacion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'primer_acceso' => 'boolean',
        'bloqueado_hasta' => 'datetime',
        'token_expira' => 'datetime',
        'intentos_fallidos' => 'integer',
    ];

    // El esquema documentado usa 'password_hash' en lugar de 'password'.
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    public function carrerasPermitidas(): BelongsToMany
    {
        // RF-40: carreras que el coordinador/director puede ver/convalidar
        return $this->belongsToMany(Carrera::class, 'permisos_carrera', 'usuario_id', 'carrera_id');
    }

    /** Alcance por facultad (Decano: todas las carreras de su facultad). */
    public function facultadesPermitidas(): BelongsToMany
    {
        return $this->belongsToMany(Facultad::class, 'permisos_facultad', 'usuario_id', 'facultad_id');
    }

    public function esAdministrador(): bool
    {
        return $this->rol?->nombre === Role::SUPERUSUARIO;
    }

    // -------------------- RBAC --------------------

    /** Claves de permiso del usuario (cacheadas en la instancia). */
    public function permisosClaves(): array
    {
        if (! isset($this->attributes['_permisos_cache'])) {
            $this->attributes['_permisos_cache'] = $this->rol
                ? $this->rol->permisos()->pluck('clave')->all()
                : [];
        }

        return $this->attributes['_permisos_cache'];
    }

    /** ¿El usuario tiene el permiso indicado? El Superusuario siempre puede. */
    public function puede(string $clave): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        return in_array($clave, $this->permisosClaves(), true);
    }

    /** Alcance de datos del rol: global | carrera | facultad. */
    public function alcance(): string
    {
        return $this->esAdministrador() ? 'global' : ($this->rol?->alcance() ?? 'global');
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }
}
