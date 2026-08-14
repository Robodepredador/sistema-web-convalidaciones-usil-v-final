# Flujo especialista / administrativo — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Separar quién decide *qué se puede convalidar* (especialista, una vez) de quién decide *qué se convalida en este caso* (administrativo, por postulante), y hacer que la simulación quede restringida al catálogo que el especialista registró.

**Architecture:** Cuatro fases. **A vacía** lo que el modelo nuevo deja muerto —es deleción pura y hace más barato todo lo demás—. **B** construye el catálogo de equivalencias y la pantalla del especialista. **C** invierte la pantalla de simulación para que consuma ese catálogo y da al administrativo su bandeja. **D** resuelve qué pasa con el catálogo cuando se activa una malla USIL nueva. Al terminar **C** el cliente ve el circuito completo.

**Tech Stack:** Laravel 11 · PHP 8.2 · MySQL 8.0 (InnoDB) · Inertia + Vue 3 · PHPUnit contra MySQL real.

---

## El cambio de fondo, en una frase

Hoy el coordinador decide libremente qué equivale a qué, expediente por expediente, y el catálogo de equivalencias que ya existe en el sistema **no se consulta nunca** (verificado: `SimulacionController` no lee `equivalencias_malla` en ninguna de sus 1072 líneas).

Mañana el especialista registra la política una vez, y el administrativo solo puede escoger dentro de ella.

---

## Decisiones de diseño tomadas, con su porqué

Estas tres no son detalles de implementación: cambian la forma de las tablas y hay que entenderlas antes de tocar nada.

### D1 · Los cursos externos cuelgan de la carrera externa, no de la malla externa

Hoy un curso externo pertenece a una malla externa, que pertenece a una carrera externa. Eso significa que **«Algoritmia Básica» de la malla SENATI 2019 y «Algoritmia Básica» de la malla 2023 son dos filas distintas.**

El cliente fue explícito: cuando llega un estudiante con una versión nueva, el especialista *«solo agrega el nombre del curso de la malla versión 2»* y a partir de ahí vale para cualquiera. Con el modelo actual tendría que registrar la misma equivalencia una vez por cada versión de malla que exista. El modelo actual **impide el flujo que el cliente pidió**, no solo lo complica.

`cursos_externos.malla_externa_id` pasa a ser **nullable e informativo** (de dónde salió este curso), y la pertenencia real es `carrera_externa_id`. La clave natural pasa a ser `(carrera_externa_id, nombre_normalizado)`.

### D2 · La equivalencia es solo el par, sin tabla de cabecera

La auditoría anterior proponía una cabecera `mapeos_malla` para no repetir (malla externa, malla USIL, autor) en cada fila. **Se descarta.** El flujo del cliente no tiene sesiones de mapeo: el especialista agrega equivalencias de una en una, a lo largo de meses, según van llegando estudiantes. No hay ninguna entidad «mapeo» que exista en su cabeza ni en su trabajo.

Además, con D1 la malla externa deja de ser parte de la identidad, y la malla USIL se deriva del curso. Lo único que queda es el par y quién lo registró.

Las dos restricciones `uq_eqm_externo_destino` y `uq_eqm_usil_origen` **se eliminan**: el cliente confirmó que un curso externo puede servir para varios cursos USIL y viceversa. Queda una sola: **no registrar dos veces el mismo par.**

> **Consecuencia declarada por el cliente:** un curso externo que valga para dos cursos USIL le da al estudiante créditos de dos cursos habiendo llevado uno. Es su decisión, tomada con conocimiento. No se implementa ninguna traba.

### D3 · El especialista escribe, el sistema cataloga

El especialista teclea el nombre del curso externo. El sistema lo normaliza con `ConvalidacionEngine::normaliza()` —que ya existe y ya quita tildes, mayúsculas y puntuación— y busca ese nombre entre los cursos de esa carrera externa: si existe lo reutiliza, si no lo crea.

Para el especialista es escribir libre. Para la base es un catálogo sin duplicados. Y si alguna vez se carga la malla externa desde Excel, esos cursos ya están y el campo los autocompleta.

---

## Global Constraints

- **Base de datos de pruebas:** `convalidaciones_test`, fijada en `phpunit.xml`. Las pruebas corren contra **MySQL real**: las FK, los `CHECK` y las columnas generadas se ejercitan de verdad.
- **Idioma:** identificadores y docblocks en español. Cada migración lleva docblock que explica **por qué**, no qué.
- **Estilo de migración:** clase anónima `return new class extends Migration`. Cuando MySQL exija que una columna generada exista antes de indexarla, llamadas `Schema::table` separadas.
- **Toda migración con `down()` funcional**, verificado con `migrate:rollback`, y **guardado para ser reintentable** (`Schema::hasColumn`, consulta a `information_schema.STATISTICS`).
- **Comprobación previa obligatoria** en toda migración que cree una restricción sobre datos existentes: MySQL no tiene DDL transaccional, así que hay que abortar con `\RuntimeException` nombrando las filas en conflicto **antes** de tocar el esquema. Modelo: `database/migrations/2026_08_13_000005_unicidad_en_catalogos.php`.
- **Sin factories** salvo `UserFactory`: `Model::create([...])`, roles con `$this->seed(RoleSeeder::class)`.
- **Pruebas que esperan rechazo de la base:** `try { …; $this->fail('…'); } catch (QueryException $e) { $this->assertStringContainsString('<indice>', $e->getMessage()); }`. Nunca `expectException` a secas.
- **Estilo antes de cada commit:** CI corre `./vendor/bin/pint --test` sin `continue-on-error`. Correr `./vendor/bin/pint` sobre los archivos tocados, pasándolos como argumentos.
- **NUNCA `git add -A` ni `git add .`**: hay scripts de desarrollo sin versionar en la raíz.
- **Commits en español**, sin tildes en el asunto, terminados con `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.
- `migrate` y `migrate:rollback` sin argumentos actúan sobre la base de **DESARROLLO**. Correr `migrate:status` antes de cada rollback.

---

## Línea base

La rama `refactor/normalizacion-bd` cierra la Fase 1 de normalización con **18 fallos preexistentes conocidos** congelados en [`linea-base-2026-08-13.txt`](linea-base-2026-08-13.txt) y 185 pruebas en verde.

**Este plan cambia esa línea base a propósito**, porque borra funcionalidad. La Tarea A0 la vuelve a congelar después de la primera deleción. A partir de ahí el criterio de cada tarea es el mismo de siempre: **los mismos fallos que la línea base vigente, ni uno más**, más las pruebas nuevas.

---

# FASE A — Vaciar

Deleción pura. No se ve en pantalla salvo el menú de roles, pero cada cosa que sale aquí es superficie que no hay que arrastrar por las fases siguientes ni explicar en la inspección.

---

### Task A1: Reducir a cinco roles

Hoy hay ocho: Superusuario, Asesor de Admisión, Ejecutivo Comercial, Coordinador de Carrera, Director de Carrera, Decano, Auditor y Consulta. El cliente describe cinco. Los cuatro que sobran no aparecen en ninguna parte de su flujo.

**Files:**
- Modify: `app/Models/Role.php:15-47`
- Modify: `app/Models/Permiso.php` (constante `POR_ROL`)
- Modify: `database/seeders/RoleSeeder.php`
- Create: `database/migrations/2026_08_14_000001_reduce_roles_al_flujo_del_cliente.php`
- Modify: `tests/Feature/RbacTest.php`

**Interfaces:**
- Produces: `Role::ESPECIALISTA = 'Especialista en Convalidaciones'` y `Role::ADMINISTRATIVO = 'Administrativo de Facultad'` (renombra `COORDINADOR`). Las fases B y C dependen de estos dos nombres.

- [ ] **Step 1: Averiguar si hay usuarios en los roles que se eliminan**

```bash
php artisan tinker --execute="foreach (DB::select('SELECT r.nombre, COUNT(u.id) n FROM roles r LEFT JOIN usuarios u ON u.rol_id=r.id GROUP BY r.nombre') as \$r) printf('%-40s %d'.PHP_EOL, \$r->nombre, \$r->n);"
```

Expected: un recuento por rol. **Si algún usuario está en Director, Decano, Auditor o Consulta, la migración debe reasignarlo, no dejarlo huérfano.** Anota los números antes de seguir: la migración los va a mover.

- [ ] **Step 2: Escribir la prueba que falla**

Añadir a `tests/Feature/RbacTest.php`:

```php
    /**
     * El cliente describe cinco roles: superusuario, especialista, administrativo
     * de facultad, asesor de admisión y ejecutivo comercial. Los cuatro que
     * sobraban sostenían una cadena de aprobación que este flujo no tiene.
     */
    public function test_el_catalogo_de_roles_es_el_del_flujo_del_cliente(): void
    {
        $esperados = [
            \App\Models\Role::SUPERUSUARIO,
            \App\Models\Role::ESPECIALISTA,
            \App\Models\Role::ADMINISTRATIVO,
            \App\Models\Role::ASESOR,
            \App\Models\Role::EJECUTIVO,
        ];

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->assertEqualsCanonicalizing($esperados, \App\Models\Role::pluck('nombre')->all());
    }
```

- [ ] **Step 3: Correrla y verificar que falla**

```bash
php artisan test --filter=test_el_catalogo_de_roles_es_el_del_flujo_del_cliente
```

Expected: FAIL — constantes `ESPECIALISTA` / `ADMINISTRATIVO` no definidas.

- [ ] **Step 4: Redefinir las constantes de rol**

En `app/Models/Role.php`, dejar exactamente estas cinco y **eliminar** `DIRECTOR`, `DECANO`, `AUDITOR`, `CONSULTA` y la constante `SIN_ACCESO` que las agrupa:

```php
    public const SUPERUSUARIO = 'Superusuario';

    public const ADMIN = 'Superusuario';              // alias histórico, se conserva

    public const ASESOR = 'Asesor de Admisión';

    public const EJECUTIVO = 'Ejecutivo Comercial de Admisión';

    /** Registra la política: mallas USIL de sus carreras y equivalencias contra todas las instituciones. */
    public const ESPECIALISTA = 'Especialista en Convalidaciones';

    /** Aplica la política: atiende las simulaciones de sus carreras asignadas. */
    public const ADMINISTRATIVO = 'Administrativo de Facultad';
```

Revisar `Role::ALCANCE` y dejar en él los dos roles con alcance por carrera: `ESPECIALISTA` y `ADMINISTRATIVO`.

- [ ] **Step 5: Escribir la migración de datos**

Crear `database/migrations/2026_08_14_000001_reduce_roles_al_flujo_del_cliente.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El sistema tenía ocho roles porque modelaba una cadena de aprobación —el
 * coordinador propone, el director aprueba, el decano visa— que el flujo real
 * del cliente no tiene. Ahí no hay visto bueno posterior: el administrativo
 * atiende la simulación y ahí termina.
 *
 * Director de Carrera, Decano, Auditor y Consulta salen. Coordinador de Carrera
 * se renombra a Administrativo de Facultad, que es lo que de verdad hace: el
 * cliente aclaró que puede ser cualquier administrativo, coordinador o profesor
 * designado. Y entra el Especialista en Convalidaciones, que no existía.
 *
 * Los usuarios de los roles retirados se mueven a Administrativo de Facultad,
 * el permiso más cercano a lo que hacían: no se borra a nadie ni se le deja
 * apuntando a un rol inexistente.
 */
return new class extends Migration
{
    private const RETIRADOS = ['Director de Carrera', 'Decano', 'Auditor', 'Consulta / Alta Dirección'];

    public function up(): void
    {
        DB::table('roles')->where('nombre', 'Coordinador de Carrera')
            ->update(['nombre' => 'Administrativo de Facultad']);

        $destino = DB::table('roles')->where('nombre', 'Administrativo de Facultad')->value('id');

        if ($destino !== null) {
            DB::table('usuarios')
                ->whereIn('rol_id', DB::table('roles')->whereIn('nombre', self::RETIRADOS)->pluck('id'))
                ->update(['rol_id' => $destino]);
        }

        DB::table('roles')->whereIn('nombre', self::RETIRADOS)->delete();

        DB::table('roles')->insertOrIgnore([
            'nombre' => 'Especialista en Convalidaciones',
            'descripcion' => 'Registra mallas USIL y equivalencias de sus carreras asignadas',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('nombre', 'Especialista en Convalidaciones')->delete();

        DB::table('roles')->where('nombre', 'Administrativo de Facultad')
            ->update(['nombre' => 'Coordinador de Carrera']);

        // Los roles retirados se recrean vacíos: a qué usuario pertenecía cada
        // uno no se puede saber después de haberlos movido.
        foreach (self::RETIRADOS as $nombre) {
            DB::table('roles')->insertOrIgnore([
                'nombre' => $nombre, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
};
```

- [ ] **Step 6: Reasignar los permisos por rol**

En `app/Models/Permiso.php`, dejar `POR_ROL` así:

```php
        Role::SUPERUSUARIO => ['*'],
        Role::ESPECIALISTA => [
            'dashboard.ver', 'catalogos.gestionar', 'mallas_externas.gestionar',
            'equivalencias.gestionar',
        ],
        Role::ADMINISTRATIVO => [
            'dashboard.ver', 'solicitudes.ver', 'evaluacion.ver', 'evaluacion.editar',
            'convalidacion.ver',
        ],
        Role::ASESOR => [
            'dashboard.ver', 'solicitudes.ver', 'solicitudes.crear', 'solicitudes.editar',
        ],
        Role::EJECUTIVO => [
            'dashboard.ver', 'solicitudes.ver', 'solicitudes.crear',
        ],
```

Añadir al catálogo el permiso nuevo:

```php
        'equivalencias.gestionar' => ['Equivalencias', 'Registrar el catálogo de equivalencias por curso'],
```

Y **eliminar del catálogo** los que sostenían la cadena de aprobación: `evaluacion.aprobar`, `evaluacion.observar`, `evaluacion.reasignar`, `evaluacion.proponer`, `solicitudes.validar`, `solicitudes.asignar`.

- [ ] **Step 7: Adaptar las pruebas de RBAC**

`tests/Feature/RbacTest.php` tiene 7 pruebas escritas contra los roles viejos. Las que comprueban el alcance por carrera se conservan cambiando el rol; las que comprueban la cadena de aprobación se **eliminan**, porque la funcionalidad que verifican deja de existir. Documenta en el commit cuáles eliminaste y por qué.

- [ ] **Step 8: Correr, formatear y confirmar**

```bash
php artisan migrate && php artisan test --filter=RbacTest && ./vendor/bin/pint app/Models/Role.php app/Models/Permiso.php database/seeders/RoleSeeder.php tests/Feature/RbacTest.php
```

- [ ] **Step 9: Recongelar la línea base**

Esta tarea **borra funcionalidad a propósito**, así que la línea base cambia. Regenerarla y confirmarla en el mismo commit:

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > docs/superpowers/plans/linea-base-2026-08-14.txt
```

Revisar el archivo antes de confirmarlo: **cada fallo nuevo debe corresponder a funcionalidad que esta tarea eliminó**. Si aparece uno que no sabes explicar, es tuyo y hay que arreglarlo, no congelarlo.

- [ ] **Step 10: Commit**

```bash
git add app/Models/Role.php app/Models/Permiso.php database/seeders/RoleSeeder.php database/migrations/2026_08_14_000001_reduce_roles_al_flujo_del_cliente.php tests/Feature/RbacTest.php docs/superpowers/plans/linea-base-2026-08-14.txt
git commit -m "refactor(rbac): cinco roles, los del flujo real, y fuera la cadena de aprobacion"
```

---

### Task A2: Retirar el flujo de revisión y aprobación

Con Director y Decano fuera, las columnas que sostenían el visto bueno quedan sin dueño. `postulantes` lleva `revision_estado`, `revision_provisional`, `revision_observaciones`, `revisado_por`, `revisado_en`; `postulante_destinos` lleva `estado_equivalencias`, `equivalencias_revisado_por`, `equivalencias_revisado_en`.

**Antes de borrar nada**, confirmar con el cliente una sola cosa: el asesor de admisión, ¿valida los datos del postulante antes de que llegue a facultad? Si sí, `revision_estado` y su revisor **se quedan** y solo se va lo de equivalencias. Si no, se va todo.

**Files:**
- Create: `database/migrations/2026_08_14_000002_retira_el_flujo_de_aprobacion.php`
- Modify: `app/Models/Postulante.php`, `app/Models/PostulanteDestino.php`
- Modify: `app/Http/Controllers/PostulanteController.php`, `app/Http/Controllers/DashboardController.php`
- Delete: `tests/Feature/RevisionFlujoTest.php`, `tests/Feature/PostulanteRevisionTest.php`
- Modify: `resources/js/Pages/Postulantes/*.vue`

- [ ] **Step 1: Medir el alcance real antes de tocar**

```bash
grep -rn "revision_estado\|revisado_por\|estado_equivalencias\|revision_provisional" app resources/js tests database | wc -l
grep -rln "revision_estado\|revisado_por\|estado_equivalencias\|revision_provisional" app resources/js tests database
```

Expected: la lista exacta de archivos afectados. **Esa lista es el alcance de la tarea**; no descubras archivos a mitad del trabajo.

- [ ] **Step 2: Escribir la prueba que falla**

Añadir a `tests/Feature/IntegridadEsquemaTest.php`:

```php
    /** El flujo del cliente no tiene visto bueno posterior: el administrativo
     *  atiende la simulación y ahí termina. Las columnas de aprobación se van. */
    public function test_los_destinos_no_conservan_el_flujo_de_aprobacion(): void
    {
        foreach (['estado_equivalencias', 'equivalencias_revisado_por', 'equivalencias_revisado_en'] as $columna) {
            $this->assertFalse(
                Schema::hasColumn('postulante_destinos', $columna),
                "postulante_destinos.{$columna} sostiene una aprobación que este flujo no tiene."
            );
        }
    }
```

- [ ] **Step 3: Correrla y verificar que falla**

```bash
php artisan test --filter=test_los_destinos_no_conservan_el_flujo_de_aprobacion
```

Expected: FAIL — las tres columnas existen.

- [ ] **Step 4: Escribir la migración**

Crear `database/migrations/2026_08_14_000002_retira_el_flujo_de_aprobacion.php`, con la misma forma que las migraciones de la Fase 1: docblock que explique por qué, `down()` funcional que recree columnas, FK e índices con sus nombres originales, y guardas de idempotencia. Las columnas a retirar de `postulante_destinos` son `estado_equivalencias`, `equivalencias_revisado_por` (con su FK) y `equivalencias_revisado_en`, más el índice `postulante_destinos_estado_equivalencias_index`.

`asignado_a_id` **se conserva**: es lo que asigna una simulación a un administrativo, y la Fase C lo necesita.

- [ ] **Step 5: Limpiar modelos, controladores y pantallas**

Recorrer la lista del Step 1. En `DashboardController.php:30-31,152-159` hay recuentos por `estado_equivalencias` que hay que retirar o sustituir por un recuento de simulaciones atendidas. **No dejes una tarjeta del panel mostrando cero.**

- [ ] **Step 6: Eliminar las pruebas de la funcionalidad retirada**

`RevisionFlujoTest` (8 pruebas) y `PostulanteRevisionTest` (1) verifican comportamiento que deja de existir. Se eliminan enteras. En el commit, decir cuántas y por qué.

- [ ] **Step 7: Verificar, formatear, recongelar y confirmar**

Mismo procedimiento que la Task A1: `migrate`, suite completa, revisar que cada fallo nuevo se explique por lo eliminado, regenerar `linea-base-2026-08-14.txt`, `pint`, commit.

---

### Task A3: Retirar la sugerencia automática dentro de la simulación

Si el administrativo solo puede escoger entre lo que el especialista autorizó, no hay nada que sugerir. La IA y la similitud dejan de tener función.

**Files:**
- Modify: `routes/web.php:262-264`
- Modify: `app/Http/Controllers/SimulacionController.php`
- Modify: `app/Services/ConvalidacionEngine.php`
- Modify: `resources/js/Pages/Simulaciones/Simular.vue` (pestaña «Asistido»)
- Create: `database/migrations/2026_08_14_000003_retira_la_sugerencia_automatica.php`
- Delete: `tests/Feature/SugerenciaIATest.php`

**Se conserva:** `mallas-externas/extraer-ia` (`routes/web.php:172`). Eso lee un PDF para cargar una malla externa: es entrada de datos, no sugerencia de equivalencias, y el especialista lo va a seguir usando.

- [ ] **Step 1: Separar lo que se va de lo que se queda en el motor**

`ConvalidacionEngine` tiene nueve métodos públicos. Se van los que sirven al emparejamiento automático: `asignacionOptima()`, `similitud()`, `nombreCanonico()`. **Se quedan** `normaliza()` —la Fase B la necesita para catalogar nombres—, `titulo()`, `mallaDeCarrera()` y `poolCursosUsil()`.

Confirmar antes de borrar:

```bash
grep -rn "asignacionOptima\|->similitud(\|nombreCanonico" app tests resources/js
```

- [ ] **Step 2: Escribir la prueba que falla**

```php
    /** El administrativo escoge dentro de lo autorizado; no hay nada que sugerir. */
    public function test_no_quedan_rutas_de_sugerencia_en_simulaciones(): void
    {
        foreach (['simulaciones.sugerir-ia', 'simulaciones.sugerir-similitud', 'simulaciones.extraer-ia'] as $ruta) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Route::has($ruta),
                "La ruta {$ruta} sugiere equivalencias, que ya no es decisión del administrativo."
            );
        }
    }
```

- [ ] **Step 3: Correrla y verificar que falla**

```bash
php artisan test --filter=test_no_quedan_rutas_de_sugerencia
```

Expected: FAIL — las tres rutas existen.

- [ ] **Step 4: Retirar rutas, métodos de controlador, métodos del motor y la pestaña**

- [ ] **Step 5: Migración de columnas**

`simulaciones.metodo` (ENUM `'manual'|'ia'`) se va. En `simulacion_detalle` se van `confianza` y el ENUM `origen` colapsa: si todo lo registra una persona, la columna deja de distinguir nada.

- [ ] **Step 6: Verificar, formatear, recongelar y confirmar**

---

### Task A4: Retirar los no convalidables por palabra clave

En el modelo nuevo un curso no es convalidable **porque nadie le registró equivalencia**. Una tabla de reglas del tipo «todo lo que se llame Física no se convalida» es una segunda fuente de verdad que puede contradecir a la primera.

**Files:**
- Create: `database/migrations/2026_08_14_000004_retira_no_convalidables_por_palabra_clave.php`
- Delete: `app/Models/CursoNoConvalidable.php`, `database/seeders/CursosNoConvalidablesSeeder.php`
- Delete: `tests/Feature/NoConvalidablesPorCarreraTest.php`, `tests/Unit/NoConvalidablesTest.php`
- Modify: `app/Services/ConvalidacionEngine.php` (`esNoConvalidable()`, `motivoNoConvalidable()`)
- Modify: `app/Http/Controllers/SimulacionController.php:597`, `app/Http/Controllers/MapeoMallasController.php:187`

La migración retira la tabla `cursos_no_convalidables` y la columna `cursos_usil.convalidable`.

> **Nota de secuencia:** esto deshace parte de la Task 9 de la Fase 1 (el observer de `clave_normalizada`) y toda la Task 5 sobre esa tabla. No es trabajo perdido: mientras la tabla existió, existió bien. Ahora deja de existir.

---

### Task A5: Retirar sílabos y la tabla de convalidaciones

**Sílabos.** El cliente confirmó que el especialista los maneja fuera del sistema. `cursos_usil.silabo_texto` y `cursos_externos.silabo_texto` son dos columnas de texto largo que nadie va a leer y que hay que mantener actualizadas para nada.

**Convalidaciones.** Verificado: las tres exportaciones del módulo (`routes/web.php:278-280`) trabajan directamente sobre `{simulacion}`, no sobre la tabla `convalidaciones`. El módulo «Pre-Convalidaciones Oficiales» es una vista sobre las simulaciones con tres descargas. La tabla —con su número de memorándum, sus responsables en JSON y sus acciones de confirmar y anular— **no la usa nadie para eso**.

**Se conserva:** la pantalla como historial y las tres rutas de descarga (PDF, Excel, Excel Oficial).

**Files:**
- Create: `database/migrations/2026_08_14_000005_retira_silabos_y_convalidaciones.php`
- Delete: `app/Models/Convalidacion.php`
- Modify: `app/Http/Controllers/ConvalidacionController.php` (queda solo el índice)
- Modify: `app/Models/Simulacion.php` (`estaCerrada()`, `tieneConvalidacionVigente()`)
- Delete: `tests/Feature/IntegridadConvalidacionTest.php`
- Modify: `tests/Feature/DocumentosEmitidosTest.php`, `tests/Feature/AuditoriaE2ETest.php`

> ⚠️ **Decisión pendiente que afecta a esta tarea.** Hoy la existencia de una convalidación es lo único que impide seguir editando una simulación ya entregada (`Simulacion::estaCerrada()`). Al borrar la tabla, **nada congela nunca una simulación**: el administrativo puede cambiarla después de que el estudiante se llevó el PDF.
>
> Si se quiere cerrar ese hueco, lo natural es un `cerrado_en TIMESTAMP NULL` en `simulaciones` con un botón «cerrar expediente» en la pantalla del administrativo — **no** resucitar la tabla. Está aislado como **Task E1** al final de este plan para que la decisión no bloquee nada.

---

# FASE B — El catálogo del especialista

Aquí empieza lo que el cliente ve.

---

### Task B1: Los cursos externos cuelgan de la carrera externa

Implementa la decisión **D1**. Sin esto, el especialista tendría que registrar la misma equivalencia una vez por cada versión de malla externa.

**Files:**
- Create: `database/migrations/2026_08_14_000006_cursos_externos_por_carrera.php`
- Modify: `app/Models/CursoExterno.php`, `app/Models/MallaExterna.php`
- Modify: `app/Http/Controllers/MallaExternaController.php`, `app/Http/Controllers/CatalogoController.php`
- Modify: `tests/Feature/IntegridadEsquemaTest.php`

**Interfaces:**
- Produces: `cursos_externos.carrera_externa_id` NOT NULL; `cursos_externos.malla_externa_id` NULLABLE (procedencia); `cursos_externos.nombre_normalizado` derivado; UNIQUE `(carrera_externa_id, nombre_normalizado)`; `UNIQUE (id, carrera_externa_id)` como destino de FK compuesta. La Task B2 depende de todo esto.

- [ ] **Step 1: Escribir la prueba que falla**

```php
    /** El mismo curso externo vale igual venga de la malla 2019 o de la 2023:
     *  la versión es procedencia, no identidad. Registrarlo dos veces obligaría
     *  al especialista a repetir la evaluación de sílabos por cada versión. */
    public function test_un_curso_externo_no_se_repite_en_la_misma_carrera(): void
    {
        $carrera = CarreraExterna::create([
            'institucion_id' => InstitucionExterna::create([
                'tipo_id' => TipoInstitucion::create(['nombre' => 'Instituto'])->id,
                'nombre' => 'Instituto de Prueba', 'pais' => 'Perú',
            ])->id,
            'nombre' => 'Desarrollo de Prueba',
        ]);

        CursoExterno::create(['carrera_externa_id' => $carrera->id, 'nombre' => 'Algoritmia Básica']);

        try {
            // Mismo curso escrito distinto: acentos y mayúsculas no lo hacen otro.
            CursoExterno::create(['carrera_externa_id' => $carrera->id, 'nombre' => 'ALGORITMIA BASICA']);
            $this->fail('Debió rechazar el curso externo repetido en la misma carrera.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('uq_curso_externo_carrera_nombre', $e->getMessage());
        }
    }
```

- [ ] **Step 2: Correrla y verificar que falla**

```bash
php artisan test --filter=test_un_curso_externo_no_se_repite_en_la_misma_carrera
```

Expected: FAIL — `carrera_externa_id` no existe en `cursos_externos`.

- [ ] **Step 3: Escribir la migración**

Estructura, en este orden:

1. **Comprobación previa** (obligatoria por las Global Constraints): detectar cursos externos que, al colapsar por `(carrera_externa_id, nombre normalizado)`, quedarían duplicados. Abortar nombrándolos, sin deduplicar automáticamente.
2. Añadir `carrera_externa_id` nullable y poblarla desde `mallas_externas.carrera_externa_id`.
3. Añadir `nombre_normalizado` como columna generada. **La normalización de PHP quita puntuación y eso MySQL no lo expresa**: la columna generada solo puede hacer minúsculas y plegado de acentos vía collation. Decide entre columna generada limitada o columna materializada mantenida por un observer, y **documenta la elección en el docblock**. Si es materializada, sacarla de `$fillable` y calcularla en `saving`, como se hizo con `clave_normalizada` en la Fase 1.
4. `carrera_externa_id` a NOT NULL con su FK.
5. `malla_externa_id` a NULLABLE con `ON DELETE SET NULL`.
6. UNIQUE `(carrera_externa_id, nombre_normalizado)` con nombre `uq_curso_externo_carrera_nombre`, y UNIQUE `(id, carrera_externa_id)` para las FK compuestas de B2.
7. Retirar el UNIQUE viejo `uq_curso_externo_malla_nombre`.

**Cuidado con el índice de la FK:** al crear un compuesto cuya columna líder es la de una FK, MySQL suelta el índice simple que la respaldaba. Es exactamente el error 1553 que mordió en la Fase 1 — ver `database/migrations/2026_08_13_000005_unicidad_en_catalogos.php`, que lo documenta y lo resuelve en ambas direcciones.

- [ ] **Step 4: Adaptar la carga de mallas externas**

`MallaExternaController` crea cursos bajo una malla. Ahora debe además resolver la carrera y **reutilizar el curso si ya existe con ese nombre normalizado**, en vez de crear uno nuevo. Ese es el comportamiento que hace que cargar la malla 2023 sobre la 2019 no duplique el catálogo.

- [ ] **Step 5: Correr, verificar el rollback, formatear y confirmar**

---

### Task B2: La equivalencia se vuelve libre en ambos sentidos

Implementa la decisión **D2**. Es el cambio que hace posible el flujo del cliente.

**Files:**
- Create: `database/migrations/2026_08_14_000007_equivalencias_libres.php`
- Create: `app/Models/Equivalencia.php`
- Delete: `app/Models/EquivalenciaMalla.php`
- Modify: `tests/Feature/IntegridadEsquemaTest.php`
- Modify: `tests/Feature/MapeoMallasTest.php`

**Interfaces:**
- Produces: tabla `equivalencias` con `PRIMARY KEY (curso_usil_id, curso_externo_id)`, más `carrera_externa_id` propagada y verificada por FK compuesta contra `cursos_externos (id, carrera_externa_id)`, y `registrado_por_id`. Modelo `App\Models\Equivalencia` con `$fillable = ['curso_usil_id', 'curso_externo_id', 'carrera_externa_id', 'registrado_por_id']`. Las tareas B3 y C1 lo consumen.

**Por qué `carrera_externa_id` propagada:** la pantalla del especialista y la de simulación filtran por «esta carrera externa», y sin la columna cada consulta necesitaría dos saltos. Al atarla con FK compuesta contra `cursos_externos (id, carrera_externa_id)` no es duplicación: es una proyección que InnoDB verifica en cada escritura y que no puede contradecir a su origen.

- [ ] **Step 1: Escribir las pruebas que fallan**

```php
    /** Un curso USIL acepta varias opciones externas: el especialista puede
     *  registrar que POO se convalida con tres cursos distintos de SENATI. */
    public function test_un_curso_usil_admite_varias_equivalencias(): void
    {
        // … crear malla USIL con un curso, carrera externa con tres cursos …
        foreach ($externos as $externo) {
            Equivalencia::create([
                'curso_usil_id' => $cursoUsil->id,
                'curso_externo_id' => $externo->id,
                'carrera_externa_id' => $carreraExterna->id,
                'registrado_por_id' => $especialista->id,
            ]);
        }

        $this->assertSame(3, Equivalencia::where('curso_usil_id', $cursoUsil->id)->count());
    }

    /** Y un curso externo puede servir para varios cursos USIL: el cliente lo
     *  confirmó explícitamente, con la consecuencia de créditos que implica. */
    public function test_un_curso_externo_sirve_para_varios_cursos_usil(): void
    {
        // … dos cursos USIL, un curso externo, dos equivalencias …
        $this->assertSame(2, Equivalencia::where('curso_externo_id', $externo->id)->count());
    }

    /** Lo único prohibido es registrar dos veces el mismo par. */
    public function test_no_se_repite_el_mismo_par_de_equivalencia(): void
    {
        $par = [
            'curso_usil_id' => $cursoUsil->id, 'curso_externo_id' => $externo->id,
            'carrera_externa_id' => $carreraExterna->id, 'registrado_por_id' => $especialista->id,
        ];
        Equivalencia::create($par);

        try {
            Equivalencia::create($par);
            $this->fail('Debió rechazar el par de equivalencia repetido.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('PRIMARY', $e->getMessage());
        }
    }

    /** Y la FK compuesta impide colgar un curso externo de otra carrera. */
    public function test_no_se_puede_registrar_un_curso_externo_de_otra_carrera(): void
    {
        try {
            Equivalencia::create([
                'curso_usil_id' => $cursoUsil->id,
                'curso_externo_id' => $externoDeOtraCarrera->id,
                'carrera_externa_id' => $carreraExterna->id,
                'registrado_por_id' => $especialista->id,
            ]);
            $this->fail('Debió rechazar un curso externo que no pertenece a esa carrera.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('fk_equivalencia_externo', $e->getMessage());
        }
    }
```

- [ ] **Step 2: Correrlas y verificar que fallan**

```bash
php artisan test --filter="test_un_curso_usil_admite|test_un_curso_externo_sirve|test_no_se_repite_el_mismo_par|test_no_se_puede_registrar_un_curso_externo_de_otra"
```

Expected: 4 FAIL — la tabla `equivalencias` no existe.

- [ ] **Step 3: Escribir la migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de equivalencias existía pero la simulación no lo consultaba
 * nunca: el coordinador decidía libremente, expediente por expediente. Y el
 * modelo lo forzaba a decidir, porque dos restricciones únicas impedían que un
 * curso USIL tuviera más de un equivalente.
 *
 * El cliente pide lo contrario: el especialista registra, tras comparar
 * sílabos, TODAS las opciones válidas —POO puede convalidarse con tres cursos
 * distintos de SENATI—, y el administrativo escoge dentro de esa lista según lo
 * que el estudiante llevó de verdad. Un curso externo puede además servir para
 * varios cursos USIL; el cliente lo confirmó sabiendo que eso otorga créditos
 * de dos cursos por uno.
 *
 * Queda una sola restricción: no registrar dos veces el mismo par. Y como la
 * clave es el par, no hace falta un id sustituto.
 *
 * No hay tabla de cabecera. La propuesta anterior agrupaba por (malla externa,
 * malla USIL), pero ese agrupamiento no existe en el trabajo real del
 * especialista: agrega equivalencias de una en una, durante meses, según van
 * llegando estudiantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE equivalencias (
            curso_usil_id      BIGINT UNSIGNED NOT NULL,
            curso_externo_id   BIGINT UNSIGNED NOT NULL,
            carrera_externa_id BIGINT UNSIGNED NOT NULL,
            registrado_por_id  BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            PRIMARY KEY (curso_usil_id, curso_externo_id),
            KEY ix_equivalencia_externo (curso_externo_id),
            KEY ix_equivalencia_carrera (carrera_externa_id, curso_usil_id),
            KEY ix_equivalencia_autor   (registrado_por_id),
            CONSTRAINT fk_equivalencia_usil FOREIGN KEY (curso_usil_id)
                REFERENCES cursos_usil (id) ON DELETE CASCADE,
            CONSTRAINT fk_equivalencia_externo FOREIGN KEY (curso_externo_id, carrera_externa_id)
                REFERENCES cursos_externos (id, carrera_externa_id) ON DELETE CASCADE,
            CONSTRAINT fk_equivalencia_autor FOREIGN KEY (registrado_por_id)
                REFERENCES usuarios (id) ON DELETE SET NULL
        ) ENGINE=InnoDB');

        // Traspaso de lo que hubiera en el modelo viejo (1 fila en desarrollo).
        if (Schema::hasTable('equivalencias_malla')) {
            DB::statement('INSERT IGNORE INTO equivalencias
                (curso_usil_id, curso_externo_id, carrera_externa_id, registrado_por_id, created_at, updated_at)
                SELECT em.curso_usil_id, em.curso_externo_id, ce.carrera_externa_id,
                       em.usuario_id, em.created_at, em.updated_at
                FROM equivalencias_malla em
                INNER JOIN cursos_externos ce ON ce.id = em.curso_externo_id');

            Schema::drop('equivalencias_malla');
        }
    }

    public function down(): void
    {
        // El modelo viejo no puede representar lo que este permite: si un curso
        // USIL tiene varias opciones, solo cabe una. Se conserva la de menor id
        // de curso externo y se pierde el resto. La reversión es estructural,
        // no de datos.
        DB::statement('CREATE TABLE IF NOT EXISTS equivalencias_malla (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_externo_id BIGINT UNSIGNED NOT NULL,
            curso_usil_id    BIGINT UNSIGNED NOT NULL,
            malla_externa_id BIGINT UNSIGNED NOT NULL,
            malla_usil_id    BIGINT UNSIGNED NOT NULL,
            usuario_id       BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_eqm_externo_destino (curso_externo_id, malla_usil_id),
            UNIQUE KEY uq_eqm_usil_origen (curso_usil_id, malla_externa_id)
        ) ENGINE=InnoDB');

        Schema::dropIfExists('equivalencias');
    }
};
```

- [ ] **Step 4: Crear el modelo**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una opción válida de convalidación, declarada por el especialista tras
 * comparar sílabos. No es una decisión sobre un estudiante concreto: es
 * política, y vale para todos los que vengan de esa carrera externa.
 *
 * carrera_externa_id no es un dato independiente: es una clave propagada que
 * la FK compuesta obliga a coincidir con la carrera del curso externo.
 */
class Equivalencia extends Model
{
    protected $table = 'equivalencias';

    public $incrementing = false;

    protected $primaryKey = null;

    protected $keyType = 'string';

    protected $fillable = [
        'curso_usil_id', 'curso_externo_id', 'carrera_externa_id', 'registrado_por_id',
    ];

    public function cursoUsil(): BelongsTo
    {
        return $this->belongsTo(CursoUsil::class, 'curso_usil_id');
    }

    public function cursoExterno(): BelongsTo
    {
        return $this->belongsTo(CursoExterno::class, 'curso_externo_id');
    }
}
```

> Eloquent no maneja claves compuestas de forma nativa. Con `$primaryKey = null` y `$incrementing = false` funcionan `create()`, las consultas y las relaciones; **no** funcionan `find()` ni `save()` sobre una instancia recuperada. Para borrar, usar `where(...)->delete()`. Si esto resulta incómodo en B3, la alternativa es añadir un `id` sustituto **manteniendo el UNIQUE del par** — pero pruébalo primero sin él.

- [ ] **Step 5: Correr las pruebas, verificar el rollback, formatear y confirmar**

---

### Task B3: La pantalla del especialista

Da la vuelta a `MapeoMallas`: en vez de partir del curso externo, se recorre la malla USIL y a cada curso se le cuelgan opciones.

**Files:**
- Modify: `app/Http/Controllers/MapeoMallasController.php` (208 líneas, se reescribe casi entero)
- Modify: `resources/js/Pages/MapeoMallas/Crear.vue` (567 líneas)
- Modify: `resources/js/Pages/MapeoMallas/Index.vue`
- Modify: `routes/web.php` (grupo `equivalencias.gestionar`)
- Modify: `tests/Feature/MapeoMallasTest.php`

**La pantalla, de arriba abajo:**

1. **Tres selectores encadenados:** carrera USIL (limitada a las asignadas al especialista) → institución externa → carrera externa. La malla USIL no se elige: es la activa de esa carrera, y se muestra cuál es.
2. **La malla USIL agrupada por ciclo**, con la misma forma que el mockup: código, nombre y créditos por fila.
3. **En cada fila, las opciones ya registradas** como etiquetas que se pueden quitar, más un campo para añadir otra.
4. **El campo de añadir es texto con autocompletado** sobre los cursos ya conocidos de esa carrera externa. Si lo que escribe no existe, se crea (decisión **D3**).
5. **Un contador arriba:** cuántos cursos de la malla ya tienen al menos una opción y cuántos no. Es lo que le dice al especialista dónde va.

**Reglas que la pantalla debe sostener:**
- El especialista solo ve carreras USIL de su alcance (`AlcanceService::autorizarCarrera`, ya existente).
- Puede registrar contra **cualquier** institución externa: su alcance limita las carreras USIL, no las universidades de origen.
- Añadir una opción no exige quitar otra. Ese es el cambio de fondo respecto de la pantalla actual.

- [ ] **Step 1: Escribir la prueba de flujo que falla**

```php
    /** El especialista registra tres opciones para el mismo curso USIL y las
     *  tres quedan disponibles. La pantalla anterior solo admitía una. */
    public function test_el_especialista_registra_varias_opciones_para_un_curso(): void
    {
        // … montar especialista con alcance, malla USIL activa, carrera externa …
        foreach (['Algoritmia Básica', 'Fundamentos de Programación', 'Introducción a Ingeniería de Software'] as $nombre) {
            $this->actingAs($especialista)
                ->post('/equivalencias-catalogo', [
                    'curso_usil_id' => $cursoUsil->id,
                    'carrera_externa_id' => $carreraExterna->id,
                    'nombre_externo' => $nombre,
                ])
                ->assertRedirect();
        }

        $this->assertSame(3, Equivalencia::where('curso_usil_id', $cursoUsil->id)->count());
    }

    /** Escribir el mismo nombre con otra grafía no crea un curso nuevo. */
    public function test_el_nombre_se_normaliza_antes_de_catalogar(): void
    {
        $this->actingAs($especialista)->post('/equivalencias-catalogo', [
            'curso_usil_id' => $cursoUsil->id, 'carrera_externa_id' => $carreraExterna->id,
            'nombre_externo' => 'Algoritmia Básica',
        ]);

        $this->actingAs($especialista)->post('/equivalencias-catalogo', [
            'curso_usil_id' => $otroCursoUsil->id, 'carrera_externa_id' => $carreraExterna->id,
            'nombre_externo' => 'ALGORITMIA  BASICA',
        ]);

        $this->assertSame(1, CursoExterno::where('carrera_externa_id', $carreraExterna->id)->count(),
            'La segunda grafía debió reutilizar el curso, no crear otro.');
    }

    /** Y el especialista no toca carreras USIL fuera de su alcance. */
    public function test_el_especialista_no_registra_en_carrera_ajena(): void
    {
        $this->actingAs($especialista)->post('/equivalencias-catalogo', [
            'curso_usil_id' => $cursoDeOtraCarrera->id,
            'carrera_externa_id' => $carreraExterna->id,
            'nombre_externo' => 'Cualquiera',
        ])->assertForbidden();
    }
```

- [ ] **Step 2: Correrlas y verificar que fallan** · **Step 3: Reescribir el controlador** · **Step 4: Reescribir la pantalla** · **Step 5: Verificar en el navegador** · **Step 6: Formatear y confirmar**

> **Verificación en navegador obligatoria en esta tarea.** Es la primera pantalla nueva del flujo y la suite no cubre la interacción. Levantar el servidor, registrar tres opciones sobre un curso, recargar y comprobar que siguen ahí.

---

# FASE C — La simulación aplica el catálogo

Al terminar esta fase el cliente ve el circuito completo.

---

### Task C1: Invertir la pantalla de simulación

**Este es el cambio más grande del plan.** Hoy la pantalla está montada al revés de lo que el cliente quiere:

| | Hoy | Cliente |
|---|---|---|
| Filas | Cursos que llevó el estudiante (`cursosOrigen`, arranca vacío) | **Cursos de la malla USIL activa**, agrupados por ciclo |
| Desplegable | Todos los cursos USIL de la carrera (`poolUsil`) | **Solo las opciones que el especialista autorizó** para ese curso |
| Quién decide | El administrativo, libremente | El especialista, de antemano |

**Files:**
- Modify: `app/Http/Controllers/SimulacionController.php` (`propsWorkspace`, `store`, `update`)
- Modify: `resources/js/Pages/Simulaciones/Simular.vue` (949 líneas; la tabla se rehace)
- Modify: `app/Models/SimulacionDetalle.php`
- Create: `database/migrations/2026_08_14_000008_detalle_por_curso_usil.php`
- Modify: `tests/Feature/SimulacionTest.php`, `tests/Feature/ExportacionPreconvalidacionTest.php`

**Cambio de grano en `simulacion_detalle`:** hoy una fila es «un curso que llevó el estudiante». Pasa a ser «un curso de la malla USIL», con su curso externo elegido o vacío. `curso_usil_id` pasa a NOT NULL y `curso_externo_id` sigue nullable: **vacío significa no convalidado**, y esa es toda la lógica que hace falta. La etiqueta «No convalidable» del mockup desaparece como estado marcable.

- [ ] **Step 1: Escribir la prueba de flujo que falla**

```php
    /** El desplegable de cada curso trae solo lo que el especialista autorizó.
     *  Antes traía el catálogo entero y el administrativo decidía por su cuenta. */
    public function test_la_simulacion_solo_ofrece_las_equivalencias_registradas(): void
    {
        // … especialista registra 2 opciones para el curso A, ninguna para el B …
        $respuesta = $this->actingAs($administrativo)
            ->get("/simulaciones/crear?postulante={$postulante->id}&carrera={$carrera->id}");

        $respuesta->assertInertia(fn ($page) => $page
            ->has('cursosMalla', 2)
            ->where('cursosMalla.0.opciones', fn ($ops) => count($ops) === 2)
            ->where('cursosMalla.1.opciones', fn ($ops) => count($ops) === 0));
    }

    /** Y guardar una equivalencia no autorizada se rechaza en el servidor, no
     *  solo en el desplegable: la restricción tiene que sobrevivir a un POST. */
    public function test_no_se_guarda_una_equivalencia_que_nadie_autorizo(): void
    {
        $this->actingAs($administrativo)->post('/simulaciones', [
            'postulante_id' => $postulante->id,
            'carrera_usil_id' => $carrera->id,
            'filas' => [['curso_usil_id' => $cursoUsil->id, 'curso_externo_id' => $externoSinAutorizar->id]],
        ])->assertInvalid();
    }
```

- [ ] **Step 2: Correrlas y verificar que fallan** · **Step 3: Migración del grano del detalle** · **Step 4: Reescribir `propsWorkspace` y el guardado** · **Step 5: Rehacer la tabla en `Simular.vue`** · **Step 6: Verificar las tres exportaciones** · **Step 7: Navegador** · **Step 8: Formatear y confirmar**

> **Cuidado con las exportaciones.** `PreconvalidacionSheet`, `FormatoErpSheet` y `NoConvalidadosSheet` leen el detalle con el grano viejo. **El Excel ya se rompió dos veces en este repositorio** (commits `d961e69` y `9aebda9`): abrir los tres archivos generados y mirarlos, no confiar solo en la suite.

---

### Task C2: La bandeja del administrativo

Que vea solo las simulaciones de sus carreras asignadas.

**Files:**
- Modify: `app/Http/Controllers/SimulacionController.php` (índice)
- Modify: `app/Models/Concerns/FiltraPorCarrera.php`
- Modify: `resources/js/Pages/Simulaciones/Index.vue`
- Modify: `app/Http/Controllers/DashboardController.php`

La mayor parte existe: `permisos_carrera`, `AlcanceService::autorizarCarrera` y el scope `FiltraPorCarrera` ya funcionan. Falta conectarlo a la bandeja y sustituir en el panel las tarjetas del flujo de aprobación retirado por un recuento de simulaciones pendientes de atender.

---

# FASE D — Herencia entre mallas

### Task D1: Al activar una malla nueva, el sistema propone

**El problema:** el especialista invierte meses comparando sílabos. Sus equivalencias apuntan a cursos de una malla USIL concreta. Cuando active la malla 2026, esos cursos son filas nuevas: **el catálogo entero quedaría en cero.**

**La solución: proponer, no decidir.**

Al activar una malla nueva, la pantalla muestra tres grupos:

- **Cursos que siguen** (mismo código en ambas mallas): sus equivalencias se traen marcadas como *heredadas*. **Funcionan desde el primer minuto**, con un contador de cuántas faltan por confirmar.
- **Cursos nuevos:** sin equivalencias. Es el trabajo que queda.
- **Cursos que desaparecieron:** sus equivalencias quedan con la malla vieja. **No se borran**, porque las simulaciones ya hechas tienen que seguir explicándose.

**Por qué funcionan sin confirmar:** la alternativa es que activar una malla congele el sistema hasta que alguien revise doscientos cursos. Nadie activaría nunca una malla. Se marcan, se cuentan, y el especialista las va confirmando.

**Dato pendiente que decide el criterio de emparejamiento:** en la base actual hay dos mallas, pero de **carreras distintas** (`IS0101…` y `SI0101…`), así que **no hay evidencia de si USIL mantiene el código del curso entre versiones de la misma carrera.** Preguntárselo al especialista antes de implementar. Si los códigos son estables, se empareja por código; si no, por nombre normalizado. **La pantalla es la misma; cambia solo el criterio**, y por eso esta tarea no bloquea a las anteriores.

**Files:**
- Create: migración que añade `equivalencias.heredada_de_curso_id` nullable y `confirmada_en` nullable
- Create: `app/Http/Controllers/HerenciaMallaController.php`
- Create: `resources/js/Pages/Mallas/Herencia.vue`

---

# FASE E — Cierres

### Task E1: Cerrar expediente *(pendiente de decisión)*

Al borrar `convalidaciones` en A5, nada congela nunca una simulación: el administrativo puede editarla después de que el estudiante se llevó el PDF, y papel y sistema divergen.

Si se decide cerrarlo: `simulaciones.cerrado_en TIMESTAMP NULL`, botón «cerrar expediente» en la pantalla del administrativo, y bloqueo de edición cuando está cerrado. Dos o tres horas. **No hacer nada hasta que el cliente responda.**

### Task E2: Fusionar `planes_estudio` y `mallas_curriculares`

Sigue siendo válida y sigue siendo la peor violación del esquema: dos tablas que se identifican por la misma clave `(carrera_id, anio, version)`, y `planes_estudio` con **cero filas** desde siempre. Va al final porque las fases B, C y D tocan esas mismas pantallas: mejor normalizar sobre el modelo definitivo.

Detalle completo en la Task 13 de [`2026-08-13-normalizacion-base-datos.md`](2026-08-13-normalizacion-base-datos.md).

### Task E3: `simulaciones` sin columnas derivables

Diez columnas que se derivan del postulante o del plan. Detalle en la Task 14 del plan anterior. **`universidad_origen` ya tiene camino alternativo** en `Simular.vue:36`.

---

## Qué queda descartado del plan anterior

| Tarea | Motivo |
|---|---|
| Task 12 (`mapeos_malla` + `mapeo_curso`) | **Superada por B2.** Asumía una equivalencia por curso, que es justo lo que el cliente descarta, y una cabecera que su flujo no tiene. |
| Task 10 (competencias a tabla) | **En suspenso.** Si son decorativas, borrarlas es mejor que normalizarlas. Preguntar antes de trabajar. |
| Task 11 (prerrequisitos N:M) | **Sigue válida**, independiente de todo esto. Prioridad baja. |

---

## Self-review

**Cobertura de lo que el cliente describió:**

| Requisito | Tarea |
|---|---|
| Tres roles de facultad + superusuario | A1 |
| El especialista gestiona mallas USIL de sus carreras | A1 (permisos) + ya existente |
| El especialista registra equivalencias contra todas las instituciones | B2, B3 |
| Varias opciones por curso USIL | B2 |
| Un curso externo para varios cursos USIL | B2 |
| Los nombres se acumulan sin importar la versión de malla externa | B1 |
| El administrativo ve solo sus carreras | C2 |
| El administrativo escoge dentro de lo autorizado | C1 |
| Curso sin opciones = no convalidable | C1 |
| Tres documentos exportables, sin acto oficial | A5 |
| Sílabos fuera del sistema | A5 |

**Dependencias de orden:** A1 antes que todo (define los roles que B3 y C2 usan). B1 antes que B2 (la FK compuesta necesita `cursos_externos (id, carrera_externa_id)`). B2 antes que B3 y C1. C1 antes que C2. D1 después de B3.

**Consistencia de nombres verificada:** `Role::ESPECIALISTA` y `Role::ADMINISTRATIVO` se definen en A1 y se consumen en B3 y C2. `App\Models\Equivalencia` se crea en B2 y se consume en B3, C1 y D1. `carrera_externa_id` significa lo mismo en `cursos_externos` (B1) y `equivalencias` (B2).

**Preguntas abiertas que NO bloquean el arranque:** el visto bueno del asesor (afecta solo a A2), el criterio de emparejamiento entre mallas (afecta solo a D1), el botón de cerrar expediente (aislado en E1), y si las competencias se usan (fuera de este plan).
