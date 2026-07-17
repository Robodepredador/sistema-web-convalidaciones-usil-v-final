# Acceso del postulante al portal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que al registrar (o resetear) a un postulante con correo, el sistema le envíe por email su usuario y contraseña temporal, lo obligue a cambiarla en el primer acceso, y que pueda seguir el estado de su trámite en todas sus fases.

**Architecture:** Reutiliza el portal del postulante (guard `postulante`) y el patrón de correo existente (`RecuperarPasswordMail`). Se agrega un Mailable de acceso, un flag `debe_cambiar_password`, un middleware que fuerza el cambio, y se corrige la derivación de la última fase del timeline. Verificación por pruebas (unit + feature) y por navegador.

**Tech Stack:** Laravel 12, Inertia + Vue 3, Tailwind, MySQL 8, PHPUnit. Mailer por defecto `log`.

## Global Constraints

- Los tests corren contra **MySQL real** (`convalidaciones_test`), no SQLite (las migraciones usan sintaxis MySQL). Usar `RefreshDatabase`.
- Hay ~7 tests preexistentes rotos por `malla_externa_id`, ajenos a este trabajo. No intentar arreglarlos aquí; al correr la suite, ignorar esos.
- El enum `accion` de `auditoria_log` es fijo (`crear|editar|eliminar|login|...`); los matices van en el payload.
- Correo: nunca romper el flujo de registro si falla el envío (envolver en `try/catch` + `Log::warning`), igual que `PasswordController@enviarEnlace`.
- Colores UI del portal: `#1F3864` / `#2E75B6` (consistente con `Portal/Login.vue`).

---

### Task 1: Flag `debe_cambiar_password` en postulantes

**Files:**
- Create: `database/migrations/2026_07_17_000001_add_debe_cambiar_password_to_postulantes.php`
- Modify: `app/Models/Postulante.php` (`$fillable`, `$casts`)
- Test: `tests/Feature/PostulanteAccesoTest.php`

**Interfaces:**
- Produces: columna booleana `postulantes.debe_cambiar_password` (default `false`), casteada a `bool` en el modelo.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Postulante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostulanteAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_cambio_password_por_defecto_false(): void
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-90001', 'tipo_documento' => 'DNI', 'numero_documento' => '90000001',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana.acceso@example.com',
        ]);

        $this->assertFalse($p->fresh()->debe_cambiar_password);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_flag_cambio_password_por_defecto_false`
Expected: FAIL — columna `debe_cambiar_password` no existe / propiedad indefinida.

- [ ] **Step 3: Create the migration**

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
            $table->boolean('debe_cambiar_password')->default(false)->after('acceso_habilitado');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn('debe_cambiar_password');
        });
    }
};
```

- [ ] **Step 4: Add the column to the model**

En `app/Models/Postulante.php`, agregar `'debe_cambiar_password'` al array `$fillable` (junto a `'acceso_habilitado'`) y en `$casts`:

```php
'debe_cambiar_password' => 'boolean',
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_flag_cambio_password_por_defecto_false`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_17_000001_add_debe_cambiar_password_to_postulantes.php app/Models/Postulante.php tests/Feature/PostulanteAccesoTest.php
git commit -m "feat(portal): flag debe_cambiar_password en postulantes"
```

---

### Task 2: Mailable de acceso al portal

**Files:**
- Create: `app/Mail/AccesoPortalMail.php`
- Create: `resources/views/emails/acceso-portal.blade.php`
- Test: `tests/Unit/AccesoPortalMailTest.php`

**Interfaces:**
- Produces: `new AccesoPortalMail(Postulante $postulante, string $url, string $password)` — Mailable con subject `Acceso a tu portal — USIL Convalidaciones` que renderiza usuario (email) y contraseña.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Mail\AccesoPortalMail;
use App\Models\Postulante;
use Tests\TestCase;

class AccesoPortalMailTest extends TestCase
{
    public function test_correo_muestra_usuario_y_password(): void
    {
        $p = new Postulante(['nombres' => 'Ana', 'email' => 'ana@example.com']);
        $mail = new AccesoPortalMail($p, 'http://localhost/portal/login', 'Temp#1234');

        $mail->assertHasSubject('Acceso a tu portal — USIL Convalidaciones');
        $mail->assertSeeInHtml('ana@example.com');
        $mail->assertSeeInHtml('Temp#1234');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_correo_muestra_usuario_y_password`
Expected: FAIL — clase `AccesoPortalMail` no existe.

- [ ] **Step 3: Create the Mailable**

```php
<?php

namespace App\Mail;

use App\Models\Postulante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccesoPortalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Postulante $postulante,
        public string $url,
        public string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Acceso a tu portal — USIL Convalidaciones',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.acceso-portal',
        );
    }
}
```

- [ ] **Step 4: Create the mail view**

`resources/views/emails/acceso-portal.blade.php`:

```blade
<x-mail::message>
# Acceso a tu portal de seguimiento

Hola **{{ $postulante->nombres }}**, registramos tu solicitud de convalidación en **USIL Convalidaciones**.

Con estas credenciales puedes ingresar al portal y seguir el estado de tu trámite:

- **Usuario:** {{ $postulante->email }}
- **Contraseña temporal:** {{ $password }}

<x-mail::button :url="$url" color="primary">
Ingresar al portal
</x-mail::button>

Por tu seguridad, deberás **cambiar la contraseña** la primera vez que ingreses.

Gracias,<br>
**USIL Convalidaciones**
</x-mail::message>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_correo_muestra_usuario_y_password`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Mail/AccesoPortalMail.php resources/views/emails/acceso-portal.blade.php tests/Unit/AccesoPortalMailTest.php
git commit -m "feat(portal): correo de acceso con usuario y contrasena temporal"
```

---

### Task 3: Enviar credenciales al registrar y al resetear acceso

**Files:**
- Modify: `app/Http/Controllers/PostulanteController.php` (imports, `store`, `resetAcceso`, nuevo helper privado `enviarAcceso`)
- Test: `tests/Feature/PostulanteAccesoTest.php` (agregar métodos)

**Interfaces:**
- Consumes: `AccesoPortalMail` (Task 2), `postulantes.debe_cambiar_password` (Task 1), ruta `portal.login`.
- Produces: al crear/resetear acceso con correo, se envía `AccesoPortalMail` y se marca `debe_cambiar_password = true`.

- [ ] **Step 1: Write the failing tests**

Agregar a `tests/Feature/PostulanteAccesoTest.php` (añadir imports arriba: `use App\Mail\AccesoPortalMail; use App\Models\Role; use App\Models\User; use Illuminate\Support\Facades\Hash; use Illuminate\Support\Facades\Mail;`):

```php
    private function asesor(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $rol = Role::where('nombre', Role::ASESOR)->firstOrFail();

        return User::create([
            'nombre' => 'Asesor', 'email' => uniqid() . '@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    public function test_reset_acceso_envia_correo_y_marca_cambio(): void
    {
        Mail::fake();
        $asesor = $this->asesor();
        $p = Postulante::create([
            'codigo' => 'POST-2026-90002', 'tipo_documento' => 'DNI', 'numero_documento' => '90000002',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana.reset@example.com',
            'usuario_id' => $asesor->id,
        ]);

        $this->actingAs($asesor)->patch("/postulantes/{$p->id}/reset-acceso")->assertRedirect();

        $this->assertTrue($p->fresh()->debe_cambiar_password);
        $this->assertTrue($p->fresh()->acceso_habilitado);
        Mail::assertSent(AccesoPortalMail::class, fn ($m) => $m->hasTo('ana.reset@example.com'));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=test_reset_acceso_envia_correo_y_marca_cambio`
Expected: FAIL — no se envía correo / `debe_cambiar_password` sigue en false.

- [ ] **Step 3: Add imports and helper to the controller**

En `app/Http/Controllers/PostulanteController.php`, agregar imports:

```php
use App\Mail\AccesoPortalMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
```

Agregar el helper privado (junto a los demás privados):

```php
    /** Envía al postulante sus credenciales del portal; no rompe el registro si falla el correo. */
    private function enviarAcceso(Postulante $postulante, string $temporal): void
    {
        try {
            Mail::to($postulante->email)->send(
                new AccesoPortalMail($postulante, route('portal.login'), $temporal)
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el correo de acceso al portal: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 4: Set the flag + send mail in `store()`**

En `store()`, reemplazar el bloque de acceso al portal:

```php
        // Acceso al portal solo si hay correo.
        $temporal = null;
        if (! empty($datos['email'])) {
            $temporal = Str::password(10);
            $datos['password_hash']         = Hash::make($temporal);
            $datos['acceso_habilitado']     = true;
            $datos['debe_cambiar_password'] = true;
        }
```

Después de `$this->syncDestinos($postulante, $destinoIds);` y antes de la auditoría, enviar el correo:

```php
        if ($temporal) {
            $this->enviarAcceso($postulante, $temporal);
        }
```

Y en el bloque del mensaje flash, reemplazar la línea del acceso para reflejar el envío:

```php
        if ($temporal) {
            $url = route('portal.login');
            $msg .= " Se enviaron las credenciales al correo del postulante. Acceso ({$url}) → usuario: {$postulante->email} · contraseña temporal: {$temporal}";
        }
```

- [ ] **Step 5: Send mail + set flag in `resetAcceso()`**

Reemplazar el cuerpo de `resetAcceso()`:

```php
    public function resetAcceso(Postulante $postulante): RedirectResponse
    {
        abort_if(empty($postulante->email), 422, 'El postulante no tiene correo para habilitar acceso.');

        $temporal = Str::password(10);
        $postulante->update([
            'password_hash'         => Hash::make($temporal),
            'acceso_habilitado'     => true,
            'debe_cambiar_password' => true,
        ]);
        $this->enviarAcceso($postulante, $temporal);
        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, null, ['reset_acceso' => true]);

        return back()->with('status', "Acceso restablecido para {$postulante->email}. Se envió la contraseña temporal por correo. (Temporal: {$temporal})");
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=PostulanteAccesoTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/PostulanteController.php tests/Feature/PostulanteAccesoTest.php
git commit -m "feat(portal): envia credenciales por correo al registrar y resetear acceso"
```

---

### Task 4: Forzar cambio de contraseña en el primer acceso al portal

**Files:**
- Create: `app/Http/Controllers/Portal/PasswordController.php`
- Create: `app/Http/Middleware/PostulanteDebeCambiarPassword.php`
- Create: `resources/js/Pages/Portal/CambiarPassword.vue`
- Modify: `bootstrap/app.php` (alias de middleware)
- Modify: `routes/web.php` (import + rutas del portal)
- Test: `tests/Feature/PortalSeguimientoTest.php`

**Interfaces:**
- Consumes: `postulantes.debe_cambiar_password` (Task 1), guard `postulante`, ruta `portal.seguimiento`.
- Produces: rutas `portal.password.cambiar.form` (GET) y `portal.password.cambiar` (POST); alias middleware `postulante.cambiar`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Postulante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalSeguimientoTest extends TestCase
{
    use RefreshDatabase;

    private function postulanteConAcceso(bool $debeCambiar): Postulante
    {
        return Postulante::create([
            'codigo' => 'POST-2026-' . random_int(90100, 90999),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(90000100, 90000999),
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => uniqid() . '@example.com',
            'estado' => 'en_evaluacion',
            'password_hash' => Hash::make('Temp#1234'),
            'acceso_habilitado' => true,
            'debe_cambiar_password' => $debeCambiar,
        ]);
    }

    public function test_primer_acceso_obliga_cambio_de_password(): void
    {
        $p = $this->postulanteConAcceso(true);

        // Con el flag activo, el seguimiento redirige al cambio de contraseña.
        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertRedirect(route('portal.password.cambiar.form'));

        // Cambiar la contraseña baja el flag y redirige al seguimiento.
        $this->actingAs($p, 'postulante')->post('/portal/password/cambiar', [
            'password' => 'NuevaClave#2026', 'password_confirmation' => 'NuevaClave#2026',
        ])->assertRedirect(route('portal.seguimiento'));

        $this->assertFalse($p->fresh()->debe_cambiar_password);

        // Ya con el flag en false, el seguimiento carga.
        $this->actingAs($p->fresh(), 'postulante')->get('/portal/')->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_primer_acceso_obliga_cambio_de_password`
Expected: FAIL — ruta `portal.password.cambiar.form` no existe.

- [ ] **Step 3: Create the portal password controller**

`app/Http/Controllers/Portal/PasswordController.php`:

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Cambio de contraseña del postulante en el portal (primer acceso). */
class PasswordController extends Controller
{
    public function mostrar()
    {
        return inertia('Portal/CambiarPassword');
    }

    public function actualizar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'password' => ['required', 'confirmed',
                Password::min(8)->letters()->numbers()->mixedCase()->symbols()],
        ]);

        $postulante = Auth::guard('postulante')->user();
        $postulante->forceFill([
            'password_hash'         => Hash::make($datos['password']),
            'debe_cambiar_password' => false,
        ])->save();

        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, null, ['cambio_password' => true]);

        return redirect()->route('portal.seguimiento')->with('status', 'Contraseña actualizada.');
    }
}
```

- [ ] **Step 4: Create the middleware**

`app/Http/Middleware/PostulanteDebeCambiarPassword.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Fuerza el cambio de contraseña del postulante antes de acceder al portal. */
class PostulanteDebeCambiarPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $postulante = Auth::guard('postulante')->user();

        if ($postulante && $postulante->debe_cambiar_password) {
            return redirect()->route('portal.password.cambiar.form');
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: Register the middleware alias**

En `bootstrap/app.php`, dentro de `$middleware->alias([...])`, agregar:

```php
            'postulante.cambiar' => \App\Http\Middleware\PostulanteDebeCambiarPassword::class,
```

- [ ] **Step 6: Wire the portal routes**

En `routes/web.php`, agregar el import junto a los demás controladores del portal:

```php
use App\Http\Controllers\Portal\PasswordController as PortalPasswordController;
```

Reemplazar el grupo `auth:postulante` por:

```php
    Route::middleware('auth:postulante')->group(function () {
        // Cambio de contraseña (sin el gate 'postulante.cambiar' para evitar bucle).
        Route::get('/password/cambiar', [PortalPasswordController::class, 'mostrar'])->name('portal.password.cambiar.form');
        Route::post('/password/cambiar', [PortalPasswordController::class, 'actualizar'])->name('portal.password.cambiar');

        // El seguimiento exige tener la contraseña ya cambiada.
        Route::middleware('postulante.cambiar')->group(function () {
            Route::get('/', [PortalSeguimientoController::class, 'index'])->name('portal.seguimiento');
        });

        Route::post('/logout', [PortalAccesoController::class, 'logout'])->name('portal.logout');
    });
```

- [ ] **Step 7: Create the Vue view**

`resources/js/Pages/Portal/CambiarPassword.vue`:

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({ password: '', password_confirmation: '' });
const enviar = () => form.post('/portal/password/cambiar');
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-[#1F3864] to-[#2E75B6] px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
            <h1 class="text-lg font-semibold text-[#1F3864]">Cambia tu contraseña</h1>
            <p class="mb-6 mt-1 text-sm text-slate-500">Por seguridad, define una nueva contraseña antes de ver el estado de tu trámite.</p>

            <form @submit.prevent="enviar" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nueva contraseña</label>
                    <input v-model="form.password" type="password" autocomplete="new-password"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Confirmar contraseña</label>
                    <input v-model="form.password_confirmation" type="password" autocomplete="new-password"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>
                <p class="text-xs text-slate-500">Mínimo 8 caracteres, con mayúsculas, minúsculas, números y símbolos.</p>
                <button type="submit" :disabled="form.processing"
                        class="w-full rounded-lg bg-[#1F3864] py-2.5 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    Guardar contraseña
                </button>
            </form>
        </div>
    </div>
</template>
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=test_primer_acceso_obliga_cambio_de_password`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Portal/PasswordController.php app/Http/Middleware/PostulanteDebeCambiarPassword.php resources/js/Pages/Portal/CambiarPassword.vue bootstrap/app.php routes/web.php tests/Feature/PortalSeguimientoTest.php
git commit -m "feat(portal): fuerza cambio de contrasena del postulante en el primer acceso"
```

---

### Task 5: Corregir la fase "Convalidación confirmada" y probar el timeline en todas sus fases

**Contexto del bug:** `ConvalidacionController@confirmar` deja la simulación en `estado = 'aceptada'` y crea la fila en `convalidaciones`. La simulación **nunca** queda en `'confirmada'`, pero `SeguimientoController@index` calcula `$confirmada` con `$s->estado === 'confirmada'`, así que la última fase del timeline del postulante no se completa jamás. La señal correcta es la relación `convalidacion` (como ya hacen `PostulanteController@edit` e `@index`).

**Files:**
- Create: `app/Support/SeguimientoTimeline.php`
- Modify: `app/Http/Controllers/Portal/SeguimientoController.php` (usar el helper, corregir `$confirmada`, eliminar el método privado `timeline`)
- Test: `tests/Unit/SeguimientoTimelineTest.php`

**Interfaces:**
- Produces: `SeguimientoTimeline::construir(string $estado, ?string $registradaEl, int $docsCount, bool $docsCompletos, bool $todasAprob, bool $enRevision, bool $tieneSim, bool $confirmada): array` — devuelve etapas `[label, detalle, estado]` con `estado ∈ {completado, actual, pendiente, rechazado}`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/SeguimientoTimelineTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\SeguimientoTimeline;
use PHPUnit\Framework\TestCase;

class SeguimientoTimelineTest extends TestCase
{
    /** @param array<int, array> $t */
    private function estados(array $t): array
    {
        return array_map(fn ($e) => $e['estado'], $t);
    }

    public function test_fase_1_solo_registro(): void
    {
        $t = SeguimientoTimeline::construir('nuevo', '01/01/2026', 0, false, false, false, false, false);
        $this->assertSame(['completado', 'actual', 'pendiente', 'pendiente', 'pendiente'], $this->estados($t));
    }

    public function test_fase_2_documentos_completos(): void
    {
        $t = SeguimientoTimeline::construir('nuevo', '01/01/2026', 3, true, false, false, false, false);
        $this->assertSame(['completado', 'completado', 'actual', 'pendiente', 'pendiente'], $this->estados($t));
    }

    public function test_fase_3_equivalencias_aprobadas(): void
    {
        $t = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, true, true, true, false, false);
        $this->assertSame(['completado', 'completado', 'completado', 'actual', 'pendiente'], $this->estados($t));
    }

    public function test_fase_4_simulacion(): void
    {
        $t = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, true, true, true, true, false);
        $this->assertSame(['completado', 'completado', 'completado', 'completado', 'actual'], $this->estados($t));
    }

    public function test_fase_5_convalidacion_confirmada(): void
    {
        $t = SeguimientoTimeline::construir('admitido', '01/01/2026', 3, true, true, true, true, true);
        $this->assertSame(['completado', 'completado', 'completado', 'completado', 'completado'], $this->estados($t));
    }

    public function test_equivalencias_en_revision_muestra_detalle(): void
    {
        $t = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, true, false, true, false, false);
        $this->assertSame('En revisión por la coordinación', $t[2]['detalle']);
    }

    public function test_rechazado_devuelve_una_sola_etapa(): void
    {
        $t = SeguimientoTimeline::construir('rechazado', '01/01/2026', 0, false, false, false, false, false);
        $this->assertCount(1, $t);
        $this->assertSame('rechazado', $t[0]['estado']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SeguimientoTimelineTest`
Expected: FAIL — clase `SeguimientoTimeline` no existe.

- [ ] **Step 3: Create the timeline helper**

`app/Support/SeguimientoTimeline.php`:

```php
<?php

namespace App\Support;

/** Construye la línea de tiempo del proceso de convalidación del postulante. */
class SeguimientoTimeline
{
    /**
     * Cada etapa: completado | actual | pendiente. La primera no completada se marca "actual".
     *
     * @return array<int, array{label:string, detalle:string, estado:string}>
     */
    public static function construir(
        string $estado,
        ?string $registradaEl,
        int $docsCount,
        bool $docsCompletos,
        bool $todasAprob,
        bool $enRevision,
        bool $tieneSim,
        bool $confirmada,
    ): array {
        if ($estado === 'rechazado') {
            return [[
                'label' => 'Solicitud rechazada', 'estado' => 'rechazado',
                'detalle' => 'Comunícate con la Coordinación Académica para más información.',
            ]];
        }

        $etapas = [
            ['label' => 'Solicitud registrada', 'done' => true,
                'detalle' => 'Recibida el ' . ($registradaEl ?? '—')],
            ['label' => 'Documentos recibidos', 'done' => $docsCompletos,
                'detalle' => $docsCompletos ? 'Expediente completo' : "{$docsCount} de 3 documentos entregados"],
            ['label' => 'Revisión de equivalencias', 'done' => $todasAprob,
                'detalle' => $todasAprob ? 'Equivalencias aprobadas' : ($enRevision ? 'En revisión por la coordinación' : 'En espera de revisión')],
            ['label' => 'Simulación de convalidación', 'done' => $tieneSim,
                'detalle' => $tieneSim ? 'Simulación generada' : 'Aún no generada'],
            ['label' => 'Convalidación confirmada', 'done' => $confirmada,
                'detalle' => $confirmada ? 'Convalidación oficial confirmada' : 'Pendiente de confirmación'],
        ];

        $hayActual = false;

        return array_map(function ($e) use (&$hayActual) {
            if ($e['done']) {
                $estado = 'completado';
            } elseif (! $hayActual) {
                $estado = 'actual';
                $hayActual = true;
            } else {
                $estado = 'pendiente';
            }

            return ['label' => $e['label'], 'detalle' => $e['detalle'], 'estado' => $estado];
        }, $etapas);
    }
}
```

- [ ] **Step 4: Use the helper and fix `$confirmada` in the controller**

En `app/Http/Controllers/Portal/SeguimientoController.php`:

1. Agregar import: `use App\Support\SeguimientoTimeline;`
2. En `index()`, cargar la convalidación: cambiar el `load` para incluir `'simulaciones.convalidacion'`:

```php
        $p->load(['carreraDestino', 'institucionOrigen', 'carreraExterna', 'destinos.carrera', 'simulaciones.detalles', 'simulaciones.convalidacion']);
```

3. Corregir la señal de confirmación (la simulación queda 'aceptada'; la señal real es la relación `convalidacion`):

```php
        $confirmada    = $p->simulaciones->contains(fn (Simulacion $s) => (bool) $s->convalidacion);
```

4. Reemplazar la construcción del timeline:

```php
            'timeline' => SeguimientoTimeline::construir(
                $p->estado,
                $p->created_at?->format('d/m/Y'),
                $docsCount, $docsCompletos, $todasAprob, $enRevision, $tieneSim, $confirmada
            ),
```

5. Eliminar el método privado `timeline(...)` completo (ya vive en el helper).

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=SeguimientoTimelineTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Support/SeguimientoTimeline.php app/Http/Controllers/Portal/SeguimientoController.php tests/Unit/SeguimientoTimelineTest.php
git commit -m "fix(portal): la fase de convalidacion confirmada usa la relacion convalidacion"
```

---

### Task 6: Test de integración del seguimiento (fases por documentos vía DB real)

**Files:**
- Modify: `tests/Feature/PortalSeguimientoTest.php` (agregar método)

**Interfaces:**
- Consumes: guard `postulante`, ruta `portal.seguimiento`, prop Inertia `timeline` (Task 5).

- [ ] **Step 1: Write the failing test**

Agregar a `tests/Feature/PortalSeguimientoTest.php`:

```php
    public function test_seguimiento_avanza_con_documentos(): void
    {
        $p = $this->postulanteConAcceso(false); // sin forzar cambio

        // Fase 1 (registro) completada, fase 2 (documentos) actual.
        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertInertia(fn ($page) => $page
                ->where('timeline.0.estado', 'completado')
                ->where('timeline.1.estado', 'actual')
                ->where('postulante.estado', 'en_evaluacion'));

        // Cargar los 3 documentos del expediente.
        foreach (['certificado', 'silabos', 'constancia'] as $tipo) {
            $p->documentos()->create([
                'tipo' => $tipo, 'nombre_original' => "{$tipo}.pdf",
                'ruta' => "postulantes/{$p->id}/{$tipo}.pdf", 'tamano' => 1000,
            ]);
        }

        // Fase 2 completada, fase 3 (equivalencias) actual.
        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertInertia(fn ($page) => $page
                ->where('timeline.1.estado', 'completado')
                ->where('timeline.2.estado', 'actual'));
    }
```

- [ ] **Step 2: Run test to verify it fails or passes**

Run: `php artisan test --filter=test_seguimiento_avanza_con_documentos`
Expected: PASS (la lógica ya existe tras Task 5). Si falla, revisar que `postulanteConAcceso` fije `estado = 'en_evaluacion'`.

- [ ] **Step 3: Run the full new suite**

Run: `php artisan test --filter="PortalSeguimientoTest|PostulanteAccesoTest|AccesoPortalMailTest|SeguimientoTimelineTest"`
Expected: PASS (todos los nuevos).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/PortalSeguimientoTest.php
git commit -m "test(portal): el seguimiento avanza al completar documentos"
```

---

### Task 7: Verificación en navegador, corrección de incoherencias y config de correo

**Files:**
- Modify: `.env.example` (variables `MAIL_*`)
- Modify: cualquier archivo donde aparezca una incoherencia de UX detectada al recorrer el flujo.

- [ ] **Step 1: Prepare the app**

```bash
php artisan migrate
php artisan db:seed
npm run build   # o dejar `npm run dev` corriendo para HMR
```
Asegurar MySQL arriba y `MAIL_MAILER=log` (el correo se escribe en `storage/logs/laravel.log`). Levantar el servidor con `php artisan serve` (o el preview del navegador).

- [ ] **Step 2: Drive the Asesor registration flow (browser)**

1. Iniciar sesión con la cuenta demo del **Asesor** (ver `Database\Seeders\DemoUsersSeeder`).
2. Ir a Postulantes → Crear, registrar un postulante **con correo**.
3. Capturar el flash de confirmación (debe mencionar que se envió la contraseña por correo y mostrar el temporal de respaldo).
4. Verificar en `storage/logs/laravel.log` que salió el `AccesoPortalMail` con usuario y contraseña.
5. Screenshot del flash.

- [ ] **Step 3: Drive the Postulante portal flow (browser)**

1. Ir a `/portal/login`, iniciar sesión con el correo y la contraseña temporal.
2. Verificar la **redirección forzada** a `/portal/password/cambiar`.
3. Cambiar la contraseña; verificar que llega al **seguimiento** y que se muestran timeline, estado y (si aplica) la observación de admisión.
4. Screenshots de: pantalla de cambio de contraseña y seguimiento con timeline.

- [ ] **Step 4: Fix incoherencies**

Anotar y corregir cualquier incoherencia detectada (textos, enlaces rotos, estados que no cuadran, redirecciones, mensajes duplicados o confusos). Cada corrección se commitea aparte con un mensaje descriptivo. Volver a verificar en el navegador tras cada fix.

- [ ] **Step 5: Document mail env vars**

Agregar a `.env.example` (después del bloque de Seguridad):

```
# Correo (RF-39 / acceso del postulante). 'log' escribe el correo en storage/logs (sin SMTP).
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="no-responder@usil.edu.pe"
MAIL_FROM_NAME="${APP_NAME}"
```

- [ ] **Step 6: Commit**

```bash
git add .env.example
git commit -m "chore: documenta variables MAIL_* para el acceso del postulante"
```

---

## Self-Review

**Spec coverage:**
- Enviar credenciales por correo → Tasks 2, 3. ✔
- Forzar cambio en primer acceso (híbrido) → Tasks 1, 4. ✔
- Corroboración del seguimiento en todas las fases → Task 5 (unit, exhaustivo) + Task 6 (integración) + Task 7 (navegador). ✔
- Verificación en navegador con capturas y corrección de incoherencias → Task 7. ✔
- Config `MAIL_*` en `.env.example` → Task 7. ✔
- No romper el registro sin SMTP → Task 3 (`try/catch` + `Log::warning`). ✔

**Bug encontrado y corregido:** fase "Convalidación confirmada" nunca se completaba (`$s->estado === 'confirmada'` imposible) → Task 5.

**Type consistency:** `SeguimientoTimeline::construir(...)` con la misma firma en Task 5 (definición) y su uso en el controlador. `AccesoPortalMail(Postulante, string, string)` consistente entre Tasks 2 y 3. `debe_cambiar_password` (bool) consistente en Tasks 1, 3, 4.

**Placeholders:** ninguno; todo el código está completo.
