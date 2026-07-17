<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';

    // Nomenclatura RBAC. Se conservan ADMIN/COORDINADOR como constantes (con su
    // nuevo valor) para no romper el código existente que las referencia.
    public const ADMIN = 'Superusuario';                 // antes 'Administrador'
    public const SUPERUSUARIO = 'Superusuario';
    public const ASESOR = 'Asesor de Admisión';
    public const EJECUTIVO = 'Ejecutivo Comercial de Admisión';
    public const COORDINADOR = 'Coordinador de Carrera'; // antes 'Coordinador'
    public const DIRECTOR = 'Director de Escuela';
    public const DECANO = 'Decano';
    public const AUDITOR = 'Auditor';
    public const CONSULTA = 'Consulta / Alta Dirección';

    /** Alcance de datos de cada rol: global | carrera | facultad. */
    public const ALCANCE = [
        self::SUPERUSUARIO => 'global',
        self::ASESOR       => 'global',
        self::EJECUTIVO    => 'global',
        self::COORDINADOR  => 'carrera',
        self::DIRECTOR     => 'carrera',
        self::DECANO       => 'facultad',
        self::AUDITOR      => 'global',
        self::CONSULTA     => 'global',
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
