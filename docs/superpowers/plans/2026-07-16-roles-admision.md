# División del rol Servicios Académicos — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar el rol `Servicios Académicos` por **Asesor de Admisión** (registra) y **Ejecutivo Comercial de Admisión** (revisa y aprueba/observa), y bloquear que el Coordinador vea cualquier expediente no aprobado.

**Architecture:** Laravel 11 + Inertia + Vue 3. La revisión es un estado en la tabla `postulantes` (`revision_estado`: pendiente/aprobada/observada). El Ejecutivo aprueba u observa; el Asesor corrige y reenvía. El gate al Coordinador se aplica filtrando `SimulacionController@index` a postulantes aprobados, con `abort_unless` de defensa en profundidad en `crear`/`persistirSimulacion`.

**Tech Stack:** PHP 8.2, Laravel, Inertia.js, Vue 3, Tailwind, PHPUnit (SQLite en memoria para tests), Vite.

## Global Constraints

- Rol constantes en `App\Models\Role`; permisos en `App\Models\Permiso::CATALOGO` / `POR_ROL`. No crear permisos nuevos: reutilizar `solicitudes.validar` para aprobar/observar.
- Ambos roles nuevos tienen alcance de carrera `global`. La restricción "solo lo suyo" del Asesor es por `postulantes.usuario_id`, aplicada en el controlador (no en `AlcanceService`).
- Toda acción con efecto se audita vía `App\Services\AuditoriaService::registrar(...)`.
- Mensajes de usuario en español.
- Estados de revisión exactos: `pendiente`, `aprobada`, `observada`. `observada` exige texto en `revision_observaciones`.
- Tests: `php artisan test` (SQLite en memoria, `RefreshDatabase`). Los cambios de Vue se verifican en el navegador (no hay test runner JS).

---

### Task 1: RBAC — reemplazar SERVICIOS por ASESOR y EJECUTIVO

**Files:**
- Modify: `app/Models/Role.php`
- Modify: `app/Models/Permiso.php`
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/RbacTest.php`

**Interfaces:**
- Produces: `Role::ASESOR = 'Asesor de Admisión'`, `Role::EJECUTIVO = 'Ejecutivo Comercial de Admisión'`; se elimina `Role::SERVICIOS`.
- Produces: permisos por rol — Asesor: `dashboard.ver, solicitudes.ver, solicitudes.crear, solicitudes.editar`; Ejecutivo: `dashboard.ver, solicitudes.ver, solicitudes.editar, solicitudes.validar, reportes.ver`.

- [ ] **Step 1: Actualizar el test RBAC (reemplaza el test de servicios)**

En `tests/Feature/RbacTest.php`, reemplazar íntegro el método `test_servicios_no_gestiona_equivalencias` (líneas 73–85) por estos dos:

```php
    /** El Asesor de Admisión registra postulantes pero no evalúa. */
    public function test_asesor_gestiona_postulantes_no_evaluacion(): void
    {
        $asesor = $this->usuarioConRol(Role::ASESOR);

        $this->actingAs($asesor)->get('/postulantes')->assertOk();
        $this->actingAs($asesor)->get('/simulaciones')->assertForbidden();
        $this->actingAs($asesor)->get('/equivalencias')->assertForbidden();
        // (La denegación de la ruta de revisión se prueba en RevisionFlujoTest, donde ya existe la ruta.)
    }

    /** El Ejecutivo Comercial revisa expedientes y ve reportes; no evalúa. */
    public function test_ejecutivo_revisa_y_ve_reportes(): void
    {
        $ejecutivo = $this->usuarioConRol(Role::EJECUTIVO);

        $this->actingAs($ejecutivo)->get('/postulantes')->assertOk();
        $this->actingAs($ejecutivo)->get('/reportes')->assertOk();
        $this->actingAs($ejecutivo)->get('/simulaciones')->assertForbidden();
    }
```

- [ ] **Step 2: Correr el test — debe fallar por constante inexistente**

Run: `php artisan test --filter=RbacTest`
Expected: FAIL (error: `Undefined constant App\Models\Role::ASESOR` / `EJECUTIVO`).

- [ ] **Step 3: Editar `app/Models/Role.php`**

Reemplazar la línea `public const SERVICIOS = 'Servicios Académicos';` (línea 17) por:

```php
    public const ASESOR = 'Asesor de Admisión';
    public const EJECUTIVO = 'Ejecutivo Comercial de Admisión';
```

En el mapa `ALCANCE` (líneas 25–33), reemplazar la línea `self::SERVICIOS    => 'global',` por:

```php
        self::ASESOR       => 'global',
        self::EJECUTIVO     => 'global',
```

- [ ] **Step 4: Editar `app/Models/Permiso.php`**

En `POR_ROL` (líneas 56–85), reemplazar el bloque `Role::SERVICIOS => [ ... ],` (líneas 58–62) por:

```php
        // Asesor de Admisión: registra postulantes y sus documentos; no evalúa ni aprueba.
        Role::ASESOR => [
            'dashboard.ver', 'solicitudes.ver', 'solicitudes.crear', 'solicitudes.editar',
        ],
        // Ejecutivo Comercial de Admisión: revisa, aprueba u observa; puede corregir datos.
        Role::EJECUTIVO => [
            'dashboard.ver', 'solicitudes.ver', 'solicitudes.editar', 'solicitudes.validar', 'reportes.ver',
        ],
```

- [ ] **Step 5: Editar `database/seeders/RoleSeeder.php`**

En el arreglo `$roles` (líneas 14–22), reemplazar la línea `Role::SERVICIOS    => 'Recibe la solicitud e inicia el flujo (Servicios Académicos)',` por:

```php
            Role::ASESOR       => 'Registra al postulante, sus datos y documentos (Admisión)',
            Role::EJECUTIVO    => 'Revisa y aprueba/observa los expedientes de admisión',
```

- [ ] **Step 6: Correr el test — debe pasar**

Run: `php artisan test --filter=RbacTest`
Expected: PASS (todos los métodos de RbacTest en verde).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Role.php app/Models/Permiso.php database/seeders/RoleSeeder.php tests/Feature/RbacTest.php
git commit -m "feat(rbac): reemplaza Servicios Académicos por Asesor y Ejecutivo Comercial de Admisión"
```

---

### Task 2: Migración y modelo — estado de revisión en postulantes

**Files:**
- Create: `database/migrations/2026_07_16_000001_add_revision_to_postulantes.php`
- Modify: `app/Models/Postulante.php`
- Test: `tests/Feature/PostulanteRevisionTest.php`

**Interfaces:**
- Produces: columnas `postulantes.revision_estado` (default `'pendiente'`), `revision_observaciones` (text null), `revisado_por` (FK usuarios null), `revisado_en` (timestamp null).
- Produces: `Postulante->revisadoPor()` relación BelongsTo a `User`; campos en `$fillable`; cast de `revisado_en`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/PostulanteRevisionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Postulante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostulanteRevisionTest extends TestCase
{
    use RefreshDatabase;

    /** Un postulante nuevo arranca pendiente de revisión. */
    public function test_postulante_arranca_pendiente(): void
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-99999', 'tipo_documento' => 'DNI', 'numero_documento' => '99999999',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana@example.com',
        ]);

        $this->assertSame('pendiente', $p->fresh()->revision_estado);
    }
}
```

- [ ] **Step 2: Correr el test — debe fallar**

Run: `php artisan test --filter=PostulanteRevisionTest`
Expected: FAIL (columna `revision_estado` inexistente / `Undefined column`).

- [ ] **Step 3: Crear la migración**

Crear `database/migrations/2026_07_16_000001_add_revision_to_postulantes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->enum('revision_estado', ['pendiente', 'aprobada', 'observada'])
                ->default('pendiente')->after('estado');
            $table->text('revision_observaciones')->nullable()->after('revision_estado');
            $table->foreignId('revisado_por')->nullable()->after('revision_observaciones')
                ->constrained('usuarios')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable()->after('revisado_por');
            $table->index('revision_estado');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropColumn(['revision_estado', 'revision_observaciones', 'revisado_en']);
        });
    }
};
```

- [ ] **Step 4: Actualizar `app/Models/Postulante.php`**

En `$fillable` (líneas 22–29), añadir al final del arreglo (antes del `];`) estas claves:

```php
        'revision_estado', 'revision_observaciones', 'revisado_por', 'revisado_en',
```

En `$casts` (líneas 33–38), añadir dentro del arreglo:

```php
        'revisado_en'                => 'datetime',
```

Añadir esta relación después de `documentos()` (tras la línea 130):

```php
    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
```

- [ ] **Step 5: Correr el test — debe pasar**

Run: `php artisan test --filter=PostulanteRevisionTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_16_000001_add_revision_to_postulantes.php app/Models/Postulante.php tests/Feature/PostulanteRevisionTest.php
git commit -m "feat(postulantes): agrega estado de revisión (pendiente/aprobada/observada)"
```

---

### Task 3: Acciones revisar / reenviar + rutas + alcance del Asesor

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/PostulanteController.php`
- Test: `tests/Feature/RevisionFlujoTest.php`

**Interfaces:**
- Consumes: `Role::ASESOR`, `Role::EJECUTIVO` (Task 1); `Postulante->revision_estado` (Task 2).
- Produces:
  - `POST /postulantes/{postulante}/revisar` → `PostulanteController@revisar` (body: `accion` ∈ `aprobar|observar`, `observaciones` requerido si observar). Ruta `postulantes.revisar`, middleware `permission:solicitudes.validar`.
  - `POST /postulantes/{postulante}/reenviar-revision` → `PostulanteController@reenviarRevision`. Ruta `postulantes.reenviar-revision`, middleware `permission:solicitudes.editar`.
  - `PostulanteController` filtra `index` por `usuario_id` cuando el rol es Asesor y usa `autorizarPropiedad()` en edit/update/destroy/reenviar.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/RevisionFlujoTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Postulante;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RevisionFlujoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rolNombre): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $rol = Role::where('nombre', $rolNombre)->firstOrFail();

        return User::create([
            'nombre' => $rolNombre, 'email' => uniqid() . '@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    private function postulanteDe(User $asesor): Postulante
    {
        return Postulante::create([
            'codigo' => 'POST-2026-' . random_int(10000, 99999),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(10000000, 99999999),
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => uniqid() . '@ex.com',
            'usuario_id' => $asesor->id,
        ]);
    }

    public function test_ejecutivo_aprueba_expediente(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulanteDe($asesor);

        $this->actingAs($ejecutivo)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar'])
            ->assertRedirect();

        $this->assertSame('aprobada', $p->fresh()->revision_estado);
        $this->assertSame($ejecutivo->id, $p->fresh()->revisado_por);
    }

    public function test_observar_exige_texto(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulanteDe($asesor);

        $this->actingAs($ejecutivo)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'observar'])
            ->assertSessionHasErrors('observaciones');

        $this->assertSame('pendiente', $p->fresh()->revision_estado);
    }

    public function test_asesor_reenvia_expediente_observado(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulanteDe($asesor);

        $this->actingAs($ejecutivo)->post("/postulantes/{$p->id}/revisar",
            ['accion' => 'observar', 'observaciones' => 'Falta el sílabo de Cálculo.']);
        $this->assertSame('observada', $p->fresh()->revision_estado);

        $this->actingAs($asesor)->post("/postulantes/{$p->id}/reenviar-revision")->assertRedirect();
        $this->assertSame('pendiente', $p->fresh()->revision_estado);
    }

    public function test_asesor_no_puede_aprobar(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $p = $this->postulanteDe($asesor);

        // No tiene el permiso solicitudes.validar → 403.
        $this->actingAs($asesor)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar'])
            ->assertForbidden();
    }

    public function test_asesor_solo_ve_sus_postulantes(): void
    {
        $asesorA = $this->usuario(Role::ASESOR);
        $asesorB = $this->usuario(Role::ASESOR);
        $mio = $this->postulanteDe($asesorA);
        $ajeno = $this->postulanteDe($asesorB);

        // La lista paginada de Inertia solo trae el propio (1 fila en postulantes.data).
        $this->actingAs($asesorA)->get('/postulantes')
            ->assertInertia(fn ($page) => $page->has('postulantes.data', 1));

        // No puede editar el ajeno.
        $this->actingAs($asesorA)->get("/postulantes/{$ajeno->id}/editar")->assertForbidden();
    }
}
```

> Nota: `assertInertia` usa `Inertia\Testing\AssertableInertia`. `postulantes` es el paginador; sus filas están en `postulantes.data`.

- [ ] **Step 2: Correr el test — debe fallar**

Run: `php artisan test --filter=RevisionFlujoTest`
Expected: FAIL (rutas `postulantes.revisar` / `reenviar-revision` inexistentes → 404/405).

- [ ] **Step 3: Añadir las rutas en `routes/web.php`**

Dentro del grupo `Route::middleware('auth')`, en la sección de postulantes, después del grupo `Route::middleware('permission:solicitudes.editar')` que termina en la línea 186 (`}); // fin solicitudes.editar (postulantes)`), añadir:

```php
        // Revisión de admisión: el Ejecutivo Comercial aprueba u observa.
        Route::middleware('permission:solicitudes.validar')->group(function () {
            Route::post('postulantes/{postulante}/revisar', [PostulanteController::class, 'revisar'])
                ->name('postulantes.revisar');
        });
        // El Asesor reenvía a revisión un expediente observado (tras corregirlo).
        Route::middleware('permission:solicitudes.editar')->group(function () {
            Route::post('postulantes/{postulante}/reenviar-revision', [PostulanteController::class, 'reenviarRevision'])
                ->name('postulantes.reenviar-revision');
        });
```

- [ ] **Step 4: Añadir imports y métodos en `PostulanteController.php`**

Añadir los imports que falten al inicio (junto a los `use` existentes):

```php
use App\Models\Role;
use App\Models\User;
```

En `index()`, después de la línea `$visibles = \App\Services\AlcanceService::carrerasVisibles($request->user());` (línea 28), y dentro de la cadena de query (añadir un `->when(...)` junto a los demás, p. ej. justo antes de `->orderByDesc('id')` en la línea 48):

```php
            // El Asesor solo ve los postulantes que él registró.
            ->when($request->user()->rol?->nombre === Role::ASESOR,
                fn ($x) => $x->where('usuario_id', $request->user()->id))
```

Cambiar la firma de `edit` (línea 120) de `public function edit(Postulante $postulante)` a:

```php
    public function edit(Request $request, Postulante $postulante)
```

y como primera línea del cuerpo de `edit`:

```php
        $this->autorizarPropiedad($request->user(), $postulante);
```

En `update` (línea 236) añadir como primera línea del cuerpo:

```php
        $this->autorizarPropiedad($request->user(), $postulante);
```

Cambiar la firma de `destroy` (línea 277) a `public function destroy(Request $request, Postulante $postulante)` y como primera línea del cuerpo:

```php
        $this->autorizarPropiedad($request->user(), $postulante);
```

Añadir estos tres métodos nuevos (por ejemplo tras `destroy`, antes de `extraerDestinos`):

```php
    /** El Ejecutivo Comercial aprueba u observa el expediente. */
    public function revisar(Request $request, Postulante $postulante): RedirectResponse
    {
        $datos = $request->validate([
            'accion'        => ['required', 'in:aprobar,observar'],
            'observaciones' => ['required_if:accion,observar', 'nullable', 'string', 'max:1000'],
        ], [
            'observaciones.required_if' => 'Indica qué debe corregir el postulante.',
        ]);

        $aprobar = $datos['accion'] === 'aprobar';
        $postulante->update([
            'revision_estado'        => $aprobar ? 'aprobada' : 'observada',
            'revision_observaciones' => $aprobar ? null : $datos['observaciones'],
            'revisado_por'           => $request->user()->id,
            'revisado_en'            => now(),
        ]);

        AuditoriaService::registrar($aprobar ? 'aprobar' : 'observar', 'postulantes', $postulante->id, null, [
            'revision_estado' => $postulante->revision_estado,
        ]);

        return back()->with('status', $aprobar
            ? "Expediente {$postulante->codigo} aprobado. Ya puede evaluarse."
            : "Expediente {$postulante->codigo} observado. La observación es visible para el asesor y el postulante.");
    }

    /** El Asesor dueño reenvía a revisión un expediente observado ya corregido. */
    public function reenviarRevision(Request $request, Postulante $postulante): RedirectResponse
    {
        $this->autorizarPropiedad($request->user(), $postulante);
        abort_unless($postulante->revision_estado === 'observada', 422, 'Solo se puede reenviar un expediente observado.');

        $postulante->update([
            'revision_estado'        => 'pendiente',
            'revision_observaciones' => null,
            'revisado_por'           => null,
            'revisado_en'            => null,
        ]);

        AuditoriaService::registrar('reenviar', 'postulantes', $postulante->id);

        return back()->with('status', 'Expediente reenviado a revisión.');
    }

    /** El Asesor solo opera sobre sus propios postulantes; Ejecutivo/Superusuario, todos. */
    private function autorizarPropiedad(User $user, Postulante $postulante): void
    {
        if ($user->rol?->nombre === Role::ASESOR) {
            abort_unless($postulante->usuario_id === $user->id, 403, 'Solo puedes gestionar los postulantes que registraste.');
        }
    }
```

- [ ] **Step 5: Correr el test — debe pasar**

Run: `php artisan test --filter=RevisionFlujoTest`
Expected: PASS. (Si `test_asesor_solo_ve_sus_postulantes` falla por la forma de la prop paginada, ajustar el aserto Inertia según la nota del Step 1.)

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/PostulanteController.php tests/Feature/RevisionFlujoTest.php
git commit -m "feat(admision): acciones revisar/reenviar y alcance propio del asesor"
```

---

### Task 4: Gate al Coordinador en SimulacionController

**Files:**
- Modify: `app/Http/Controllers/SimulacionController.php`
- Test: `tests/Feature/GateSimulacionTest.php`

**Interfaces:**
- Consumes: `Postulante->revision_estado` (Task 2).
- Produces: `SimulacionController@index` solo lista destinos cuyo postulante está `aprobada`; `crear` y `persistirSimulacion` abortan 403 si no está aprobada.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/GateSimulacionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Postulante;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GateSimulacionTest extends TestCase
{
    use RefreshDatabase;

    private function coordinador(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $rol = Role::where('nombre', Role::COORDINADOR)->firstOrFail();
        $u = User::create([
            'nombre' => 'Coord', 'email' => 'coord@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
        $u->carrerasPermitidas()->sync(\App\Models\Carrera::pluck('id'));

        return $u;
    }

    private function postulante(string $estado): Postulante
    {
        return Postulante::create([
            'codigo' => 'POST-2026-' . random_int(10000, 99999),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(10000000, 99999999),
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => uniqid() . '@ex.com',
            'revision_estado' => $estado,
        ]);
    }

    public function test_crear_simulacion_bloqueada_sin_aprobacion(): void
    {
        $coord = $this->coordinador();
        $p = $this->postulante('pendiente');

        $this->actingAs($coord)->get("/simulaciones/simular/{$p->id}")->assertForbidden();
    }

    public function test_gate_se_levanta_al_aprobar(): void
    {
        $coord = $this->coordinador();
        $p = $this->postulante('aprobada');

        // Robusto ante datos faltantes: basta comprobar que el gate ya no bloquea (no es 403).
        $status = $this->actingAs($coord)->get("/simulaciones/simular/{$p->id}")->getStatusCode();
        $this->assertNotSame(403, $status, 'Un expediente aprobado no debe ser bloqueado por el gate.');
    }
}
```

- [ ] **Step 2: Correr el test — debe fallar**

Run: `php artisan test --filter=GateSimulacionTest`
Expected: FAIL (`test_crear_simulacion_bloqueada_sin_aprobacion` da 200 en vez de 403).

- [ ] **Step 3: Filtrar `index` a postulantes aprobados**

En `SimulacionController@index`, reemplazar la línea 57 `->whereHas('postulante')` por:

```php
            ->whereHas('postulante', fn ($p) => $p->where('revision_estado', 'aprobada'))
```

- [ ] **Step 4: Gate en `crear` y `persistirSimulacion`**

En `crear` (línea 95), añadir como primera línea del cuerpo:

```php
        abort_unless($postulante->revision_estado === 'aprobada', 403,
            'La solicitud aún no ha sido aprobada por el Ejecutivo Comercial de Admisión.');
```

En `persistirSimulacion`, justo después de `$postulante = Postulante::findOrFail($datos['postulante_id']);` (línea 429), añadir:

```php
        abort_unless($postulante->revision_estado === 'aprobada', 403,
            'La solicitud aún no ha sido aprobada por el Ejecutivo Comercial de Admisión.');
```

- [ ] **Step 5: Correr el test — debe pasar**

Run: `php artisan test --filter=GateSimulacionTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/SimulacionController.php tests/Feature/GateSimulacionTest.php
git commit -m "feat(simulaciones): el coordinador solo ve/simula expedientes aprobados por admisión"
```

---

### Task 5: Dashboard para Asesor y Ejecutivo + bandeja del coordinador filtrada

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/DashboardAdmisionTest.php`

**Interfaces:**
- Consumes: `Role::ASESOR`, `Role::EJECUTIVO`, `Postulante->revision_estado`.
- Produces: KPIs y bandeja específicos por rol; la bandeja de las etapas de evaluación (no-admisión) solo incluye destinos de postulantes aprobados.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/DashboardAdmisionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardAdmisionTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rolNombre): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $rol = Role::where('nombre', $rolNombre)->firstOrFail();

        return User::create([
            'nombre' => $rolNombre, 'email' => uniqid() . '@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    public function test_dashboard_asesor_ok(): void
    {
        $this->actingAs($this->usuario(Role::ASESOR))->get('/')->assertOk();
    }

    public function test_dashboard_ejecutivo_ok(): void
    {
        $this->actingAs($this->usuario(Role::EJECUTIVO))->get('/')->assertOk();
    }
}
```

- [ ] **Step 2: Correr el test — debe fallar**

Run: `php artisan test --filter=DashboardAdmisionTest`
Expected: FAIL o error (el `switch` cae en `default` de Superusuario y usa consultas no pensadas para estos roles; el objetivo es darles su propio caso). Si diera 200 por el default, igual continuar para implementar los KPIs correctos.

- [ ] **Step 3: Importar Postulante en `DashboardController.php`**

Añadir junto a los `use` existentes:

```php
use App\Models\Postulante;
```

- [ ] **Step 4: Añadir los casos ASESOR y EJECUTIVO en `kpis()`**

En el `switch ($rol)` de `kpis()`, reemplazar el bloque completo `case Role::SERVICIOS:` (líneas 62–68) por:

```php
            case Role::ASESOR:
                $mios = Postulante::where('usuario_id', $user->id);
                return [
                    ['label' => 'Mis solicitudes', 'valor' => (clone $mios)->count(), 'color' => 'blue'],
                    ['label' => 'En revisión', 'valor' => (clone $mios)->where('revision_estado', 'pendiente')->count(), 'color' => 'amber'],
                    ['label' => 'Observadas por corregir', 'valor' => (clone $mios)->where('revision_estado', 'observada')->count(), 'color' => 'orange'],
                    ['label' => 'Aprobadas', 'valor' => (clone $mios)->where('revision_estado', 'aprobada')->count(), 'color' => 'green'],
                ];

            case Role::EJECUTIVO:
                return [
                    ['label' => 'Por revisar', 'valor' => Postulante::where('revision_estado', 'pendiente')->count(), 'color' => 'amber'],
                    ['label' => 'Observadas', 'valor' => Postulante::where('revision_estado', 'observada')->count(), 'color' => 'orange'],
                    ['label' => 'Aprobadas', 'valor' => Postulante::where('revision_estado', 'aprobada')->count(), 'color' => 'green'],
                    ['label' => 'Solicitudes totales', 'valor' => Postulante::count(), 'color' => 'blue'],
                ];
```

- [ ] **Step 5: Reescribir `bandeja()` para admisión y filtro de aprobados**

Reemplazar el método `bandeja()` completo (líneas 123–140) por:

```php
    /** Bandeja de pendientes (hasta 6) relevante al rol. */
    private function bandeja($destinos, ?string $rol, User $user): array
    {
        // Admisión: su bandeja se arma sobre postulantes por estado de revisión.
        if ($rol === Role::EJECUTIVO) {
            return $this->bandejaPostulantes(Postulante::where('revision_estado', 'pendiente'));
        }
        if ($rol === Role::ASESOR) {
            return $this->bandejaPostulantes(
                Postulante::where('usuario_id', $user->id)->where('revision_estado', 'observada')
            );
        }

        // Etapas de evaluación: solo expedientes ya aprobados por Admisión.
        return (clone $destinos)
            ->whereHas('postulante', fn ($p) => $p->where('revision_estado', 'aprobada'))
            ->with(['postulante:id,nombres,apellido_paterno,apellido_materno', 'carrera:id,nombre'])
            ->where('estado_equivalencias', '!=', 'aprobada')
            ->orderByDesc('id')->limit(6)->get()
            ->map(fn (PostulanteDestino $d) => [
                'titulo'    => $d->postulante
                    ? trim("{$d->postulante->apellido_paterno} {$d->postulante->apellido_materno}, {$d->postulante->nombres}")
                    : '—',
                'subtitulo' => $d->carrera?->nombre,
                'estado'    => $d->estado_equivalencias,
            ])->all();
    }

    /** Bandeja construida sobre postulantes (para los roles de Admisión). */
    private function bandejaPostulantes($query): array
    {
        return $query->with('carreraDestino:id,nombre')->orderByDesc('id')->limit(6)->get()
            ->map(fn (Postulante $p) => [
                'titulo'    => $p->nombre_completo,
                'subtitulo' => $p->carreraDestino?->nombre,
                'estado'    => $p->revision_estado,
            ])->all();
    }
```

- [ ] **Step 6: Pasar `$user` a `bandeja()` en `index()`**

En `index()`, cambiar la línea `'bandeja'  => $this->bandeja($destinos, $rol),` (línea 39) por:

```php
                'bandeja'  => $this->bandeja($destinos, $rol, $user),
```

- [ ] **Step 7: Correr el test — debe pasar**

Run: `php artisan test --filter=DashboardAdmisionTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/DashboardController.php tests/Feature/DashboardAdmisionTest.php
git commit -m "feat(dashboard): paneles de Asesor y Ejecutivo; bandeja de evaluación solo aprobados"
```

---

### Task 6: Cuentas demo y botones de login

**Files:**
- Modify: `database/seeders/DemoUsersSeeder.php`
- Modify: `app/Http/Controllers/Auth/LoginController.php`
- Test: `tests/Feature/DemoUsersSeederTest.php`

**Interfaces:**
- Consumes: `Role::ASESOR`, `Role::EJECUTIVO`.
- Produces: cuentas demo `asesor.demo@usil.edu.pe` y `ejecutivo.demo@usil.edu.pe`; botones demo del login actualizados.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/DemoUsersSeederTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_cuentas_de_admision(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\DemoUsersSeeder::class);

        $this->assertDatabaseHas('usuarios', ['email' => 'asesor.demo@usil.edu.pe']);
        $this->assertDatabaseHas('usuarios', ['email' => 'ejecutivo.demo@usil.edu.pe']);
        $this->assertDatabaseMissing('usuarios', ['email' => 'servicios.demo@usil.edu.pe']);
    }
}
```

- [ ] **Step 2: Correr el test — debe fallar**

Run: `php artisan test --filter=DemoUsersSeederTest`
Expected: FAIL (no existen `asesor.demo` / `ejecutivo.demo`).

- [ ] **Step 3: Editar `database/seeders/DemoUsersSeeder.php`**

En el arreglo `$usuariosDemo`, reemplazar la línea del rol Servicios:

```php
            ['email' => 'servicios.demo@usil.edu.pe',  'rol' => Role::SERVICIOS,    'nombre' => 'Servicios Demo'],
```

por:

```php
            ['email' => 'asesor.demo@usil.edu.pe',     'rol' => Role::ASESOR,       'nombre' => 'Asesor Demo'],
            ['email' => 'ejecutivo.demo@usil.edu.pe',  'rol' => Role::EJECUTIVO,    'nombre' => 'Ejecutivo Demo'],
```

- [ ] **Step 4: Editar `app/Http/Controllers/Auth/LoginController.php`**

En la lista de cuentas demo (línea 30), reemplazar:

```php
            ['label' => 'Servicios Académicos', 'email' => 'servicios.demo@usil.edu.pe', 'password' => 'Demo#1234'],
```

por:

```php
            ['label' => 'Asesor de Admisión', 'email' => 'asesor.demo@usil.edu.pe', 'password' => 'Demo#1234'],
            ['label' => 'Ejecutivo Comercial de Admisión', 'email' => 'ejecutivo.demo@usil.edu.pe', 'password' => 'Demo#1234'],
```

- [ ] **Step 5: Correr el test — debe pasar**

Run: `php artisan test --filter=DemoUsersSeederTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/DemoUsersSeeder.php app/Http/Controllers/Auth/LoginController.php tests/Feature/DemoUsersSeederTest.php
git commit -m "feat(demo): cuentas y botones de login para Asesor y Ejecutivo"
```

---

### Task 7: UI — badge de revisión (Index) y panel de revisión (Form)

**Files:**
- Modify: `app/Http/Controllers/PostulanteController.php` (props de `index` y `edit`)
- Modify: `resources/js/Pages/Postulantes/Index.vue`
- Modify: `resources/js/Pages/Postulantes/Form.vue`

**Interfaces:**
- Consumes: rutas `postulantes.revisar` y `postulantes.reenviar-revision` (Task 3).
- Produces: `index` envía `revision` por fila; `edit` envía objeto `revision` con `estado, observaciones, revisado_por, revisado_en, puede_revisar, puede_reenviar`.

> Nota de diseño (simplificación deliberada): las acciones Aprobar/Observar/Reenviar viven en el expediente (Form), única ruta de código. El listado (Index) solo muestra el badge de estado de revisión. // ponytail: un solo lugar para las acciones; mover al Index si se pide UX de bandeja.

- [ ] **Step 1: Enviar `revision` en las props de `index()`**

En `PostulanteController@index`, dentro del `->through(fn (Postulante $p) => [ ... ])` (líneas 50–61), añadir una clave:

```php
                'revision'        => $p->revision_estado,
```

- [ ] **Step 2: Enviar `revision` en las props de `edit()`**

En `PostulanteController@edit`, dentro del `return inertia('Postulantes/Form', $this->opciones() + [ ... ])`, añadir al arreglo (junto a `preconvalidaciones` / `preconvalidacion_estado`):

```php
            'revision' => [
                'estado'         => $postulante->revision_estado,
                'observaciones'  => $postulante->revision_observaciones,
                'revisado_en'    => optional($postulante->revisado_en)->format('d/m/Y H:i'),
                'revisado_por'   => $postulante->revisadoPor?->nombre,
                'puede_revisar'  => $request->user()->puede('solicitudes.validar'),
                'puede_reenviar' => $request->user()->puede('solicitudes.editar')
                    && ($request->user()->rol?->nombre !== \App\Models\Role::ASESOR
                        || $postulante->usuario_id === $request->user()->id),
            ],
```

> `edit` ya recibe `Request $request` tras Task 3. Cargar la relación no es obligatorio (acceso perezoso), pero para evitar consultas extra puedes añadir `'revisadoPor'` al `$postulante->load([...])` de las líneas 122–128.

- [ ] **Step 3: Badge de revisión en `Index.vue`**

En `<script setup>`, junto al mapa `PRECONV` (líneas 29–33), añadir:

```js
const REVISION = {
    pendiente: { label: 'Pendiente de revisión', clase: 'bg-amber-50 text-amber-700 ring-amber-200' },
    aprobada:  { label: 'Aprobada',              clase: 'bg-green-50 text-green-700 ring-green-200' },
    observada: { label: 'Observada',             clase: 'bg-orange-50 text-orange-700 ring-orange-200' },
};
```

En la tabla de postulantes, añadir una celda que muestre el estado de revisión de cada fila (`p.revision`), replicando el patrón visual de los otros badges de la fila:

```html
<span v-if="p.revision"
      :class="REVISION[p.revision]?.clase"
      class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset">
    {{ REVISION[p.revision]?.label ?? p.revision }}
</span>
```

(Colocarlo junto a la columna de estado/preconvalidación existente; añadir también el `<th>` correspondiente en la cabecera si la tabla las declara.)

- [ ] **Step 4: Panel de revisión en `Form.vue`**

En `defineProps` (líneas 6–9), añadir la prop:

```js
    revision: { type: Object, default: null },
```

En `<script setup>`, cambiar el import de la línea 2 para incluir `router`:

```js
import { useForm, Link, router } from '@inertiajs/vue3';
```

y añadir la lógica de revisión (por ejemplo tras la definición de `form`):

```js
const obs = reactive({ observaciones: '' });
const REV = {
    pendiente: { label: 'Pendiente de revisión', clase: 'border-amber-200 bg-amber-50 text-amber-800' },
    aprobada:  { label: 'Aprobada',              clase: 'border-green-200 bg-green-50 text-green-800' },
    observada: { label: 'Observada',             clase: 'border-orange-200 bg-orange-50 text-orange-800' },
};
const aprobar = () => router.post(`/postulantes/${props.postulante.id}/revisar`,
    { accion: 'aprobar' }, { preserveScroll: true });
const observar = () => {
    if (!obs.observaciones.trim()) return;
    router.post(`/postulantes/${props.postulante.id}/revisar`,
        { accion: 'observar', observaciones: obs.observaciones }, { preserveScroll: true });
};
const reenviar = () => router.post(`/postulantes/${props.postulante.id}/reenviar-revision`,
    {}, { preserveScroll: true });
```

En el `<template>`, después del encabezado del formulario (cuando `editando` es true), añadir el bloque de revisión:

```html
<div v-if="editando && revision" class="mb-6 rounded-lg border p-4" :class="REV[revision.estado]?.clase">
    <div class="flex items-center justify-between">
        <p class="text-sm font-semibold">Revisión de admisión: {{ REV[revision.estado]?.label ?? revision.estado }}</p>
        <span v-if="revision.revisado_por" class="text-xs opacity-80">
            {{ revision.revisado_por }} · {{ revision.revisado_en }}
        </span>
    </div>

    <!-- Observación visible para el asesor -->
    <p v-if="revision.estado === 'observada' && revision.observaciones" class="mt-2 text-sm">
        <span class="font-medium">Observación:</span> {{ revision.observaciones }}
    </p>

    <!-- Ejecutivo Comercial: aprobar u observar -->
    <div v-if="revision.puede_revisar" class="mt-3 space-y-2">
        <textarea v-model="obs.observaciones" rows="2" placeholder="Detalle de la observación (obligatorio para observar)"
                  class="w-full rounded-md border-slate-300 text-sm"></textarea>
        <div class="flex gap-2">
            <button type="button" @click="aprobar"
                    class="rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800">Aprobar</button>
            <button type="button" @click="observar" :disabled="!obs.observaciones.trim()"
                    class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-40">Observar</button>
        </div>
    </div>

    <!-- Asesor dueño: reenviar tras corregir -->
    <button v-else-if="revision.puede_reenviar && revision.estado === 'observada'" type="button" @click="reenviar"
            class="mt-3 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E75B6]">
        Reenviar a revisión
    </button>
</div>
```

- [ ] **Step 5: Verificar en el navegador**

Arrancar el servidor de desarrollo y Vite. Crear `.claude/launch.json` si no existe con una configuración que ejecute `php artisan serve` (puerto 8000) y, por separado, `npm run dev` (Vite). Luego con las herramientas del panel Browser:
1. `preview_start` con el name del server.
2. Login como `ejecutivo.demo@usil.edu.pe` (Demo#1234) → abrir un postulante → ver el panel con botones Aprobar/Observar. Observar con texto → recargar → estado "Observada" y observación visible.
3. Login como `asesor.demo@usil.edu.pe` → abrir ese postulante → ver la observación (solo lectura) y el botón "Reenviar a revisión"; pulsarlo → vuelve a "Pendiente".
4. `read_console_messages` y `preview_logs` sin errores.
5. `computer {action:"screenshot"}` como evidencia.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PostulanteController.php resources/js/Pages/Postulantes/Index.vue resources/js/Pages/Postulantes/Form.vue
git commit -m "feat(ui): badge de revisión en listado y panel aprobar/observar/reenviar en expediente"
```

---

### Task 8: UI — observación en el portal del postulante

**Files:**
- Modify: `app/Http/Controllers/Portal/SeguimientoController.php`
- Modify: `resources/js/Pages/Portal/Seguimiento.vue`

**Interfaces:**
- Consumes: `Postulante->revision_estado`, `revision_observaciones` (Task 2).
- Produces: el portal muestra un aviso "Documentación observada: …" cuando el expediente está `observada`.

- [ ] **Step 1: Enviar la revisión al portal**

En `SeguimientoController@index`, dentro del arreglo `'postulante' => [ ... ]` (líneas 29–39), añadir:

```php
                'revision_estado'        => $p->revision_estado,
                'revision_observaciones' => $p->revision_observaciones,
```

- [ ] **Step 2: Mostrar el aviso en `Seguimiento.vue`**

En el `<template>`, tras el encabezado principal de la página, añadir un aviso condicional (leyendo `postulante.revision_estado` de las props):

```html
<div v-if="postulante.revision_estado === 'observada'"
     class="mb-6 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
    <p class="font-medium">Documentación observada</p>
    <p class="mt-1">{{ postulante.revision_observaciones || 'Comunícate con tu asesor de admisión para más detalle.' }}</p>
</div>
```

(Si el componente no desestructura `postulante` de props, usar la referencia que ya emplee la página; el objeto `postulante` ya llega en las props de la vista.)

- [ ] **Step 3: Verificar en el navegador**

Con un postulante en estado `observada` (dejado por la Task 7) que tenga acceso al portal:
1. `navigate` al portal (`/portal/login`), iniciar sesión como ese postulante.
2. Confirmar que se ve el recuadro "Documentación observada" con el texto.
3. `read_console_messages` sin errores; `computer {action:"screenshot"}` como evidencia.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Portal/SeguimientoController.php resources/js/Pages/Portal/Seguimiento.vue
git commit -m "feat(portal): muestra la observación de admisión al postulante"
```

---

### Task 9: Verificación integral

**Files:** ninguno (solo verificación).

- [ ] **Step 1: Correr toda la suite**

Run: `php artisan test`
Expected: PASS (incluye `RbacTest`, `PostulanteRevisionTest`, `RevisionFlujoTest`, `GateSimulacionTest`, `DashboardAdmisionTest`, `DemoUsersSeederTest` y los tests previos del proyecto).

- [ ] **Step 2: Build de front**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Smoke manual end-to-end (navegador)**

Recorrido completo con las cuentas demo:
1. Asesor registra un postulante (queda `pendiente`) → no aparece en Simulaciones del coordinador.
2. Ejecutivo observa → el asesor ve la observación y el postulante también en el portal → asesor reenvía.
3. Ejecutivo aprueba → el postulante ahora sí aparece en Simulaciones del coordinador y la simulación procede.

- [ ] **Step 4: Commit final (si hubo ajustes)**

```bash
git add -A && git commit -m "chore: verificación integral del flujo de admisión en dos roles"
```

---

## Notas de auto-revisión (cobertura del spec)

- Roles y permisos → Task 1. Migración/modelo → Task 2. Revisar/observar/reenviar + alcance del asesor → Task 3. Gate al coordinador (ocultar + 403) → Task 4. Dashboards → Task 5. Demo/login → Task 6. UI expediente + listado → Task 7. Portal → Task 8. Verificación → Task 9.
- Simplificación deliberada marcada en Task 7: las acciones de revisión viven en el expediente, no en el listado (el spec las mencionaba en el listado). Confirmar en review si se quiere también en el listado.
- Fuera de alcance (sin tarea, por diseño): permiso `solicitudes.asignar` y desacople `estado`↔`estado_equivalencias`.
