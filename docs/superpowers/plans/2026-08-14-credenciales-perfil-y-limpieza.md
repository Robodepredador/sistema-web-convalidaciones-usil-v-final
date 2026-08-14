# Credenciales sin correo, perfil personal y limpieza — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el sistema no dependa de ningún servicio externo —ni servidor de correo ni proveedor de IA— y que cada usuario administre su propia cuenta.

**Architecture:** Cinco tareas en tres bloques. **E vacía** dos módulos que dejaron de tener función. **F** cambia el canal de entrega de credenciales: de correo a pantalla. **G** da a cada usuario su perfil. E y F1 son independientes entre sí; F2 depende de F1 y G1 comparte archivos con F2.

**Tech Stack:** Laravel 11 · PHP 8.2 · MySQL 8.0 · Inertia + Vue 3 · PHPUnit contra MySQL real.

---

## Lo que encontré antes de escribir esto

Cuatro hechos que cambian el tamaño del trabajo. Conviene leerlos antes de empezar.

**1. Mallas Externas ya está vacío por dentro.** `mallas_externas` tiene **0 filas**, y `cursos_externos.malla_externa_id` existe pero **no tiene ni un solo valor no nulo**: la Task B1 ya trasladó la pertenencia a `carrera_externa_id`. `simulaciones` ni siquiera tiene esa columna. Borrar el módulo no arrastra datos de nadie — es retirar un andamio que ya no sostiene nada.

**2. La contraseña temporal y el cambio forzado YA FUNCIONAN.** `UsuarioController:73` genera `Str::password(12)` y marca `primer_acceso = true`; `LoginController:85` intercepta el primer acceso y `PasswordController` limpia la marca al cambiarla. **No hay que construir ese flujo, solo cambiar por dónde sale la contraseña.**

**3. Hoy el correo no entrega nada, y el código lo sabe.** `.env.example` trae `MAIL_MAILER=log`, y `app/Support/EntregaCredenciales.php` está escrito íntegramente alrededor de ese problema: su docblock dice que el correo *«es el ÚNICO canal por el que salen las contraseñas, así que un envío fallido deja al usuario sin poder entrar»*, y devuelve un aviso pidiendo entregarla por otro medio. **Este cambio no degrada nada: formaliza lo que ya pasa en la práctica.**

**4. La pantalla de Configuración es solo IA.** `Configuracion/Index.vue` declara una sola pestaña (`{ id: 'ia', label: 'Motor de IA' }`) y `tab = ref('ia')`. La tabla `configuraciones` está **vacía**. Al quitar la IA, la pantalla queda sin contenido — lo que la convierte en el sitio natural del perfil personal.

---

## Decisiones de diseño, con su porqué

### D1 · La pantalla es el canal, y la contraseña se muestra una sola vez

El administrador crea el usuario, la pantalla le muestra correo y contraseña temporal con un botón de copiar, y él la hace llegar por donde quiera. El sistema **no guarda la contraseña en claro en ningún sitio**: ni en la sesión, ni en el registro de auditoría, ni en el log.

Eso implica que **si el administrador cierra la pantalla sin copiarla, la contraseña se pierde**. No es un descuido del diseño: es la consecuencia de no guardarla. La salida es regenerarla, que ya existe (`UsuarioController:143`). La pantalla debe decirlo con todas sus letras antes de que el administrador navegue a otro sitio.

### D2 · Se retira la recuperación de contraseña por correo

Sin servidor de correo no hay forma de verificar que quien pide el reseteo es el dueño de la cuenta. El enlace «¿Olvidaste tu contraseña?» sale del login, y el camino pasa a ser: **el usuario le pide al administrador que la regenere**, y el administrador le entrega la nueva por el mismo canal que la primera.

Es menos cómodo y es honesto. Un formulario de «olvidé mi contraseña» que no envía nada es peor que no tenerlo.

### D3 · Configuración deja de ser del sistema y pasa a ser del usuario

La pantalla `/configuracion` hoy solo configura la IA. Al retirarla, en vez de dejar una pantalla vacía o borrar la entrada del menú, **se reutiliza como perfil personal**: cualquier usuario autenticado entra a lo suyo, sin permiso especial. El permiso `configuracion.gestionar` deja de gatear esa pantalla.

---

## Global Constraints

- **Base de pruebas:** `convalidaciones_test`, fijada en `phpunit.xml`. Las pruebas corren contra **MySQL real**.
- Identificadores, docblocks y mensajes **en español**. Cada migración lleva docblock que explica **por qué**, no qué.
- Clase anónima `return new class extends Migration`; **`down()` funcional** y guardado para ser reintentable (`Schema::hasTable`, `Schema::hasColumn`, `information_schema.STATISTICS`).
- **Comprobación previa** en toda migración que restrinja datos existentes: abortar con `\RuntimeException` nombrando las filas antes de tocar el esquema. Modelo: `database/migrations/2026_08_13_000005_unicidad_en_catalogos.php`.
- Sin factories salvo `UserFactory`: `Model::create([...])`, roles con `$this->seed(RoleSeeder::class)`.
- Pruebas que esperan rechazo de la base: `try { …; $this->fail('…'); } catch (QueryException $e) { assertStringContainsString('<indice>', …); }`. Nunca `expectException` a secas.
- **`./vendor/bin/pint` sobre los archivos tocados antes de cada commit**, pasándolos como argumentos. CI gatea con `pint --test` sin `continue-on-error`.
- **NUNCA `git add -A` ni `git add .`**: hay scripts de desarrollo sin versionar en la raíz.
- Commits en español, sin tildes en el asunto, con `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.
- `migrate` y `migrate:rollback` sin argumentos actúan sobre la base de **DESARROLLO**. Correr `migrate:status` antes de cada rollback.

---

## Línea base

La rama está **a medio refactor y rota a propósito**: `18 failed, 160 passed`. Los 18 son el trabajo pendiente de la Task C1 del plan anterior (el camino de guardado sigue escribiendo el grano viejo de `simulacion_detalle`).

**Congela el estado antes de empezar** y compara contra él:

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > .superpowers/sdd/estado-antes-de-E1.txt
```

Como las tareas E y F **borran funcionalidad**, el total va a bajar y van a desaparecer archivos de prueba enteros. El criterio no es «los mismos fallos» sino:

- **El total no sube.**
- **Ningún fallo nuevo que no puedas nombrar y explicar** con la funcionalidad que lo causó.

---

# BLOQUE E — Vaciar

### Task E1: Eliminar el módulo de Mallas Externas

**Files:**
- Delete: `app/Http/Controllers/MallaExternaController.php`, `app/Models/MallaExterna.php`, `app/Exports/MallaExternaPlantillaExport.php`
- Delete: `tests/Feature/MallaExternaPlantillaTest.php`
- Create: `database/migrations/2026_08_15_000001_retira_mallas_externas.php`
- Modify: `routes/web.php`, `app/Models/CursoExterno.php`, `app/Models/CarreraExterna.php`, `app/Models/Permiso.php`, `app/Http/Controllers/EquivalenciaController.php`, `app/Http/Controllers/DashboardController.php`, `resources/js/Layouts/AppLayout.vue`, `resources/js/Pages/Equivalencias/Form.vue`
- Modify: `tests/Feature/IntegridadEsquemaTest.php`, `tests/Feature/HistorialEquivalenciasTest.php`, `tests/Feature/RbacTest.php`, `tests/Feature/SimulacionTest.php`

**Interfaces:**
- Produces: desaparecen el permiso `mallas_externas.gestionar`, la tabla `mallas_externas` y la columna `cursos_externos.malla_externa_id`. La entrada «Mallas Externas» sale del menú.

- [ ] **Step 1: Inventario propio antes de tocar**

```bash
grep -rn "MallaExterna\|mallas_externas\|malla_externa_id\|mallas_externas.gestionar" app resources/js routes tests database
```

Trabaja sobre esa lista. **Ojo con dos cosas que NO se van:** `carreras_externas` y `cursos_externos` se quedan —son el catálogo que usa el especialista— y `instituciones_externas` también. Lo que se va es la capa de «malla» que había entre la carrera externa y sus cursos.

- [ ] **Step 2: Confirmar que la columna está realmente huérfana**

```bash
php artisan tinker --execute="echo 'mallas_externas: '.DB::table('mallas_externas')->count().PHP_EOL; echo 'cursos con malla_externa_id: '.DB::table('cursos_externos')->whereNotNull('malla_externa_id')->count().PHP_EOL;"
```

Expected: `0` y `0`. **Si alguno no es cero, para y dilo**: significa que alguien cargó datos después de la medición y la deleción dejaría de ser inocua.

- [ ] **Step 3: Escribir la prueba que falla**

Añadir a `tests/Feature/IntegridadEsquemaTest.php`:

```php
    /** La capa de «malla externa» desapareció: un curso externo pertenece a su
     *  carrera, y la versión de malla de la que salió dejó de importar (D1 del
     *  plan del flujo especialista). */
    public function test_no_queda_rastro_de_mallas_externas(): void
    {
        $this->assertFalse(Schema::hasTable('mallas_externas'));
        $this->assertFalse(Schema::hasColumn('cursos_externos', 'malla_externa_id'));
    }
```

- [ ] **Step 4: Correrla y verificar que falla**

```bash
php artisan test --filter=test_no_queda_rastro_de_mallas_externas
```

Expected: FAIL — la tabla existe.

- [ ] **Step 5: Escribir la migración**

Docblock que explique el porqué: la malla externa era una capa de versión que el flujo del cliente descartó, porque el especialista acumula nombres de curso sin importar de qué versión vengan. `down()` recrea la tabla y la columna nullable, y **dice en el docblock que no recupera los datos**.

Retirar primero la FK `cursos_externos.malla_externa_id`, luego la columna, luego la tabla.

- [ ] **Step 6: Limpiar código y pantallas**

Recorrer la lista del Step 1. En `Permiso::CATALOGO` y `Permiso::POR_ROL` retirar `mallas_externas.gestionar` — el Especialista se queda con `catalogos.gestionar` y `equivalencias.gestionar`. **Añadir la migración que borre esa clave de la tabla `permisos`**, o el permiso sobrevive en bases ya instaladas: es exactamente el defecto que la Task A1 tuvo que arreglar después.

En `AppLayout.vue` sale la entrada «Mallas Externas → /equivalencias».

- [ ] **Step 7: Verificar, formatear y confirmar**

Suite completa contra `estado-antes-de-E1.txt`, `migrate:rollback` + `migrate`, `pint --test`, commit.

---

### Task E2: Eliminar la IA por completo

Subsume la Task A3 del plan anterior, que quedó pendiente. Se va la configuración **y** el motor: sin proveedor configurado, los endpoints de sugerencia no tienen nada que ofrecer, y en el modelo nuevo el administrativo solo escoge dentro del catálogo del especialista — no hay nada que sugerir.

**Files:**
- Delete: `app/Services/IAConvalidacionService.php`, `tests/Feature/SugerenciaIATest.php`
- Modify: `app/Http/Controllers/ConfiguracionController.php`, `app/Http/Controllers/SimulacionController.php`, `app/Services/ConvalidacionEngine.php`, `config/services.php`, `routes/web.php`
- Modify: `resources/js/Pages/Configuracion/Index.vue`, `resources/js/Pages/Simulaciones/Index.vue`, `resources/js/Pages/Simulaciones/Simular.vue`
- Create: `database/migrations/2026_08_15_000002_retira_configuracion_de_ia.php`

- [ ] **Step 1: Separar lo que se va de lo que se queda en el motor**

`ConvalidacionEngine` tiene nueve métodos públicos. Se van los del emparejamiento automático: `asignacionOptima()`, `similitud()`, `nombreCanonico()`. **Se quedan** `normaliza()` —la usa el catálogo del especialista para no duplicar cursos—, `titulo()`, `mallaDeCarrera()` y `poolCursosUsil()`.

Confirmar antes de borrar:

```bash
grep -rn "asignacionOptima\|->similitud(\|nombreCanonico\|IAConvalidacion" app tests resources/js
```

- [ ] **Step 2: Escribir la prueba que falla**

```php
    /** Sin IA no hay nada que sugerir: el administrativo escoge dentro de lo que
     *  el especialista autorizó, y esa lista no la propone una máquina. */
    public function test_no_quedan_rutas_de_ia(): void
    {
        foreach (['simulaciones.sugerir-ia', 'simulaciones.sugerir-similitud', 'simulaciones.extraer-ia'] as $ruta) {
            $this->assertFalse(\Illuminate\Support\Facades\Route::has($ruta), "La ruta {$ruta} sigue existiendo.");
        }
    }
```

- [ ] **Step 3: Correrla y verificar que falla** — Expected: FAIL, las tres rutas existen.

- [ ] **Step 4: Retirar rutas, servicio, métodos del motor y pestaña de IA**

`simulaciones.metodo` (ENUM `'manual'|'ia'`) se va por migración, junto con `simulacion_detalle.confianza`. El ENUM `simulacion_detalle.origen` colapsa: si todo lo registra una persona, deja de distinguir nada.

La migración debe además **borrar de la tabla `configuraciones` las claves de IA**, si las hubiera. Hoy la tabla está vacía, pero una base instalada puede tenerlas.

- [ ] **Step 5: Dejar `Configuracion/Index.vue` sin la pestaña de IA**

Al quitarla la pantalla queda sin contenido. **No la borres**: la Task G1 la reutiliza como perfil. Déjala con un estado vacío honesto de una línea; G1 la llena.

- [ ] **Step 6: Verificar, formatear y confirmar**

---

# BLOQUE F — Credenciales sin correo

### Task F1: El alta muestra las credenciales en pantalla

**Files:**
- Modify: `app/Http/Controllers/UsuarioController.php` (`store`, `resetPassword`, y el método `enviarCredenciales`)
- Modify: `resources/js/Pages/Usuarios/Index.vue`, `resources/js/Pages/Usuarios/Form.vue`
- Modify: `tests/Feature/EntregaCredencialesTest.php`

**Interfaces:**
- Produces: `store()` y `resetPassword()` devuelven la contraseña temporal en un flash de un solo uso (`->with('credenciales', ['email' => …, 'temporal' => …])`), no por correo.

- [ ] **Step 1: Escribir la prueba que falla**

```php
    /** El alta entrega la contraseña en pantalla, no por correo: no hay servidor
     *  SMTP y el administrador es quien la hace llegar. */
    public function test_el_alta_devuelve_la_contrasena_temporal_en_pantalla(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $this->actingAs($this->superusuario())->post('/usuarios', [
            'nombre' => 'Nueva Especialista',
            'email' => 'nueva.especialista@usil.edu.pe',
            'rol_id' => \App\Models\Role::where('nombre', \App\Models\Role::ESPECIALISTA)->firstOrFail()->id,
        ])->assertRedirect()
          ->assertSessionHas('credenciales', fn ($c) => $c['email'] === 'nueva.especialista@usil.edu.pe'
              && is_string($c['temporal']) && strlen($c['temporal']) >= 12);

        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    /** Y la contraseña en claro no se guarda en ninguna parte: ni en auditoría. */
    public function test_la_contrasena_temporal_no_queda_en_auditoria(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $this->actingAs($this->superusuario())->post('/usuarios', [
            'nombre' => 'Otro Usuario',
            'email' => 'otro.usuario@usil.edu.pe',
            'rol_id' => \App\Models\Role::where('nombre', \App\Models\Role::ESPECIALISTA)->firstOrFail()->id,
        ]);

        $temporal = session('credenciales')['temporal'];

        foreach (\App\Models\AuditoriaLog::all() as $fila) {
            $this->assertStringNotContainsString($temporal, json_encode($fila->toArray()),
                'La contraseña temporal quedó escrita en el registro de auditoría.');
        }
    }
```

- [ ] **Step 2: Correrlas y verificar que fallan** — Expected: 2 FAIL; hoy se envía correo y no hay flash `credenciales`.

- [ ] **Step 3: Cambiar el canal en el controlador**

Sustituir la llamada a `enviarCredenciales()` por el flash. Retirar el `use App\Mail\AccesoSistemaMail` de `UsuarioController`. Comprobar que **ni el `AuditoriaService::registrar()` del alta ni el del reseteo reciben la contraseña**.

- [ ] **Step 4: La pantalla que muestra y copia**

En `Usuarios/Index.vue`, cuando llegue el flash `credenciales`, mostrar un panel destacado con el correo, la contraseña en texto legible y un botón **Copiar**. El aviso tiene que ser explícito:

> Esta contraseña se muestra una sola vez. Cópiala y hazla llegar al usuario antes de salir de esta pantalla. Si la pierdes, regenérala desde la lista de usuarios.

Usa `navigator.clipboard.writeText`. **Si falla —el navegador la bloquea fuera de HTTPS—, no dejes al administrador sin salida**: el campo debe ser seleccionable para copiar a mano.

- [ ] **Step 5: Verificar en el navegador** — dar de alta un usuario, copiar la contraseña, cerrar sesión y entrar con ella. Debe pedir cambio de contraseña en el primer acceso.

- [ ] **Step 6: Suite, pint y commit**

---

### Task F2: Retirar la infraestructura de correo

**Files:**
- Delete: `app/Support/EntregaCredenciales.php`, `app/Mail/AccesoSistemaMail.php`, `app/Mail/AccesoPortalMail.php`, `app/Mail/RecuperarPasswordMail.php`
- Delete: las vistas Blade de esos correos en `resources/views/`
- Delete: `tests/Feature/CorreoSinInyeccionTest.php`
- Modify: `app/Http/Controllers/Auth/PasswordController.php`, `app/Models/User.php`, `routes/web.php`
- Modify: `resources/js/Pages/Auth/*.vue` (el enlace «¿Olvidaste tu contraseña?»)
- Create: `database/migrations/2026_08_15_000003_retira_recuperacion_por_correo.php`

> ⚠️ **Decisión pendiente que afecta al alcance.** `AccesoPortalMail` entrega credenciales al **postulante**, no al personal. Tu encargo habla del administrador creando usuarios del sistema. Lo natural es tratarlo igual —el asesor copia y entrega—, pero **son dos flujos y dos pantallas distintas**. Si no lo confirmas, esta tarea toca solo el personal y el portal del postulante se queda como está; dilo en el informe.

- [ ] **Step 1: Escribir la prueba que falla**

```php
    /** Sin servidor de correo no hay forma de verificar que quien pide el reseteo
     *  es el dueño de la cuenta: el camino es pedírselo al administrador. */
    public function test_no_queda_recuperacion_de_contrasena_por_correo(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('password.olvide'));
        $this->assertFalse(class_exists(\App\Mail\RecuperarPasswordMail::class));
    }
```

- [ ] **Step 2: Correrla y verificar que falla**

- [ ] **Step 3: Retirar rutas, clases y columnas**

`usuarios.token_recuperacion` y `usuarios.token_expira` quedan sin uso: se van por migración. **Antes de borrarlas, comprobar que ningún token vivo queda pendiente** —`WHERE token_expira > NOW()`— y avisar si los hay.

- [ ] **Step 4: Quitar el enlace del login y poner en su lugar el camino real**

Sustituir «¿Olvidaste tu contraseña?» por una línea que diga a quién dirigirse: *«¿Olvidaste tu contraseña? Pídele al administrador del sistema que la regenere.»* Un enlace que no lleva a ningún sitio es peor que una instrucción.

- [ ] **Step 5: Suite, pint y commit**

---

# BLOQUE G — Perfil personal

### Task G1: Cada usuario administra su cuenta

**Files:**
- Create: `app/Http/Controllers/PerfilController.php`
- Modify: `resources/js/Pages/Configuracion/Index.vue` (pasa a ser el perfil)
- Modify: `routes/web.php`, `resources/js/Layouts/AppLayout.vue`
- Create: `tests/Feature/PerfilTest.php`

**Interfaces:**
- Consumes: `Configuracion/Index.vue` vaciada por la Task E2.
- Produces: rutas `perfil.index` (GET `/perfil`), `perfil.update` (PUT), `perfil.password` (PUT). **Sin permiso especial**: solo `auth`.

- [ ] **Step 1: Escribir las pruebas que fallan**

```php
    /** El perfil es de cada quien: no lo gatea ningún permiso, solo estar dentro. */
    public function test_cualquier_usuario_autenticado_entra_a_su_perfil(): void
    {
        $this->actingAs($this->usuarioConRol(\App\Models\Role::ADMINISTRATIVO))
            ->get('/perfil')->assertOk();
    }

    public function test_el_usuario_edita_sus_propios_datos(): void
    {
        $u = $this->usuarioConRol(\App\Models\Role::ESPECIALISTA);

        $this->actingAs($u)->put('/perfil', ['nombre' => 'Nombre Corregido', 'email' => $u->email])
            ->assertRedirect();

        $this->assertSame('Nombre Corregido', $u->fresh()->nombre);
    }

    /** Cambiar la contraseña exige la actual: si no, una sesión abierta y
     *  desatendida basta para secuestrar la cuenta. */
    public function test_cambiar_la_contrasena_exige_la_actual(): void
    {
        $u = $this->usuarioConRol(\App\Models\Role::ESPECIALISTA);

        $this->actingAs($u)->put('/perfil/password', [
            'actual' => 'la-que-no-es',
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
        ])->assertInvalid('actual');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Demo#1234', $u->fresh()->password_hash));
    }

    /** Y nadie puede cambiarse el rol desde su propio perfil. */
    public function test_el_perfil_no_permite_cambiarse_el_rol(): void
    {
        $u = $this->usuarioConRol(\App\Models\Role::ADMINISTRATIVO);
        $superusuario = \App\Models\Role::where('nombre', \App\Models\Role::SUPERUSUARIO)->firstOrFail();

        $this->actingAs($u)->put('/perfil', [
            'nombre' => $u->nombre, 'email' => $u->email, 'rol_id' => $superusuario->id,
        ]);

        $this->assertNotSame($superusuario->id, $u->fresh()->rol_id,
            'El perfil dejó que el usuario se ascendiera a sí mismo.');
    }
```

- [ ] **Step 2: Correrlas y verificar que fallan** — Expected: 4 FAIL, la ruta `/perfil` no existe.

- [ ] **Step 3: Escribir el controlador**

`update()` acepta **solo** `nombre` y `email`. El `rol_id` y `activo` no se leen de la petición: son del administrador, no del interesado. La prueba del Step 1 lo comprueba, y esa es la parte que no se puede relajar.

`email` valida `unique:usuarios,email,{$id}` — el índice único de la Fase 1 lo rechazaría igual, pero un error de validación se lee mejor que una excepción de base.

`password()` exige `actual` (verificada con `Hash::check`), `password` confirmada, y limpia `primer_acceso`.

- [ ] **Step 4: Reutilizar la pantalla de Configuración**

Dos pestañas: **Mis datos** (nombre, correo) y **Contraseña** (actual, nueva, confirmación). Renombrar la entrada del menú de «Configuración» a **«Mi perfil»** y quitarle el `puede('configuracion.gestionar')`: la ve todo el mundo.

- [ ] **Step 5: Verificar en el navegador con dos roles distintos** — entrar como Especialista y como Administrativo, cambiar el nombre, cambiar la contraseña, cerrar sesión y entrar con la nueva.

- [ ] **Step 6: Suite, pint y commit**

---

## Orden y dependencias

| Orden | Tarea | Depende de |
|---|---|---|
| 1 | **E1** Mallas Externas | nada |
| 2 | **E2** IA | nada (subsume la A3 pendiente) |
| 3 | **F1** Credenciales en pantalla | nada |
| 4 | **F2** Retirar el correo | F1 — no se puede quitar el canal viejo antes de tener el nuevo |
| 5 | **G1** Perfil | E2 (usa la pantalla que E2 vacía) y F2 (comparten `PasswordController`) |

E1, E2 y F1 son independientes: si hay que priorizar para enseñar algo, **F1 es la más visible**.

---

## Lo que este plan NO resuelve

**Los 18 fallos de la línea base siguen ahí.** Son de la Task C1 del plan anterior: el guardado de la simulación todavía escribe el grano viejo de `simulacion_detalle`, y por eso una simulación **se puede ver pero no guardar con notas**. Ninguna tarea de este plan lo toca, y sigue siendo el trabajo que falta para que el circuito quede completo de verdad.

## Preguntas abiertas

1. **El portal del postulante, ¿también entrega credenciales en pantalla?** Afecta solo al alcance de F2. Sin respuesta, F2 toca únicamente al personal.
2. **`usuario:password` por consola, ¿se queda?** Es un comando artisan que regenera contraseñas. Con la pantalla nueva es redundante, pero es la única salida si alguien pierde el acceso al panel de usuarios. Recomiendo conservarlo.

## Self-review

**Cobertura de lo pedido:**

| Requisito | Tarea |
|---|---|
| Eliminar el módulo de Mallas Externas | E1 |
| Gestión de usuarios sin SMTP | F1 |
| El administrador crea y se genera contraseña temporal | Ya existe · F1 cambia el canal |
| El administrador copia las credenciales | F1 Step 4 |
| Cambio de contraseña en el primer acceso | **Ya funciona** (`LoginController:85`) |
| Todo interno, sin módulos externos | E2 + F2 |
| Eliminar la configuración de IA | E2 |
| Perfil personal: editar datos | G1 |
| Perfil personal: cambiar contraseña | G1 |

**Consistencia:** `Role::ESPECIALISTA` y `Role::ADMINISTRATIVO` vienen de la Task A1 y se usan en las pruebas de F1 y G1. `Configuracion/Index.vue` la vacía E2 y la llena G1 — por eso E2 no la borra.

**Riesgo mayor identificado:** que E1 retire el permiso `mallas_externas.gestionar` solo del catálogo PHP y no de la tabla `permisos`. Es exactamente el defecto que la Task A1 tuvo que corregir después, y por eso el Step 6 de E1 lo exige explícitamente.
