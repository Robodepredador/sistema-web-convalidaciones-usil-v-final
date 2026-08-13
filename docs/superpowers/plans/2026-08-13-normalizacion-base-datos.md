# Normalización de la base de datos — Plan de implementación en dos fases

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dejar el esquema de `convalidaciones_usil` en 3FN defendible ante auditoría, con la aplicación web coherente con él y verificada por pruebas automáticas.

**Architecture:** Dos fases separadas por riesgo. La **Fase 1** elimina defectos de integridad mediante restricciones declarativas sin cambiar la forma de ninguna tabla: casi no toca código de aplicación y cada tarea se prueba haciendo que MySQL rechace un dato inválido. La **Fase 2** reestructura las tablas con violaciones de 1FN y 3FN, aprovechando que hoy están vacías o casi (`planes_estudio` 0 filas, `equivalencias_malla` 1, `mallas_curriculares` 2, `simulaciones` 3). Cada tarea es entregable por separado: se puede parar después de cualquiera y el esquema queda estrictamente mejor que antes.

**Tech Stack:** Laravel 11 · PHP 8.2 · MySQL 8.0 (InnoDB) · Inertia + Vue 3 · PHPUnit contra MySQL real (no SQLite).

---

## Global Constraints

- **Base de datos de pruebas:** `convalidaciones_test`, fijada en `phpunit.xml`. Las pruebas corren contra **MySQL real**, así que las FK, los `CHECK` y las columnas generadas se ejercitan de verdad.
- **Idioma del código:** identificadores y docblocks en español, como el resto del repositorio. Cada migración lleva un docblock que explica **por qué**, no qué (ver `database/migrations/2026_07_13_000001_fix_mallas_curriculares_unique_index.php` como modelo).
- **Estilo de migración:** clase anónima `return new class extends Migration`. Cuando MySQL exija que una columna generada exista antes de indexarla, usar **llamadas `Schema::table` separadas**, como ya hace la migración citada.
- **Todas las migraciones deben tener `down()` funcional.** Se verifica con `migrate:rollback` en cada tarea.
- **Sin factories:** el repositorio solo tiene `UserFactory`. Los modelos se crean con `Model::create([...])` y los roles con `$this->seed(RoleSeeder::class)`, como en `tests/Feature/PostulanteValidacionTest.php`.
- **Nomenclatura de índices:** prefijo `uq_` para únicos nuevos, `ix_` para no únicos nuevos. No renombrar los existentes.
- **Ningún cambio de permisos, rutas ni RBAC en este plan.** Ese trabajo está en vuelo y es la causa de la línea base roja (ver abajo). Mezclarlo haría imposible atribuir un fallo.
- `php artisan test` completo tarda ~50 s. Se corre entero al final de cada tarea.

---

## Línea base: 18 fallos conocidos y explicados

**Medido el 2026-08-13 sobre el árbol de trabajo actual: `18 failed, 171 passed (702 assertions)`.** El listado exacto está congelado en [`linea-base-2026-08-13.txt`](linea-base-2026-08-13.txt).

Estos 18 fallos **no son bugs**: son pruebas que describen el comportamiento *anterior* mientras el código ya se movió al *nuevo*, por trabajo sin confirmar. Causas raíz identificadas:

| Fallos | Causa raíz | Evidencia |
|---:|---|---|
| 7 | `Role::DECANO` perdió `mallas_externas.gestionar` en `app/Models/Permiso.php:82-84`; las rutas de `/mallas-externas` lo exigen (`routes/web.php:164`). Devuelve 403 donde las pruebas esperan 200. | `git diff app/Models/Permiso.php` |
| 7 | Las rutas `mallas/{malla}/no-convalidables` y `configuracion/no-convalidables` fueron **eliminadas**; la función se movió a `mapeo-mallas/no-convalidable`. Devuelve 404. | `git diff routes/web.php` |
| 1 | `SimulacionTest` espera estado `generada`; el flujo nuevo guarda `borrador` y añade rutas `guardar-borrador` y `validar`. | `git diff routes/web.php:268-270` |
| 3 | `LectorMallaExcelTest`, `ExportacionPreconvalidacionTest`, `NotaMinimaTest` — trabajo en vuelo sobre Excel y nota mínima. | — |

### Criterio de éxito de TODA tarea de este plan

> **Los mismos 18 fallos, ni uno más.** Más las pruebas nuevas de la tarea, en verde.

**Por qué no se exige la suite en verde primero:** reparar esos 18 exige decisiones de producto que no están tomadas (¿DECANO debe gestionar mallas externas? ¿la gestión de no convalidables se queda en Mapeo de Mallas?). Bloquear la normalización hasta resolverlas es bloquearla indefinidamente. Como los 18 están **enumerados y su causa identificada**, una línea base congelada da la misma protección que el verde: cualquier fallo nº 19 es tuyo y aparece al instante.

Comando de verificación, idéntico en todas las tareas:

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

`diff` sin salida (salvo la línea `Tests:`, que cambia al sumar pruebas nuevas) = sin regresiones.

---

## Mapa de archivos

### Fase 1 — se crean

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/2026_08_13_000001_elimina_columnas_muertas_de_postulantes.php` | Quita 3 columnas duplicadas de `postulante_destinos` |
| `database/migrations/2026_08_13_000002_unifica_tipo_documento_en_simulaciones.php` | Alinea el ENUM y **repara los datos falsificados** |
| `database/migrations/2026_08_13_000003_email_unico_en_postulantes.php` | Convierte índice en único (credencial de acceso) |
| `database/migrations/2026_08_13_000004_cierra_agujeros_de_unicidad_con_null.php` | `mallas_externas.version` NOT NULL + `carrera_key` generada |
| `database/migrations/2026_08_13_000005_unicidad_en_catalogos.php` | UNIQUE faltantes en instituciones, carreras externas y cursos |
| `database/migrations/2026_08_13_000006_una_sola_malla_activa_por_carrera.php` | Columna generada + UNIQUE parcial |
| `database/migrations/2026_08_13_000007_clave_primaria_compuesta_en_puentes.php` | Elimina `id` sustituto de las dos tablas puente |
| `database/migrations/2026_08_13_000008_collation_uniforme.php` | Alinea el default del esquema |
| `tests/Feature/IntegridadEsquemaTest.php` | **Todas** las pruebas de Fase 1: cada restricción rechaza su dato inválido |

### Fase 1 — se modifican

| Archivo | Cambio |
|---|---|
| `app/Models/Postulante.php:22-43` | Quitar 3 entradas de `$fillable`, 1 de `$casts` |
| `app/Models/CursoNoConvalidable.php:20` | Sacar `clave_normalizada` de `$fillable`; añadir observer `saving` |
| `app/Http/Controllers/SimulacionController.php:597` | Dejar de escribir `clave_normalizada` a mano |

### Fase 2 — se crean

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/2026_08_14_000001_competencias_como_tabla.php` | 1FN: JSON con comas → catálogo + pivote |
| `database/migrations/2026_08_14_000002_prerequisitos_como_tabla_puente.php` | 1FN: cardinalidad real N:M |
| `database/migrations/2026_08_14_000003_mapeos_de_malla.php` | 3FN: cabecera + detalle con FK compuestas |
| `database/migrations/2026_08_14_000004_fusiona_planes_y_mallas.php` | 3FN: elimina la duplicación de entidad |
| `database/migrations/2026_08_14_000005_simulaciones_sin_redundancia.php` | 3FN: −10 columnas, diamante cerrado |
| `app/Models/Competencia.php`, `app/Models/MapeoMalla.php`, `app/Models/MapeoCurso.php` | Modelos nuevos |
| `tests/Feature/NormalizacionEstructuralTest.php` | Pruebas de Fase 2 |

---

# FASE 1 — Integridad declarativa

Nueve tareas. Ninguna cambia la forma de una tabla ni el contrato de una ruta. El frontend no se toca.

---

### Task 1: Congelar la línea base

**Files:**
- Create: `docs/superpowers/plans/linea-base-2026-08-13.txt` *(ya generado)*

**Interfaces:**
- Produces: el archivo de línea base contra el que compara **toda** tarea posterior.

- [ ] **Step 1: Verificar que la línea base existe y coincide con la realidad**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

Expected: sin salida. Si difiere, el árbol cambió desde el 2026-08-13: **regenera el archivo y anótalo**, no continúes con una base falsa.

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/plans/
git commit -m "docs: congela la linea base de pruebas antes de normalizar el esquema"
```

---

### Task 2: Eliminar las columnas muertas de `postulantes`

Duplican exactamente tres columnas de `postulante_destinos` (violación 3FN-7). Verificado: aparecen en `$fillable` pero **ningún punto de `app/` las lee**; todo el flujo consume `postulante_destinos`.

**Files:**
- Create: `database/migrations/2026_08_13_000001_elimina_columnas_muertas_de_postulantes.php`
- Create: `tests/Feature/IntegridadEsquemaTest.php`
- Modify: `app/Models/Postulante.php:22-43`

**Interfaces:**
- Produces: `IntegridadEsquemaTest`, clase donde se acumulan las pruebas de toda la Fase 1.

- [ ] **Step 1: Escribir la prueba que falla**

Crear `tests/Feature/IntegridadEsquemaTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Restricciones de integridad del esquema (Fase 1 de la normalización).
 *
 * Cada prueba comprueba que la BASE DE DATOS rechaza un dato inválido, no que
 * la aplicación lo valide. Es la diferencia entre una regla y una costumbre:
 * la validación de Laravel se puede saltar con un seeder, un comando artisan o
 * una importación; una restricción de InnoDB no.
 */
class IntegridadEsquemaTest extends TestCase
{
    use RefreshDatabase;

    /** El estado de equivalencias vive en postulante_destinos, no duplicado en el padre. */
    public function test_postulantes_no_conserva_las_columnas_duplicadas_de_destinos(): void
    {
        foreach (['estado_equivalencias', 'equivalencias_revisado_por', 'equivalencias_revisado_en'] as $columna) {
            $this->assertFalse(
                Schema::hasColumn('postulantes', $columna),
                "postulantes.{$columna} duplica a postulante_destinos y debió eliminarse."
            );
        }
    }
}
```

- [ ] **Step 2: Correrla y verificar que falla**

```bash
php artisan test --filter=IntegridadEsquemaTest
```

Expected: FAIL — `postulantes.estado_equivalencias duplica a postulante_destinos y debió eliminarse.`

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_08_13_000001_elimina_columnas_muertas_de_postulantes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un postulante puede tener VARIAS carreras destino, y cada una avanza por el
 * flujo de equivalencias a su propio ritmo. Ese estado es del destino, no del
 * postulante: guardarlo también en el padre obliga a elegir cuál de los N
 * destinos representa, y la respuesta correcta es "ninguno".
 *
 * Las tres columnas quedaron sin lectura en toda la aplicación cuando se creó
 * postulante_destinos; solo seguían en $fillable. Se retiran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropForeign(['equivalencias_revisado_por']);
            $table->dropIndex('postulantes_estado_equivalencias_index');
            $table->dropColumn(['estado_equivalencias', 'equivalencias_revisado_por', 'equivalencias_revisado_en']);
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->enum('estado_equivalencias', ['pendiente', 'en_revision', 'aprobada'])
                ->default('pendiente')->after('revision_estado');
            $table->foreignId('equivalencias_revisado_por')->nullable()
                ->after('estado_equivalencias')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('equivalencias_revisado_en')->nullable()->after('equivalencias_revisado_por');
            $table->index('estado_equivalencias', 'postulantes_estado_equivalencias_index');
        });
    }
};
```

- [ ] **Step 4: Limpiar el modelo**

En `app/Models/Postulante.php`, quitar de `$fillable` (línea 27) las tres claves `'estado_equivalencias'`, `'equivalencias_revisado_por'`, `'equivalencias_revisado_en'`, dejando la línea así:

```php
        'estado',
```

Y quitar de `$casts` (línea 41) la entrada:

```php
        'equivalencias_revisado_en' => 'datetime',
```

- [ ] **Step 5: Correr la prueba nueva**

```bash
php artisan test --filter=IntegridadEsquemaTest
```

Expected: PASS (1 passed).

- [ ] **Step 6: Verificar el rollback**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

Expected: ambos comandos terminan sin error.

- [ ] **Step 7: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

Expected: solo difiere la línea `Tests:` (172 passed en vez de 171). Ningún `FAILED` nuevo.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_13_000001_elimina_columnas_muertas_de_postulantes.php tests/Feature/IntegridadEsquemaTest.php app/Models/Postulante.php
git commit -m "refactor(bd): retira de postulantes las columnas de equivalencias duplicadas en destinos"
```

---

### Task 3: Unificar `tipo_documento` y reparar los datos falsificados

**Defecto activo, no teórico.** `postulantes.tipo_documento` admite 5 valores; `simulaciones.tipo_documento` solo 3. Medido en la base de desarrollo:

```
postulante 1: TEMP TMP-2026-00001     simulacion 1 (post 1): DNI
postulante 2: TEMP TMP-2026-00002     simulacion 2 (post 2): DNI
                                      simulacion 3 (post 2): DNI
```

Los dos postulantes tienen documento **TEMP** y sus tres simulaciones dicen **DNI**. El expediente que se entrega al postulante afirma un tipo de documento que la persona no tiene.

> **Nota de secuencia:** la Task 14 elimina `simulaciones.tipo_documento` por completo (es redundancia 3FN). Esta tarea igual se hace ahora porque el defecto está activo y la Fase 2 no está garantizada.

**Files:**
- Create: `database/migrations/2026_08_13_000002_unifica_tipo_documento_en_simulaciones.php`
- Modify: `tests/Feature/IntegridadEsquemaTest.php`

- [ ] **Step 1: Escribir la prueba que falla**

Añadir a `IntegridadEsquemaTest`:

```php
    /**
     * Un postulante con documento temporal debe poder tener simulación sin que
     * su tipo de documento mute por el camino. Antes el ENUM de simulaciones no
     * conocía 'TEMP' y el valor terminaba guardado como 'DNI'.
     */
    public function test_simulaciones_acepta_todos_los_tipos_de_documento_del_postulante(): void
    {
        $tipos = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simulaciones' AND COLUMN_NAME = 'tipo_documento'"
        )->t;

        foreach (['DNI', 'CE', 'PASAPORTE', 'PTP', 'TEMP'] as $tipo) {
            $this->assertStringContainsString("'{$tipo}'", $tipos,
                "simulaciones.tipo_documento no admite '{$tipo}', que postulantes sí acepta.");
        }
    }
```

- [ ] **Step 2: Correrla y verificar que falla**

```bash
php artisan test --filter=test_simulaciones_acepta_todos_los_tipos
```

Expected: FAIL — `simulaciones.tipo_documento no admite 'PTP', que postulantes sí acepta.`

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_08_13_000002_unifica_tipo_documento_en_simulaciones.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mismo dominio, dos ENUM distintos: postulantes admite DNI, CE, PASAPORTE, PTP
 * y TEMP; simulaciones solo los tres primeros. Un postulante con carné de
 * extranjería temporal generaba una simulación cuyo tipo de documento no podía
 * representarse, y el valor terminaba escrito como 'DNI'.
 *
 * El documento que firma la universidad decía entonces que la persona tiene DNI
 * cuando no lo tiene. Se amplía el ENUM y se reparan las filas ya falsificadas
 * copiando el valor verdadero desde el postulante.
 *
 * (La Fase 2 elimina esta columna: el tipo de documento es del postulante y no
 * debe duplicarse aquí. Mientras exista, que al menos no mienta.)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE simulaciones
            MODIFY tipo_documento ENUM('DNI','CE','PASAPORTE','PTP','TEMP') NOT NULL");

        DB::statement('UPDATE simulaciones s
            INNER JOIN postulantes p ON p.id = s.postulante_id
            SET s.tipo_documento = p.tipo_documento
            WHERE s.postulante_id IS NOT NULL
              AND s.tipo_documento <> p.tipo_documento');
    }

    public function down(): void
    {
        // Las filas con PTP/TEMP no caben en el ENUM viejo; se llevan a DNI, que es
        // exactamente el estado defectuoso del que veníamos.
        DB::statement("UPDATE simulaciones SET tipo_documento = 'DNI'
            WHERE tipo_documento IN ('PTP','TEMP')");

        DB::statement("ALTER TABLE simulaciones
            MODIFY tipo_documento ENUM('DNI','CE','PASAPORTE') NOT NULL");
    }
};
```

- [ ] **Step 4: Correr la prueba nueva**

```bash
php artisan test --filter=IntegridadEsquemaTest
```

Expected: PASS (2 passed).

- [ ] **Step 5: Reparar la base de desarrollo y comprobar el resultado**

```bash
php artisan migrate && php artisan tinker --execute="foreach (DB::select('SELECT s.id, s.tipo_documento ts, p.tipo_documento tp FROM simulaciones s JOIN postulantes p ON p.id=s.postulante_id') as \$r) { printf(\"sim %d: %s (postulante: %s)\n\", \$r->id, \$r->ts, \$r->tp); }"
```

Expected: las tres simulaciones muestran `TEMP (postulante: TEMP)`.

- [ ] **Step 6: Verificar el rollback**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

Expected: ambos sin error.

- [ ] **Step 7: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

Expected: sin `FAILED` nuevos.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_13_000002_unifica_tipo_documento_en_simulaciones.php tests/Feature/IntegridadEsquemaTest.php
git commit -m "fix(bd): simulaciones dejaba de reconocer PTP y TEMP y los guardaba como DNI"
```

---

### Task 4: `email` único en `postulantes`

`postulantes.email` es credencial de acceso al portal (`Postulante implements Authenticatable`, `app/Models/Postulante.php:16`) y solo tiene índice **no único**. Dos postulantes con el mismo correo hacen ambiguo el login.

**Files:**
- Create: `database/migrations/2026_08_13_000003_email_unico_en_postulantes.php`
- Modify: `tests/Feature/IntegridadEsquemaTest.php`

- [ ] **Step 1: Escribir la prueba que falla**

Añadir a `IntegridadEsquemaTest`:

```php
    /** El email es la credencial del portal: dos postulantes con el mismo correo
     *  vuelven ambiguo el inicio de sesión. Debe rechazarlo la base, no el formulario. */
    public function test_dos_postulantes_no_pueden_compartir_email(): void
    {
        $base = [
            'tipo_documento' => 'DNI', 'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
            'codigo' => 'P-0001', 'numero_documento' => '10000001', 'email' => 'repetido@ex.com',
        ];
        \App\Models\Postulante::create($base);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\Postulante::create(array_merge($base, [
            'codigo' => 'P-0002', 'numero_documento' => '10000002',
        ]));
    }

    /** Pero varios postulantes SIN correo deben seguir siendo válidos:
     *  en un índice único de MySQL cada NULL cuenta como distinto. */
    public function test_varios_postulantes_pueden_no_tener_email(): void
    {
        $base = ['tipo_documento' => 'DNI', 'nombres' => 'Sin', 'apellido_paterno' => 'Correo', 'email' => null];
        \App\Models\Postulante::create($base + ['codigo' => 'P-0003', 'numero_documento' => '10000003']);
        \App\Models\Postulante::create($base + ['codigo' => 'P-0004', 'numero_documento' => '10000004']);

        $this->assertSame(2, \App\Models\Postulante::whereNull('email')->count());
    }
```

- [ ] **Step 2: Correrlas y verificar que la primera falla**

```bash
php artisan test --filter=test_dos_postulantes_no_pueden_compartir_email
```

Expected: FAIL — no se lanzó `QueryException`.

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_08_13_000003_email_unico_en_postulantes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El postulante entra al portal con su correo. Con un índice no único, dos
 * registros podían compartirlo y el proveedor de autenticación resolvía a
 * cualquiera de los dos: quien entra ve el expediente de otra persona.
 *
 * El correo sigue siendo opcional. En un índice único de InnoDB cada NULL es
 * distinto, así que N postulantes sin correo conviven sin problema; lo que
 * queda prohibido es repetir un correo real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropIndex('postulantes_email_index');
            $table->unique('email', 'uq_postulantes_email');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropUnique('uq_postulantes_email');
            $table->index('email', 'postulantes_email_index');
        });
    }
};
```

- [ ] **Step 4: Correr las pruebas nuevas**

```bash
php artisan test --filter=IntegridadEsquemaTest
```

Expected: PASS (4 passed).

- [ ] **Step 5: Verificar el rollback**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

- [ ] **Step 6: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_13_000003_email_unico_en_postulantes.php tests/Feature/IntegridadEsquemaTest.php
git commit -m "fix(bd): el email del postulante es credencial y ahora es unico"
```

---

### Task 5: Cerrar los dos agujeros de unicidad con NULL

InnoDB trata cada NULL como distinto dentro de un índice único. Dos restricciones existentes **no restringen nada**:

- `uq_malla_externa_carrera_anio_version` incluye `version`, que es NULLABLE.
- `uq_no_convalidable_clave_carrera` incluye `carrera_id`, NULLABLE para las reglas institucionales.

**Files:**
- Create: `database/migrations/2026_08_13_000004_cierra_agujeros_de_unicidad_con_null.php`
- Modify: `tests/Feature/IntegridadEsquemaTest.php`

- [ ] **Step 1: Escribir las pruebas que fallan**

Añadir a `IntegridadEsquemaTest`:

```php
    /** Sin version NOT NULL, el índice único de mallas externas admitía duplicados
     *  exactos: dos NULL nunca chocan entre sí. */
    public function test_no_se_repite_una_malla_externa_de_la_misma_carrera_y_anio(): void
    {
        $carrera = \App\Models\CarreraExterna::create([
            'institucion_id' => \App\Models\InstitucionExterna::create([
                'tipo_id' => \App\Models\TipoInstitucion::create(['nombre' => 'Universidad'])->id,
                'nombre' => 'Instituto de Prueba',
            ])->id,
            'nombre' => 'Ingeniería de Prueba',
        ]);

        \App\Models\MallaExterna::create(['carrera_externa_id' => $carrera->id, 'anio' => 2026]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\MallaExterna::create(['carrera_externa_id' => $carrera->id, 'anio' => 2026]);
    }

    /** Y la misma regla institucional no puede cargarse dos veces. */
    public function test_no_se_repite_una_regla_institucional_no_convalidable(): void
    {
        $regla = ['carrera_id' => null, 'palabra_clave' => 'Física', 'motivo' => 'Ciencia básica'];
        \App\Models\CursoNoConvalidable::create($regla);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\CursoNoConvalidable::create($regla);
    }
```

- [ ] **Step 2: Correrlas y verificar que fallan**

```bash
php artisan test --filter="test_no_se_repite"
```

Expected: 2 FAIL — no se lanzó `QueryException` en ninguna.

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_08_13_000004_cierra_agujeros_de_unicidad_con_null.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * InnoDB considera cada NULL distinto de cualquier otro dentro de un índice
 * único. Dos restricciones del esquema quedaban por eso desactivadas en la
 * práctica, justo en el caso que más importaba:
 *
 *   - mallas_externas: con `version` NULL se podía cargar N veces la misma
 *     malla de la misma carrera y el mismo año.
 *   - cursos_no_convalidables: con `carrera_id` NULL (regla institucional) se
 *     podía repetir indefinidamente la misma palabra clave.
 *
 * Se resuelven distinto porque el problema es distinto. En mallas externas la
 * versión sí tiene un valor por defecto sensato ('1'), así que basta con
 * volverla obligatoria. En no convalidables el NULL es información —significa
 * "institucional"— y no se puede eliminar: se añade una columna generada que
 * lo proyecta a 0 y el índice se construye sobre ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE mallas_externas SET version = '1' WHERE version IS NULL");
        DB::statement("ALTER TABLE mallas_externas MODIFY version VARCHAR(20) NOT NULL DEFAULT '1'");

        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->dropUnique('uq_no_convalidable_clave_carrera');
        });

        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->unsignedBigInteger('carrera_key')
                ->storedAs('IFNULL(carrera_id, 0)')->after('carrera_id');
        });

        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->unique(['clave_normalizada', 'carrera_key'], 'uq_no_convalidable_clave_carrera');
        });
    }

    public function down(): void
    {
        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->dropUnique('uq_no_convalidable_clave_carrera');
        });

        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->dropColumn('carrera_key');
        });

        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->unique(['clave_normalizada', 'carrera_id'], 'uq_no_convalidable_clave_carrera');
        });

        DB::statement('ALTER TABLE mallas_externas MODIFY version VARCHAR(255) NULL');
    }
};
```

- [ ] **Step 4: Correr las pruebas nuevas**

```bash
php artisan test --filter=IntegridadEsquemaTest
```

Expected: PASS (6 passed).

- [ ] **Step 5: Verificar el rollback**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

Expected: sin error. *(Si MySQL rechaza el `DROP COLUMN` de la columna generada por estar indexada, el orden de las tres llamadas `Schema::table` del `down()` está mal: el índice va primero.)*

- [ ] **Step 6: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_13_000004_cierra_agujeros_de_unicidad_con_null.php tests/Feature/IntegridadEsquemaTest.php
git commit -m "fix(bd): dos indices unicos no restringian nada por culpa de columnas NULL"
```

---

### Task 6: Unicidad faltante en catálogos

`carreras_externas` tiene 5.086 filas y `instituciones_externas` 206, sin ninguna restricción que impida duplicarlas. `cursos_usil` no tiene unicidad de código. Verificado: **hoy no hay duplicados**, así que las tres restricciones aplican sin saneo previo.

**Files:**
- Create: `database/migrations/2026_08_13_000005_unicidad_en_catalogos.php`
- Modify: `tests/Feature/IntegridadEsquemaTest.php`

- [ ] **Step 1: Escribir las pruebas que fallan**

Añadir a `IntegridadEsquemaTest`:

```php
    /** El catálogo SUNEDU se recarga periódicamente: sin unicidad, cada recarga
     *  fallida a medias deja instituciones repetidas que el usuario debe distinguir a ojo. */
    public function test_no_se_repite_una_institucion_externa(): void
    {
        $tipo = \App\Models\TipoInstitucion::create(['nombre' => 'Universidad']);
        $datos = ['tipo_id' => $tipo->id, 'nombre' => 'Universidad Nacional', 'pais' => 'Perú'];
        \App\Models\InstitucionExterna::create($datos);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\InstitucionExterna::create($datos);
    }

    /** Ni una carrera dentro de la misma institución. */
    public function test_no_se_repite_una_carrera_externa_en_la_misma_institucion(): void
    {
        $institucion = \App\Models\InstitucionExterna::create([
            'tipo_id' => \App\Models\TipoInstitucion::create(['nombre' => 'Instituto'])->id,
            'nombre' => 'Instituto Único', 'pais' => 'Perú',
        ]);
        $datos = ['institucion_id' => $institucion->id, 'nombre' => 'Contabilidad'];
        \App\Models\CarreraExterna::create($datos);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\CarreraExterna::create($datos);
    }
```

- [ ] **Step 2: Correrlas y verificar que fallan**

```bash
php artisan test --filter="test_no_se_repite_una_institucion|test_no_se_repite_una_carrera_externa"
```

Expected: 2 FAIL — no se lanzó `QueryException`.

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_08_13_000005_unicidad_en_catalogos.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los tres catálogos de mayor volumen no tenían clave natural declarada. Se
 * cargan por importación —SUNEDU, Excel de mallas—, y una importación que se
 * corta a la mitad y se reintenta duplica filas en silencio. El usuario se
 * encuentra después dos instituciones con el mismo nombre y ninguna forma de
 * saber cuál tiene los datos buenos.
 *
 * Verificado antes de aplicar: hoy no hay duplicados en ninguna de las tres.
 *
 * cursos_usil se restringe por (ciclo_id, codigo) y no por malla, porque hoy el
 * curso no conoce su malla: llega a ella por el ciclo. La Fase 2 propaga
 * plan_estudio_id hasta el curso y eleva la restricción a su nivel correcto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituciones_externas', function (Blueprint $table) {
            $table->unique(['nombre', 'pais'], 'uq_institucion_nombre_pais');
        });

        Schema::table('carreras_externas', function (Blueprint $table) {
            $table->unique(['institucion_id', 'nombre'], 'uq_carrera_externa_institucion_nombre');
        });

        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->unique(['ciclo_id', 'codigo'], 'uq_curso_usil_ciclo_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->dropUnique('uq_curso_usil_ciclo_codigo');
        });

        Schema::table('carreras_externas', function (Blueprint $table) {
            $table->dropUnique('uq_carrera_externa_institucion_nombre');
        });

        Schema::table('instituciones_externas', function (Blueprint $table) {
            $table->dropUnique('uq_institucion_nombre_pais');
        });
    }
};
```

- [ ] **Step 4: Correr las pruebas nuevas**

```bash
php artisan test --filter=IntegridadEsquemaTest
```

Expected: PASS (8 passed).

- [ ] **Step 5: Confirmar que la base de desarrollo real acepta las restricciones**

```bash
php artisan migrate
```

Expected: `DONE`. Si falla con `Duplicate entry`, hay datos sucios aparecidos después de la medición: lístalos antes de forzar nada.

- [ ] **Step 6: Verificar el rollback**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

- [ ] **Step 7: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_13_000005_unicidad_en_catalogos.php tests/Feature/IntegridadEsquemaTest.php
git commit -m "fix(bd): claves naturales en instituciones, carreras externas y cursos"
```

---

### Task 7: Una sola malla activa por carrera

Nada impide hoy que dos mallas de la misma carrera tengan `activa = 1`. `ConvalidacionEngine::mallaDeCarrera()` devuelve entonces una malla no determinista, y la simulación se calcula contra un plan de estudios arbitrario.

El índice `mallas_curriculares_carrera_id_anio_version_activa_unica_unique` **no** cubre esto pese al nombre: `activa_unica` solo excluye las filas con borrado lógico.

**Files:**
- Create: `database/migrations/2026_08_13_000006_una_sola_malla_activa_por_carrera.php`
- Modify: `tests/Feature/IntegridadEsquemaTest.php`

- [ ] **Step 1: Escribir la prueba que falla**

Añadir a `IntegridadEsquemaTest`:

```php
    /**
     * La simulación se calcula contra "la malla activa de la carrera". Si hay dos,
     * el motor elige la que devuelva primero el optimizador y dos postulantes de
     * la misma carrera pueden convalidarse contra planes distintos.
     */
    public function test_una_carrera_no_puede_tener_dos_mallas_activas(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $usuario = \App\Models\User::create([
            'nombre' => 'Admin', 'email' => uniqid().'@usil.edu.pe',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('x'),
            'rol_id' => \App\Models\Role::where('nombre', \App\Models\Role::SUPERUSUARIO)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
        $unidad = \App\Models\UnidadNegocio::create(['nombre' => 'Sede Central', 'codigo' => 'SC']);
        $facultad = \App\Models\Facultad::create(['unidad_negocio_id' => $unidad->id, 'nombre' => 'Ingeniería', 'codigo' => 'ING']);
        $carrera = \App\Models\Carrera::create(['facultad_id' => $facultad->id, 'nombre' => 'Civil', 'codigo' => 'CIV']);

        $base = ['carrera_id' => $carrera->id, 'origen_carga' => 'manual', 'usuario_id' => $usuario->id, 'activa' => true];
        \App\Models\MallaCurricular::create($base + ['anio' => 2025, 'version' => 'A']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\MallaCurricular::create($base + ['anio' => 2026, 'version' => 'B']);
    }
```

- [ ] **Step 2: Correrla y verificar que falla**

```bash
php artisan test --filter=test_una_carrera_no_puede_tener_dos_mallas_activas
```

Expected: FAIL — no se lanzó `QueryException`.

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_08_13_000006_una_sola_malla_activa_por_carrera.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `activa` era un booleano suelto: nada impedía marcar dos mallas de la misma
 * carrera a la vez. ConvalidacionEngine::mallaDeCarrera() hace un first() sobre
 * ese filtro, así que con dos activas el resultado depende del plan de ejecución
 * de MySQL. Dos postulantes de la misma carrera podían convalidarse contra
 * mallas distintas sin que nadie lo notara.
 *
 * Mismo recurso que ya usa `activa_unica` en esta tabla: una columna generada
 * que vale 1 solo cuando la fila cuenta (activa y no borrada) y NULL cuando no.
 * Como los NULL no chocan entre sí, el índice único solo compara las activas.
 *
 * Ojo con el nombre parecido: `activa_unica` NO controla `activa`, solo excluye
 * las borradas del índice de la clave natural. Son dos cosas distintas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->boolean('vigente_flag')
                ->nullable()
                ->virtualAs('IF(activa = 1 AND deleted_at IS NULL, 1, NULL)')
                ->after('activa_unica');
        });

        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->unique(['carrera_id', 'vigente_flag'], 'uq_malla_vigente_por_carrera');
        });
    }

    public function down(): void
    {
        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->dropUnique('uq_malla_vigente_por_carrera');
        });

        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->dropColumn('vigente_flag');
        });
    }
};
```

- [ ] **Step 4: Correr la prueba nueva**

```bash
php artisan test --filter=IntegridadEsquemaTest
```

Expected: PASS (9 passed).

- [ ] **Step 5: Comprobar que la base de desarrollo la acepta**

```bash
php artisan migrate
```

Expected: `DONE`. Verificado antes: 0 carreras con más de una malla activa.

- [ ] **Step 6: Verificar el rollback**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

- [ ] **Step 7: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

**Atención en este paso:** si alguna prueba existente crea dos mallas activas de la misma carrera, ahora fallará. Es un fallo **legítimo**: corrige la prueba marcando una sola como activa, no relajes la restricción.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_13_000006_una_sola_malla_activa_por_carrera.php tests/Feature/IntegridadEsquemaTest.php
git commit -m "fix(bd): impide dos mallas activas en la misma carrera"
```

---

### Task 8: Clave primaria compuesta en las tablas puente

`permisos_carrera` y `permisos_facultad` son puentes puros —dos FK, cero atributos— con `id` sustituto **más** un UNIQUE sobre el par. El `id` no aporta nada y crea un segundo identificador para la misma fila. `rol_permiso`, en el mismo esquema, ya lo hace bien: es el precedente.

**Files:**
- Create: `database/migrations/2026_08_13_000007_clave_primaria_compuesta_en_puentes.php`
- Modify: `tests/Feature/IntegridadEsquemaTest.php`

- [ ] **Step 1: Escribir la prueba que falla**

Añadir a `IntegridadEsquemaTest`:

```php
    /** Un puente puro se identifica por su par, no por un autonumérico añadido. */
    public function test_las_tablas_puente_de_alcance_no_tienen_id_sustituto(): void
    {
        foreach (['permisos_carrera', 'permisos_facultad'] as $tabla) {
            $this->assertFalse(
                Schema::hasColumn($tabla, 'id'),
                "{$tabla} es un puente puro: su clave es el par, no un id aparte."
            );
        }
    }
```

- [ ] **Step 2: Correrla y verificar que falla**

```bash
php artisan test --filter=test_las_tablas_puente_de_alcance
```

Expected: FAIL — `permisos_carrera es un puente puro: su clave es el par, no un id aparte.`

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_08_13_000007_clave_primaria_compuesta_en_puentes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las dos tablas de alcance son puentes puros: solo relacionan un usuario con
 * una carrera o una facultad, sin atributos propios. Llevaban un `id`
 * autonumérico y además un UNIQUE sobre el par, o sea dos identificadores para
 * la misma fila y un índice de más en cada escritura.
 *
 * rol_permiso, en este mismo esquema, ya usa clave primaria compuesta. Estas dos
 * eran la desviación, no la norma.
 *
 * El orden importa: MySQL exige que una columna AUTO_INCREMENT sea clave, así
 * que primero hay que quitarle el AUTO_INCREMENT y solo después soltar la clave
 * primaria y la columna. Hacerlo al revés falla con errno 150.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'permisos_carrera' => ['carrera_id', 'permisos_carrera_usuario_id_carrera_id_unique'],
            'permisos_facultad' => ['facultad_id', 'permisos_facultad_usuario_id_facultad_id_unique'],
        ] as $tabla => [$columna, $indiceUnico]) {
            DB::statement("ALTER TABLE `{$tabla}` MODIFY `id` BIGINT UNSIGNED NOT NULL");
            DB::statement("ALTER TABLE `{$tabla}` DROP PRIMARY KEY, DROP COLUMN `id`");
            DB::statement("ALTER TABLE `{$tabla}` ADD PRIMARY KEY (`usuario_id`, `{$columna}`)");
            DB::statement("ALTER TABLE `{$tabla}` DROP INDEX `{$indiceUnico}`");
        }
    }

    public function down(): void
    {
        foreach ([
            'permisos_carrera' => ['carrera_id', 'permisos_carrera_usuario_id_carrera_id_unique'],
            'permisos_facultad' => ['facultad_id', 'permisos_facultad_usuario_id_facultad_id_unique'],
        ] as $tabla => [$columna, $indiceUnico]) {
            DB::statement("ALTER TABLE `{$tabla}` ADD UNIQUE `{$indiceUnico}` (`usuario_id`, `{$columna}`)");
            DB::statement("ALTER TABLE `{$tabla}` DROP PRIMARY KEY");
            DB::statement("ALTER TABLE `{$tabla}` ADD `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
        }
    }
};
```

- [ ] **Step 4: Correr la prueba nueva**

```bash
php artisan test --filter=IntegridadEsquemaTest
```

Expected: PASS (10 passed).

- [ ] **Step 5: Verificar el rollback**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

Expected: sin error. *(Este `down()` es el más frágil del plan por el baile de claves primarias: si falla, es la señal de que el orden de sentencias necesita ajuste, no de que la tarea esté mal.)*

- [ ] **Step 6: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

**Atención:** `RbacTest` y `AlcanceService` usan estas tablas. Si alguna prueba lee `permisos_carrera.id`, fallará. Es fallo legítimo: reescribe la lectura por el par.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_13_000007_clave_primaria_compuesta_en_puentes.php tests/Feature/IntegridadEsquemaTest.php
git commit -m "refactor(bd): clave compuesta en los puentes de alcance, como rol_permiso"
```

---

### Task 9: `clave_normalizada` derivada de verdad, y collation uniforme

Dos cierres pequeños de la Fase 1.

`cursos_no_convalidables.clave_normalizada` se calcula desde `palabra_clave` con `ConvalidacionEngine::normaliza()` (minúsculas, sin acentos, sin puntuación). Como está en `$fillable`, la aplicación puede escribir un valor que no corresponda a su origen — y `SimulacionController.php:597` la escribe a mano.

El esquema declara `utf8mb4_0900_ai_ci` por defecto pero todas las tablas usan `utf8mb4_unicode_ci`: cualquier tabla futura creada sin collation explícito divergirá, y un JOIN por texto entre collations distintas falla o pierde el índice.

**Files:**
- Create: `database/migrations/2026_08_13_000008_collation_uniforme.php`
- Modify: `app/Models/CursoNoConvalidable.php:20`
- Modify: `app/Http/Controllers/SimulacionController.php:597`
- Modify: `tests/Feature/IntegridadEsquemaTest.php`

- [ ] **Step 1: Escribir la prueba que falla**

Añadir a `IntegridadEsquemaTest`:

```php
    /** La clave normalizada es un derivado de la palabra clave: el modelo la
     *  recalcula siempre, aunque alguien intente pasarla desde fuera. */
    public function test_la_clave_normalizada_no_se_puede_escribir_a_mano(): void
    {
        $regla = \App\Models\CursoNoConvalidable::create([
            'carrera_id' => null,
            'palabra_clave' => 'Educación Física',
            'clave_normalizada' => 'valor-inventado',
            'motivo' => 'Prueba',
        ]);

        $this->assertSame('educacion fisica', $regla->fresh()->clave_normalizada);
    }
```

- [ ] **Step 2: Correrla y verificar que falla**

```bash
php artisan test --filter=test_la_clave_normalizada_no_se_puede_escribir
```

Expected: FAIL — se esperaba `'educacion fisica'` y se obtuvo `'valor-inventado'`.

- [ ] **Step 3: Blindar el modelo**

En `app/Models/CursoNoConvalidable.php`, cambiar la línea 20:

```php
    protected $fillable = ['carrera_id', 'palabra_clave', 'motivo', 'activo'];
```

Y añadir dentro de la clase:

```php
    /**
     * La clave normalizada es una proyección de la palabra clave, no un dato
     * aparte: se recalcula en cada guardado. Antes venía en $fillable y el
     * llamador podía dejarla desalineada de su origen, con lo que la regla
     * dejaba de coincidir con nada.
     */
    protected static function booted(): void
    {
        static::saving(function (self $regla) {
            $regla->clave_normalizada = app(\App\Services\ConvalidacionEngine::class)
                ->normaliza($regla->palabra_clave);
        });
    }
```

- [ ] **Step 4: Quitar la escritura manual del controlador**

En `app/Http/Controllers/SimulacionController.php:597`, la llamada `firstOrCreate` pasa `clave_normalizada` en su primer array. Quitar esa clave y dejar que el observer la calcule; el `firstOrCreate` debe buscar por `palabra_clave` y `carrera_id`.

- [ ] **Step 5: Escribir la migración de collation**

Crear `database/migrations/2026_08_13_000008_collation_uniforme.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El esquema declaraba utf8mb4_0900_ai_ci por defecto mientras las 26 tablas
 * usan utf8mb4_unicode_ci. Las tablas existentes están alineadas entre sí, así
 * que hoy no falla nada; el problema es la próxima tabla que alguien cree sin
 * collation explícito: nacerá con la otra y el primer JOIN por texto contra una
 * tabla vieja fallará con "Illegal mix of collations", o resolverá pero sin
 * poder usar el índice.
 *
 * Se alinea el DEFAULT del esquema con lo que las tablas ya son. No se
 * convierten las tablas: eso reescribiría cada índice de texto sin ganar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER DATABASE `'.DB::getDatabaseName().'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        DB::statement('ALTER DATABASE `'.DB::getDatabaseName().'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
    }
};
```

- [ ] **Step 6: Correr la prueba nueva**

```bash
php artisan test --filter=IntegridadEsquemaTest
```

Expected: PASS (11 passed).

- [ ] **Step 7: Verificar el rollback**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

- [ ] **Step 8: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

**Atención:** `NoConvalidablesPorCarreraTest` crea reglas pasando `clave_normalizada` explícita (línea 125 y similares). Esas 7 pruebas **ya están en la línea base como fallidas por 404**, así que no cambian de estado. Cuando se reparen las rutas, habrá que quitarles ese campo.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_13_000008_collation_uniforme.php app/Models/CursoNoConvalidable.php app/Http/Controllers/SimulacionController.php tests/Feature/IntegridadEsquemaTest.php
git commit -m "fix(bd): la clave normalizada se deriva siempre y el collation del esquema se alinea"
```

---

## Cierre de Fase 1

- [ ] **Verificación de instalación limpia** — es lo que TI va a ejecutar:

```bash
php artisan migrate:fresh --seed
```

Expected: todas las migraciones aplican y todos los seeders corren sin error. **Si un seeder choca contra una restricción nueva, el seeder tenía un defecto que la base ahora expone.** Corrígelo ahí, no relajes la restricción.

- [ ] **Recuento final:** 11 pruebas nuevas en `IntegridadEsquemaTest`, los mismos 18 fallos de línea base, `171 + 11 = 182` en verde.

**Lo que Fase 1 deja resuelto:** INT-1 a INT-9 completos, 3FN-7, y los `id` sustitutos de §2.4. Los nueve defectos de integridad reales del reporte de auditoría. **Sin tocar el frontend ni una sola ruta.**

---

# FASE 2 — Normalización estructural

Cinco tareas, ordenadas de menor a mayor riesgo. Cada una es entregable por separado.

**Precondición:** Fase 1 completa y confirmada.

> Las tareas 10 y 11 corrigen violaciones de **1FN**, que son las que un auditor señala primero porque se ven a simple vista. Las tareas 12 a 14 corrigen las **3FN** y eliminan las FK redundantes.

---

### Task 10: `competencias` deja de ser un JSON con comas

Violación de 1FN. `MallaController.php:426` hace literalmente `explode(',', $datos['competencias'])`: es una lista separada por comas guardada en una celda.

**Files:**
- Create: `database/migrations/2026_08_14_000001_competencias_como_tabla.php`
- Create: `app/Models/Competencia.php`
- Create: `tests/Feature/NormalizacionEstructuralTest.php`
- Modify: `app/Models/CursoUsil.php:19-27`
- Modify: `app/Http/Controllers/MallaController.php:268,417,426-427`
- Modify: `resources/js/Pages/Mallas/Show.vue`, `resources/js/Pages/Mallas/Form.vue`

**Interfaces:**
- Produces: `App\Models\Competencia` con `$fillable = ['nombre']`; `CursoUsil::competencias()` pasa de atributo casteado a `belongsToMany`.

- [ ] **Step 1: Escribir la prueba que falla**

Crear `tests/Feature/NormalizacionEstructuralTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 2: forma de las tablas después de normalizar.
 * Comprueba que los datos multivaluados dejaron de vivir en una celda y que las
 * llaves redundantes ya no pueden contradecir a su origen.
 */
class NormalizacionEstructuralTest extends TestCase
{
    use RefreshDatabase;

    /** Una competencia es una entidad del currículo: se cuenta, se busca y se
     *  comparte entre cursos. En un JSON con comas no se puede hacer ninguna. */
    public function test_las_competencias_son_filas_y_no_una_celda(): void
    {
        $this->assertFalse(Schema::hasColumn('cursos_usil', 'competencias'),
            'cursos_usil.competencias seguía siendo un JSON multivaluado.');
        $this->assertTrue(Schema::hasTable('competencias'));
        $this->assertTrue(Schema::hasTable('curso_competencia'));
    }
}
```

- [ ] **Step 2: Correrla y verificar que falla**

```bash
php artisan test --filter=test_las_competencias_son_filas
```

Expected: FAIL — `cursos_usil.competencias seguía siendo un JSON multivaluado.`

- [ ] **Step 3: Escribir la migración con traspaso de datos**

Crear `database/migrations/2026_08_14_000001_competencias_como_tabla.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las competencias de un curso son varias, y se guardaban como un JSON que la
 * aplicación construía con explode(',') sobre un campo de texto. Eso impide
 * contar cuántos cursos desarrollan una competencia, renombrarla en un sitio, o
 * buscar por ella: todas las preguntas que un currículo necesita responder.
 *
 * El traspaso conserva lo que haya: cada elemento del JSON se convierte en fila
 * del catálogo (sin repetir) y en una asociación con su curso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencias', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('nombre', 150);
            $table->unique('nombre', 'uq_competencia_nombre');
        });

        Schema::create('curso_competencia', function (Blueprint $table) {
            $table->foreignId('curso_id')->constrained('cursos_usil')->cascadeOnDelete();
            $table->unsignedSmallInteger('competencia_id');
            $table->primary(['curso_id', 'competencia_id']);
            $table->foreign('competencia_id')->references('id')->on('competencias')->restrictOnDelete();
        });

        foreach (DB::table('cursos_usil')->whereNotNull('competencias')->get(['id', 'competencias']) as $curso) {
            foreach (json_decode($curso->competencias, true) ?: [] as $nombre) {
                $nombre = trim((string) $nombre);
                if ($nombre === '') {
                    continue;
                }
                $id = DB::table('competencias')->where('nombre', $nombre)->value('id')
                    ?? DB::table('competencias')->insertGetId(['nombre' => $nombre]);

                DB::table('curso_competencia')->insertOrIgnore([
                    'curso_id' => $curso->id, 'competencia_id' => $id,
                ]);
            }
        }

        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->dropColumn('competencias');
        });
    }

    public function down(): void
    {
        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->json('competencias')->nullable()->after('area');
        });

        foreach (DB::table('curso_competencia')
            ->join('competencias', 'competencias.id', '=', 'curso_competencia.competencia_id')
            ->select('curso_competencia.curso_id', 'competencias.nombre')->get()
            ->groupBy('curso_id') as $cursoId => $filas) {
            DB::table('cursos_usil')->where('id', $cursoId)
                ->update(['competencias' => $filas->pluck('nombre')->toJson()]);
        }

        Schema::dropIfExists('curso_competencia');
        Schema::dropIfExists('competencias');
    }
};
```

- [ ] **Step 4: Crear el modelo**

Crear `app/Models/Competencia.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Competencia del currículo. Se comparte entre cursos y entre mallas. */
class Competencia extends Model
{
    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(CursoUsil::class, 'curso_competencia', 'competencia_id', 'curso_id');
    }
}
```

- [ ] **Step 5: Actualizar `CursoUsil`**

En `app/Models/CursoUsil.php`: quitar `'competencias'` de `$fillable` (línea 20) y de `$casts` (línea 27), y añadir:

```php
    public function competencias(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Competencia::class, 'curso_competencia', 'curso_id', 'competencia_id');
    }
```

- [ ] **Step 6: Actualizar el controlador**

En `app/Http/Controllers/MallaController.php`:

Línea 268, la lectura pasa a leer nombres desde la relación:

```php
                    'competencias' => $cu->competencias->pluck('nombre')->all(),
```

Y añadir `'competencias'` al `->with([...])` de la consulta que carga los cursos, para no provocar una consulta por curso.

Líneas 426-427, la escritura pasa de construir un array a sincronizar el pivote:

```php
        $nombres = collect(explode(',', (string) ($datos['competencias'] ?? '')))
            ->map(fn ($n) => trim($n))->filter()->unique();
        unset($datos['competencias']);
```

y después de guardar el curso, con `$curso` ya persistido:

```php
        $curso->competencias()->sync(
            $nombres->map(fn ($n) => \App\Models\Competencia::firstOrCreate(['nombre' => $n])->id)->all()
        );
```

- [ ] **Step 7: Correr la prueba nueva**

```bash
php artisan test --filter=NormalizacionEstructuralTest
```

Expected: PASS (1 passed).

- [ ] **Step 8: Verificar el rollback con datos**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

Expected: sin error, y las competencias vuelven a su JSON y otra vez a tablas sin pérdida.

- [ ] **Step 9: Verificar la pantalla en el navegador**

Levantar el servidor y abrir la ficha de una malla; confirmar que las competencias se muestran y que al editarlas se guardan. Los dos archivos Vue (`Mallas/Show.vue`, `Mallas/Form.vue`) reciben la misma forma de dato (array de strings), así que **no deberían necesitar cambios**; verificarlo, no asumirlo.

- [ ] **Step 10: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

- [ ] **Step 11: Commit**

```bash
git add database/migrations/2026_08_14_000001_competencias_como_tabla.php app/Models/Competencia.php app/Models/CursoUsil.php app/Http/Controllers/MallaController.php tests/Feature/NormalizacionEstructuralTest.php
git commit -m "refactor(bd): las competencias del curso pasan de JSON con comas a tabla"
```

---

### Task 11: Prerrequisitos con su cardinalidad real

Un curso tiene *N* prerrequisitos; el modelo permite uno (`prerequisito_id`) y guarda el desbordamiento como texto libre (`prerequisito_texto`). `MallaImportController.php:206` lo documenta: *"conserva el texto original del archivo"* — porque la FK no alcanzaba.

**Files:**
- Create: `database/migrations/2026_08_14_000002_prerequisitos_como_tabla_puente.php`
- Modify: `app/Models/CursoUsil.php:19,37`
- Modify: `app/Http/Controllers/MallaController.php:263-264,420`
- Modify: `tests/Feature/NormalizacionEstructuralTest.php`

**Interfaces:**
- Consumes: `CursoUsil` de la Task 10.
- Produces: `CursoUsil::prerequisitos()` como `belongsToMany` sobre `curso_prerequisitos`.

- [ ] **Step 1: Escribir la prueba que falla**

Añadir a `NormalizacionEstructuralTest`:

```php
    /** Cálculo II puede exigir Cálculo I y Álgebra a la vez. Con una sola FK
     *  el segundo prerrequisito terminaba como texto suelto sin integridad. */
    public function test_un_curso_admite_varios_prerequisitos(): void
    {
        $this->assertTrue(Schema::hasTable('curso_prerequisitos'));
        $this->assertFalse(Schema::hasColumn('cursos_usil', 'prerequisito_id'),
            'cursos_usil.prerequisito_id imponía un solo prerrequisito por curso.');
    }
```

- [ ] **Step 2: Correrla y verificar que falla**

```bash
php artisan test --filter=test_un_curso_admite_varios_prerequisitos
```

Expected: FAIL — la tabla `curso_prerequisitos` no existe.

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_08_14_000002_prerequisitos_como_tabla_puente.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un curso puede exigir varios prerrequisitos —Cálculo II pide Cálculo I y
 * Álgebra Lineal—, pero el modelo solo tenía sitio para uno. El resto se
 * guardaba en prerequisito_texto, sin FK y sin forma de validarlo.
 *
 * prerequisito_texto SE CONSERVA a propósito: durante la importación de un
 * Excel llega texto que todavía no se ha podido resolver a cursos concretos
 * (nombres que no coinciden, cursos de otra malla). Deja de ser el sitio donde
 * viven los prerrequisitos y pasa a ser lo que siempre debió ser: la materia
 * prima sin resolver de la importación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curso_prerequisitos', function (Blueprint $table) {
            $table->foreignId('curso_id')->constrained('cursos_usil')->cascadeOnDelete();
            $table->foreignId('prerequisito_id')->constrained('cursos_usil')->cascadeOnDelete();
            $table->primary(['curso_id', 'prerequisito_id']);
            $table->index('prerequisito_id', 'ix_prereq_inverso');
        });

        DB::statement('ALTER TABLE curso_prerequisitos
            ADD CONSTRAINT ck_prereq_no_autoref CHECK (curso_id <> prerequisito_id)');

        DB::statement('INSERT INTO curso_prerequisitos (curso_id, prerequisito_id)
            SELECT id, prerequisito_id FROM cursos_usil
            WHERE prerequisito_id IS NOT NULL AND prerequisito_id <> id');

        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->dropForeign(['prerequisito_id']);
            $table->dropColumn('prerequisito_id');
        });
    }

    public function down(): void
    {
        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->foreignId('prerequisito_id')->nullable()->after('convalidable')
                ->constrained('cursos_usil');
        });

        // Solo se puede devolver uno por curso: se conserva el de menor id.
        DB::statement('UPDATE cursos_usil c
            SET prerequisito_id = (SELECT MIN(prerequisito_id) FROM curso_prerequisitos p WHERE p.curso_id = c.id)');

        Schema::dropIfExists('curso_prerequisitos');
    }
};
```

- [ ] **Step 4: Actualizar el modelo**

En `app/Models/CursoUsil.php`, quitar `'prerequisito_id'` de `$fillable` (línea 19) y reemplazar el `belongsTo` de la línea 37 por:

```php
    /** Prerrequisitos ya resueltos a cursos. Lo que la importación no pudo
     *  resolver sigue en prerequisito_texto hasta que alguien lo revise. */
    public function prerequisitos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(self::class, 'curso_prerequisitos', 'curso_id', 'prerequisito_id');
    }
```

- [ ] **Step 5: Actualizar el controlador**

En `app/Http/Controllers/MallaController.php` líneas 263-264:

```php
                    'prerequisitos' => $cu->prerequisitos->pluck('nombre')->all(),
                    'prerequisito_texto' => $cu->prerequisito_texto,
```

Añadir `'prerequisitos'` al `->with([...])` de la consulta padre.

Línea 420: la regla `Rule::notIn([$cursoId])` ya no aplica a una sola columna. Cambiar la validación a un array y dejar que el `CHECK` de la base sostenga la no autorreferencia:

```php
            'prerequisito_ids' => ['nullable', 'array'],
            'prerequisito_ids.*' => ['integer', 'exists:cursos_usil,id',
                $cursoId ? Rule::notIn([$cursoId]) : 'nullable'],
```

y tras guardar: `$curso->prerequisitos()->sync($datos['prerequisito_ids'] ?? []);`

- [ ] **Step 6: Correr la prueba nueva**

```bash
php artisan test --filter=NormalizacionEstructuralTest
```

Expected: PASS (2 passed).

- [ ] **Step 7: Verificar el rollback**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

- [ ] **Step 8: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

**Atención:** `MallaTest` y `MallaExternaPlantillaTest` tocan cursos. Los 6 fallos de `MallaExternaPlantillaTest` ya están en línea base; `MallaTest` está en verde y debe seguir.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_14_000002_prerequisitos_como_tabla_puente.php app/Models/CursoUsil.php app/Http/Controllers/MallaController.php tests/Feature/NormalizacionEstructuralTest.php
git commit -m "refactor(bd): un curso puede tener varios prerrequisitos, como en la realidad"
```

---

### Task 12: Mapeo de mallas con cabecera y FK compuestas

`equivalencias_malla` repite en **cada fila** el par (malla externa, malla USIL), el autor y las marcas de tiempo — datos que pertenecen al mapeo entero, no a cada curso (3FN-5 y 3FN-6). Además `malla_externa_id` y `malla_usil_id` son derivables de los cursos y **nada garantiza que coincidan**.

**Volumen de datos: 1 fila.** El traspaso es trivial.

**Files:**
- Create: `database/migrations/2026_08_14_000003_mapeos_de_malla.php`
- Create: `app/Models/MapeoMalla.php`, `app/Models/MapeoCurso.php`
- Delete: `app/Models/EquivalenciaMalla.php`
- Modify: `app/Http/Controllers/MapeoMallasController.php:40-57,109-122`
- Modify: `app/Http/Controllers/EquivalenciaController.php:27`
- Modify: `routes/web.php:253` (parámetro `{equivalenciaMalla}` → `{mapeoCurso}`)
- Modify: `tests/Feature/NormalizacionEstructuralTest.php`, `tests/Feature/MapeoMallasTest.php`

**Interfaces:**
- Produces: `MapeoMalla` (`$fillable = ['malla_externa_id','plan_estudio_id','creado_por_id']`, relación `cursos(): HasMany`) y `MapeoCurso` (`$fillable = ['mapeo_id','malla_externa_id','plan_estudio_id','curso_externo_id','curso_usil_id']`).

> **Dependencia de orden:** esta tarea usa `plan_estudio_id` como destino, que solo existe tras la Task 13. **Ejecutar la Task 13 ANTES que esta**, o usar `malla_usil_id` aquí y renombrarlo después. Recomendado: hacer la Task 13 primero y luego esta.

- [ ] **Step 1: Escribir la prueba que falla**

Añadir a `NormalizacionEstructuralTest`:

```php
    /** El par (malla externa, plan destino) es el mapeo: vive una vez en la
     *  cabecera, no repetido en cada uno de sus cursos. */
    public function test_el_mapeo_de_mallas_tiene_cabecera(): void
    {
        $this->assertTrue(Schema::hasTable('mapeos_malla'));
        $this->assertTrue(Schema::hasTable('mapeo_curso'));
        $this->assertFalse(Schema::hasTable('equivalencias_malla'));
    }

    /** Y la FK compuesta impide mapear un curso que no pertenece a esas mallas. */
    public function test_no_se_puede_mapear_un_curso_ajeno_a_la_malla(): void
    {
        $fks = \Illuminate\Support\Facades\DB::select(
            "SELECT CONSTRAINT_NAME n, COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mapeo_curso'
               AND REFERENCED_TABLE_NAME IS NOT NULL
             GROUP BY CONSTRAINT_NAME HAVING c > 1"
        );

        $this->assertGreaterThanOrEqual(4, count($fks),
            'mapeo_curso debe tener 4 FK compuestas que aten cada curso a su malla.');
    }
```

- [ ] **Step 2: Correrlas y verificar que fallan**

```bash
php artisan test --filter="test_el_mapeo_de_mallas_tiene_cabecera|test_no_se_puede_mapear_un_curso_ajeno"
```

Expected: 2 FAIL — la tabla `mapeos_malla` no existe.

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_08_14_000003_mapeos_de_malla.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un mapeo entre dos mallas es una entidad: tiene autor, fecha y un par de
 * mallas. Todo eso se guardaba repetido en cada una de sus N filas de curso, y
 * las dos referencias a las mallas eran derivables de los propios cursos sin
 * que nada obligara a que coincidieran: se podía mapear un curso de la malla A
 * dentro de un mapeo declarado sobre la malla B.
 *
 * La cabecera guarda el par una vez. En el detalle, malla_externa_id y
 * plan_estudio_id NO son datos independientes: son claves propagadas, y las
 * cuatro FK compuestas obligan a que el curso pertenezca exactamente a las
 * mallas que la cabecera declara. La contradicción deja de ser posible en vez
 * de ser solo improbable.
 *
 * Los índices UNIQUE (id, malla_externa_id) de las tablas padre no añaden
 * ninguna restricción —id ya es único— y existen solo para poder ser destino
 * de esas FK compuestas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE mallas_externas ADD UNIQUE uq_malla_ext_id_carrera (id, carrera_externa_id)');
        DB::statement('ALTER TABLE cursos_externos ADD UNIQUE uq_curso_ext_id_malla (id, malla_externa_id)');
        DB::statement('ALTER TABLE cursos_usil    ADD UNIQUE uq_curso_usil_id_plan (id, plan_estudio_id)');

        DB::statement('CREATE TABLE mapeos_malla (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            malla_externa_id BIGINT UNSIGNED NOT NULL,
            plan_estudio_id  BIGINT UNSIGNED NOT NULL,
            creado_por_id    BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_mapeo         (malla_externa_id, plan_estudio_id),
            UNIQUE KEY uq_mapeo_origen  (id, malla_externa_id),
            UNIQUE KEY uq_mapeo_destino (id, plan_estudio_id),
            KEY ix_mapeo_plan  (plan_estudio_id),
            KEY ix_mapeo_autor (creado_por_id),
            CONSTRAINT fk_mapeo_externa FOREIGN KEY (malla_externa_id) REFERENCES mallas_externas(id) ON DELETE CASCADE,
            CONSTRAINT fk_mapeo_plan    FOREIGN KEY (plan_estudio_id)  REFERENCES planes_estudio(id)  ON DELETE CASCADE,
            CONSTRAINT fk_mapeo_autor   FOREIGN KEY (creado_por_id)    REFERENCES usuarios(id)
        ) ENGINE=InnoDB');

        DB::statement('CREATE TABLE mapeo_curso (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mapeo_id         BIGINT UNSIGNED NOT NULL,
            malla_externa_id BIGINT UNSIGNED NOT NULL,
            plan_estudio_id  BIGINT UNSIGNED NOT NULL,
            curso_externo_id BIGINT UNSIGNED NOT NULL,
            curso_usil_id    BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_mc_externo (mapeo_id, curso_externo_id),
            UNIQUE KEY uq_mc_usil    (mapeo_id, curso_usil_id),
            KEY ix_mc_curso_usil (curso_usil_id),
            CONSTRAINT fk_mc_cab_origen  FOREIGN KEY (mapeo_id, malla_externa_id)
                REFERENCES mapeos_malla   (id, malla_externa_id) ON DELETE CASCADE,
            CONSTRAINT fk_mc_cab_destino FOREIGN KEY (mapeo_id, plan_estudio_id)
                REFERENCES mapeos_malla   (id, plan_estudio_id)  ON DELETE CASCADE,
            CONSTRAINT fk_mc_externo     FOREIGN KEY (curso_externo_id, malla_externa_id)
                REFERENCES cursos_externos(id, malla_externa_id) ON DELETE CASCADE,
            CONSTRAINT fk_mc_usil        FOREIGN KEY (curso_usil_id, plan_estudio_id)
                REFERENCES cursos_usil    (id, plan_estudio_id)  ON DELETE CASCADE
        ) ENGINE=InnoDB');

        // Traspaso (1 fila en desarrollo, pero escrito para N).
        foreach (DB::table('equivalencias_malla')->get() as $eq) {
            $mapeoId = DB::table('mapeos_malla')
                ->where('malla_externa_id', $eq->malla_externa_id)
                ->where('plan_estudio_id', $eq->malla_usil_id)
                ->value('id')
                ?? DB::table('mapeos_malla')->insertGetId([
                    'malla_externa_id' => $eq->malla_externa_id,
                    'plan_estudio_id' => $eq->malla_usil_id,
                    'creado_por_id' => $eq->usuario_id ?? DB::table('usuarios')->min('id'),
                    'created_at' => $eq->created_at, 'updated_at' => $eq->updated_at,
                ]);

            DB::table('mapeo_curso')->insertOrIgnore([
                'mapeo_id' => $mapeoId,
                'malla_externa_id' => $eq->malla_externa_id,
                'plan_estudio_id' => $eq->malla_usil_id,
                'curso_externo_id' => $eq->curso_externo_id,
                'curso_usil_id' => $eq->curso_usil_id,
                'created_at' => $eq->created_at, 'updated_at' => $eq->updated_at,
            ]);
        }

        Schema::dropIfExists('equivalencias_malla');
    }

    public function down(): void
    {
        DB::statement('CREATE TABLE equivalencias_malla (
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

        DB::statement('INSERT INTO equivalencias_malla
            (curso_externo_id, curso_usil_id, malla_externa_id, malla_usil_id, usuario_id, created_at, updated_at)
            SELECT mc.curso_externo_id, mc.curso_usil_id, mc.malla_externa_id, mc.plan_estudio_id,
                   m.creado_por_id, mc.created_at, mc.updated_at
            FROM mapeo_curso mc INNER JOIN mapeos_malla m ON m.id = mc.mapeo_id');

        Schema::dropIfExists('mapeo_curso');
        Schema::dropIfExists('mapeos_malla');

        DB::statement('ALTER TABLE cursos_usil    DROP INDEX uq_curso_usil_id_plan');
        DB::statement('ALTER TABLE cursos_externos DROP INDEX uq_curso_ext_id_malla');
        DB::statement('ALTER TABLE mallas_externas DROP INDEX uq_malla_ext_id_carrera');
    }
};
```

- [ ] **Step 4: Crear los modelos**

Crear `app/Models/MapeoMalla.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mapeo declarado entre una malla externa y un plan de estudios USIL. */
class MapeoMalla extends Model
{
    protected $table = 'mapeos_malla';

    protected $fillable = ['malla_externa_id', 'plan_estudio_id', 'creado_por_id'];

    public function cursos(): HasMany
    {
        return $this->hasMany(MapeoCurso::class, 'mapeo_id');
    }

    public function mallaExterna(): BelongsTo
    {
        return $this->belongsTo(MallaExterna::class, 'malla_externa_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class, 'plan_estudio_id');
    }
}
```

Crear `app/Models/MapeoCurso.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un par curso externo ↔ curso USIL dentro de un mapeo.
 *
 * malla_externa_id y plan_estudio_id se copian de la cabecera al insertar: no
 * son datos que elija el llamador, son lo que exigen las FK compuestas para
 * poder verificar que ambos cursos pertenecen a las mallas mapeadas.
 */
class MapeoCurso extends Model
{
    protected $table = 'mapeo_curso';

    protected $fillable = [
        'mapeo_id', 'malla_externa_id', 'plan_estudio_id',
        'curso_externo_id', 'curso_usil_id',
    ];

    public function mapeo(): BelongsTo
    {
        return $this->belongsTo(MapeoMalla::class, 'mapeo_id');
    }

    public function cursoExterno(): BelongsTo
    {
        return $this->belongsTo(CursoExterno::class, 'curso_externo_id');
    }

    public function cursoUsil(): BelongsTo
    {
        return $this->belongsTo(CursoUsil::class, 'curso_usil_id');
    }
}
```

Borrar `app/Models/EquivalenciaMalla.php`.

- [ ] **Step 5: Simplificar el controlador**

En `app/Http/Controllers/MapeoMallasController.php`, el listado de las líneas 40-57 reconstruía por JOIN un agrupamiento que ahora es una tabla. Reemplazar por:

```php
        $mapeos = MapeoMalla::query()
            ->withCount('cursos')
            ->with(['mallaExterna.carreraExterna.institucion', 'plan.carrera'])
            ->when($visibles !== null, fn ($q) => $q->whereHas('plan',
                fn ($p) => $p->whereIn('carrera_id', $visibles ?: [0])))
            ->get();
```

Desaparecen tres JOIN y el `GROUP BY`. En el `store` (líneas 109-122), crear la cabecera y propagar sus claves al detalle:

```php
        $mapeo = MapeoMalla::firstOrCreate(
            ['malla_externa_id' => $datos['malla_externa_id'], 'plan_estudio_id' => $plan->id],
            ['creado_por_id' => $request->user()->id],
        );

        $mapeo->cursos()->createMany(collect($datos['pares'])->map(fn ($par) => [
            'malla_externa_id' => $mapeo->malla_externa_id,
            'plan_estudio_id' => $mapeo->plan_estudio_id,
            'curso_externo_id' => $par['curso_externo_id'],
            'curso_usil_id' => $par['curso_usil_id'],
        ])->all());
```

- [ ] **Step 6: Correr las pruebas nuevas**

```bash
php artisan test --filter=NormalizacionEstructuralTest
```

Expected: PASS (4 passed).

- [ ] **Step 7: Actualizar `MapeoMallasTest`**

Sus 10 pruebas están **en verde** en la línea base y usan `EquivalenciaMalla`. Reescribirlas contra `MapeoMalla`/`MapeoCurso` conservando cada aserción de negocio. Debe seguir habiendo 10 en verde al terminar.

- [ ] **Step 8: Verificar el rollback**

```bash
php artisan migrate:rollback --step=1 && php artisan migrate
```

- [ ] **Step 9: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

- [ ] **Step 10: Commit**

```bash
git add -A app/Models database/migrations/2026_08_14_000003_mapeos_de_malla.php app/Http/Controllers/MapeoMallasController.php app/Http/Controllers/EquivalenciaController.php routes/web.php tests/Feature/
git commit -m "refactor(bd): el mapeo de mallas gana cabecera y FK compuestas que lo hacen consistente"
```

---

### Task 13: Fusionar `planes_estudio` y `mallas_curriculares`

La violación más grave del reporte (3FN-1 y 2FN-1). Ambas tablas se identifican por `(carrera_id, anio, version)` —las dos declaran ese UNIQUE— y `mallas_curriculares` repite además `modalidad` como ENUM cuando existe la tabla `modalidades`.

**`planes_estudio` tiene 0 filas: nunca se insertó un registro.** `mallas_curriculares` tiene 2. El traspaso es de dos filas; el trabajo es de código.

**Files:**
- Create: `database/migrations/2026_08_14_000004_fusiona_planes_y_mallas.php`
- Delete: `app/Models/MallaCurricular.php`
- Modify: `app/Models/PlanEstudio.php`, `app/Models/Ciclo.php:27`, `app/Models/CargaMasiva.php`
- Modify: `app/Http/Controllers/MallaController.php`, `MallaImportController.php`, `SimulacionController.php:524,573,814`, `MapeoMallasController.php:115`
- Modify: `app/Services/ConvalidacionEngine.php` (`mallaDeCarrera()`)
- Modify: `database/seeders/UsilPregradoSeeder.php`, `CargarPlanEstudiosJsonSeeder.php`, `EstructuraSeeder.php`
- Modify: `tests/Feature/NormalizacionEstructuralTest.php` y toda prueba que use `MallaCurricular`

**Interfaces:**
- Produces: `PlanEstudio` absorbe `origen_carga`, `creado_por_id`, `activo`, `deleted_at` y la relación `ciclos()`. `Ciclo::plan()` sustituye a `Ciclo::malla()`.

> **Esta es la tarea grande.** Si el tiempo aprieta, es legítimo parar antes de empezarla: las tareas 10-12 ya entregan un esquema estrictamente mejor.

- [ ] **Step 1: Escribir la prueba que falla**

Añadir a `NormalizacionEstructuralTest`:

```php
    /** Plan de estudios y malla curricular eran la misma entidad partida en dos:
     *  las dos tablas se identificaban por (carrera, año, versión). */
    public function test_el_plan_de_estudios_es_una_sola_tabla(): void
    {
        $this->assertFalse(Schema::hasTable('mallas_curriculares'));
        $this->assertTrue(Schema::hasColumn('planes_estudio', 'origen_carga'));
        $this->assertTrue(Schema::hasColumn('ciclos', 'plan_estudio_id'));
        $this->assertFalse(Schema::hasColumn('ciclos', 'malla_id'));
    }
```

- [ ] **Step 2: Correrla y verificar que falla**

```bash
php artisan test --filter=test_el_plan_de_estudios_es_una_sola_tabla
```

Expected: FAIL — la tabla `mallas_curriculares` sigue existiendo.

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_08_14_000004_fusiona_planes_y_mallas.php`. Estructura en cinco bloques, en este orden exacto:

1. Añadir a `planes_estudio` las columnas que solo tenía `mallas_curriculares`: `origen_carga ENUM('manual','excel') NOT NULL DEFAULT 'manual'`, `creado_por_id BIGINT UNSIGNED NULL`, `periodo VARCHAR(10) NULL`, `deleted_at TIMESTAMP NULL`, y las generadas `vivo_flag` y `vigente_flag`.
2. Insertar en `planes_estudio` una fila por cada `mallas_curriculares`, resolviendo `modalidad_id` desde el ENUM `modalidad` contra la tabla `modalidades`, y generando `codigo` como `CONCAT(c.codigo,'-',m.anio,'-',m.version)`. Guardar el mapeo `malla_id → plan_id` en una tabla temporal.
3. Repuntar las FK: `ciclos.malla_id → plan_estudio_id`, `cargas_masivas.malla_id → plan_estudio_id`, `simulaciones.malla_usil_id → plan_estudio_id`.
4. Añadir `UNIQUE (id, carrera_id)` a `planes_estudio` (destino de las FK compuestas de las tareas 12 y 14) y `UNIQUE (carrera_id, modalidad_id, vigente_flag)`.
5. `DROP TABLE mallas_curriculares`.

El `down()` recorre los cinco bloques al revés.

- [ ] **Step 4: Renombrar el modelo y repuntar los llamadores**

`MallaCurricular` desaparece; `PlanEstudio` absorbe sus relaciones. Reescrituras tipo:

```php
// ANTES
MallaCurricular::where('carrera_id', $id)->where('activa', 1)->whereNull('deleted_at')->first();
Ciclo::where('malla_id', $malla->id)->get();

// DESPUÉS — el UNIQUE parcial de la Task 7 garantiza que hay como máximo una
PlanEstudio::where('carrera_id', $id)->where('activo', 1)->first();
Ciclo::where('plan_estudio_id', $plan->id)->get();
```

Buscar todos los usos con:

```bash
grep -rn "MallaCurricular\|malla_id\|malla_usil_id" app database tests resources/js
```

- [ ] **Step 5: Correr la prueba nueva**

```bash
php artisan test --filter=test_el_plan_de_estudios_es_una_sola_tabla
```

Expected: PASS.

- [ ] **Step 6: Instalación limpia — es donde de verdad se ve si los seeders siguen bien**

```bash
php artisan migrate:fresh --seed
```

Expected: sin error. Los tres seeders de estructura escriben mallas; si alguno falla, es él quien debe adaptarse.

- [ ] **Step 7: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

Es la tarea con más probabilidad de romper pruebas en verde. Cada fallo nuevo se corrige en la prueba, no relajando el esquema.

- [ ] **Step 8: Verificar en el navegador** el listado de mallas, la ficha de una malla, la importación de Excel y el flujo de simulación completo.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor(bd): plan de estudios y malla curricular eran la misma entidad; se fusionan"
```

---

### Task 14: `simulaciones` sin redundancia y con el diamante cerrado

Diez columnas que se pueden derivar (3FN-2, 3FN-3, 3FN-4): `nombres`, `apellidos`, `tipo_documento`, `numero_documento`, `email`, `telefono`, `ciclo_postulacion`, `universidad_origen`, `carrera_externa_id`, `postulante_id`.

`carrera_usil_id` **se conserva**, pero deja de ser una FK independiente: pasa a ser clave propagada verificada por dos FK compuestas —contra `planes_estudio(id, carrera_id)` y contra `postulacion_destinos(id, carrera_id)`— que obligan a ambos caminos a la misma carrera. Esto conserva los ~57 puntos de código que la usan (`AlcanceService::autorizarCarrera`, el scope `FiltraPorCarrera`, `HistorialEquivalenciasService`) y a la vez hace imposible la contradicción.

**Files:**
- Create: `database/migrations/2026_08_14_000005_simulaciones_sin_redundancia.php`
- Modify: `app/Models/Simulacion.php:17-22`
- Modify: `app/Http/Controllers/SimulacionController.php:180,572-576,666`
- Modify: `app/Exports/Sheets/PreconvalidacionSheet.php`, `resources/views/pdf/simulacion.blade.php`
- Modify: `tests/Feature/NormalizacionEstructuralTest.php`

- [ ] **Step 1: Escribir la prueba que falla**

Añadir a `NormalizacionEstructuralTest`:

```php
    /** El nombre del postulante vive en postulantes. El PDF ya guardado es el
     *  congelado legal; duplicar el dato en columnas editables no congela nada. */
    public function test_simulaciones_no_duplica_los_datos_del_postulante(): void
    {
        foreach (['nombres', 'apellidos', 'tipo_documento', 'numero_documento',
                  'email', 'telefono', 'universidad_origen'] as $columna) {
            $this->assertFalse(Schema::hasColumn('simulaciones', $columna),
                "simulaciones.{$columna} se puede derivar del postulante.");
        }
    }

    /** Y la carrera que se lee en la simulación debe ser la misma por los dos
     *  caminos: el del plan de estudios y el del destino del postulante. */
    public function test_la_carrera_de_la_simulacion_esta_verificada_por_fk_compuesta(): void
    {
        $compuestas = \Illuminate\Support\Facades\DB::select(
            "SELECT CONSTRAINT_NAME n FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simulaciones'
               AND COLUMN_NAME = 'carrera_usil_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
        );

        $this->assertGreaterThanOrEqual(2, count($compuestas),
            'carrera_usil_id debe estar atada por FK compuesta a plan y a destino.');
    }
```

- [ ] **Step 2: Correrlas y verificar que fallan**

```bash
php artisan test --filter="test_simulaciones_no_duplica|test_la_carrera_de_la_simulacion"
```

Expected: 2 FAIL.

- [ ] **Step 3: Escribir la migración** — cuatro bloques en este orden:

1. `ALTER TABLE postulante_destinos ADD UNIQUE uq_destino_id_carrera (id, carrera_id)`.
2. Añadir `simulaciones.destino_id BIGINT UNSIGNED NULL` y poblarlo desde `postulante_destinos` cruzando `(postulante_id, carrera_usil_id)`; después dejarlo `NOT NULL`.
3. Añadir las dos FK compuestas: `(plan_estudio_id, carrera_usil_id) → planes_estudio(id, carrera_id)` y `(destino_id, carrera_usil_id) → postulante_destinos(id, carrera_id)`.
4. Eliminar las diez columnas redundantes y sus FK.

- [ ] **Step 4: Adaptar las lecturas — aquí aparece el riesgo de N+1**

Cada columna eliminada se convierte en un salto de relación, y Eloquent los resuelve perezosamente. **Toda consulta que liste simulaciones debe declarar su carga anticipada:**

```php
$sims = Simulacion::with([
    'destino.postulante',
    'destino.carrera',
    'mallaExterna.carreraExterna.institucion',
])->get();

$p = $sim->destino->postulante;
echo "{$p->nombres} {$p->apellido_paterno} {$p->apellido_materno}";
```

- [ ] **Step 5: Correr las pruebas nuevas**

```bash
php artisan test --filter=NormalizacionEstructuralTest
```

Expected: PASS (8 passed).

- [ ] **Step 6: Verificar el Excel y el PDF con especial cuidado**

```bash
php artisan test --filter="ExportacionPreconvalidacionTest|DocumentosEmitidosTest"
```

`ExportacionPreconvalidacionTest` ya está en la línea base como fallido; `DocumentosEmitidosTest` está en verde y debe seguir. **La exportación a Excel ya se rompió dos veces en este repositorio** (commits `d961e69` y `9aebda9`): abrir el archivo generado y mirarlo, no confiar solo en la prueba.

- [ ] **Step 7: Suite completa contra la línea base**

```bash
php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | grep -E "^\s+(FAILED|Tests:)" | sed 's/^  *//' > /tmp/actual.txt; diff docs/superpowers/plans/linea-base-2026-08-13.txt /tmp/actual.txt
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(bd): simulaciones pierde 10 columnas derivables y cierra su diamante con FK compuestas"
```

---

## Fuera de alcance, y por qué

| Hallazgo de la auditoría | Motivo de la exclusión |
|---|---|
| **Partir `postulantes`** en `personas` + `postulaciones` + credenciales (§4.6) | Toca el proveedor de autenticación del guard `postulante`. Es el único cambio cuyo fallo no se manifiesta como prueba roja sino como gente que no puede entrar. Merece su propio plan con ventana de verificación. |
| **`convalidaciones`** con clave compartida y `responsables` normalizado (§4.2) | `Plan-Correccion-Entrega-TI.md` decide **retirar el módulo**. Normalizar algo que va a desaparecer es trabajo perdido. **Bloqueado hasta que se confirme la decisión.** |
| Catálogos `periodos_academicos`, `paises`, `tipos_documento` | 1FN de `ciclo_postulacion`. Real, pero toca validaciones y 3 archivos Vue con poco rédito frente al resto. Siguiente iteración. |
| `auditoria_log`: dos FK en lugar de `actor_tipo`/`actor_id` | `AuditoriaE2ETest` son 20 pruebas y el ENUM de la tabla es deliberadamente fijo. Cambio de bajo riesgo pero alto roce; no cabe en estas dos fases. |
| `responsables` JSON → tabla | Depende de la misma decisión sobre Convalidación. |

---

## Self-review

**Cobertura del reporte de auditoría:**

| Sección | Estado |
|---|---|
| 1FN-1 competencias | Task 10 |
| 1FN-3 prerrequisitos | Task 11 |
| 1FN-2 responsables, 1FN-4/5 ciclo | **Fuera de alcance, justificado** |
| 2FN-1 / 3FN-1 planes vs mallas | Task 13 |
| 3FN-2/3/4 simulaciones | Task 14 |
| 3FN-5/6 equivalencias | Task 12 |
| 3FN-7 postulantes | Task 2 |
| 3FN-8 carrera_destino_id | **Fuera de alcance** (depende de partir `postulantes`) |
| 3FN-9 clave_normalizada | Task 9 |
| INT-1 a INT-9 | Tasks 3-9 — **completo** |
| §2.4 1:1 artificiales y puentes | Task 8 (puentes); `convalidaciones` fuera de alcance |
| §2.3 propagación de clave | Tasks 12 y 14 |

**Consistencia de nombres verificada:** `plan_estudio_id` se usa igual en las tareas 12, 13 y 14. `MapeoMalla::cursos()` (Task 12) es la relación que consume el `withCount('cursos')` del mismo paso. `vigente_flag` se crea en la Task 7 sobre `mallas_curriculares` y migra a `planes_estudio` en la Task 13.

**Dependencia de orden detectada y anotada:** la Task 12 necesita `planes_estudio.id` como destino, que la Task 13 produce. **Ejecutar 13 antes que 12**, o usar `malla_usil_id` en la 12 y renombrarlo en la 13.

**Riesgo residual conocido:** la Task 8 tiene el `down()` más frágil del plan (baile de claves primarias con AUTO_INCREMENT). Está anotado en su paso de rollback.
