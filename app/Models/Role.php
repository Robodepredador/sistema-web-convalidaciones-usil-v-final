<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';

    // Nomenclatura RBAC. Los cinco roles del flujo real del cliente: la cadena
    // de aprobación (Director/Decano/Auditor/Consulta) no tiene equivalente aquí.
    public const SUPERUSUARIO = 'Superusuario';

    public const ADMIN = 'Superusuario';              // alias histórico, se conserva

    public const ASESOR = 'Asesor de Admisión';

    public const EJECUTIVO = 'Ejecutivo Comercial de Admisión';

    /** Registra la política: mallas USIL de sus carreras y equivalencias contra todas las instituciones. */
    public const ESPECIALISTA = 'Especialista en Convalidaciones';

    /** Aplica la política: atiende las simulaciones de sus carreras asignadas. */
    public const ADMINISTRATIVO = 'Administrativo de Facultad';

    public const ALCANCE = [
        self::ESPECIALISTA => 'carrera',
        self::ADMINISTRATIVO => 'carrera',
    ];

    protected $fillable = ['nombre', 'descripcion'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'rol_id');
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'rol_id', 'permiso_id');
    }

    /** Alcance de datos configurado para este rol. */
    public function alcance(): string
    {
        return self::ALCANCE[$this->nombre] ?? 'global';
    }
}
