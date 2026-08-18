# Auditoría de entrega a TI — Sistema de Convalidaciones USIL

**Fecha:** 18 de agosto de 2026
**Rama:** `refactor/normalizacion-bd` (último commit `4272461`, del 14/08) **más 64 archivos modificados y 12 sin versionar que no están confirmados**
**Antecedente:** [Auditoría del 10/08/2026](Auditoria-Entrega-TI-2026-08-10.md). La mayoría de sus puntos están cerrados.

---

## ESTADO DE EJECUCIÓN — cerrado el 18/08/2026

Todo lo que sigue **ya se aplicó**. El informe se conserva íntegro como registro de lo que se
encontró; esta sección dice qué se hizo con cada punto.

| Punto | Estado | Qué se hizo |
|---|---|---|
| **B-1** Falta compilar en clon limpio | ✅ Cerrado | Versionados `resources/images/` (logo y frontis) y `ModalCredenciales.vue`. **Verificado compilando desde el árbol exportado de Git: 803 módulos, build correcto** |
| **B-2** Trabajo sin confirmar | ✅ Cerrado | Todo confirmado tras pasar las pruebas |
| **B-3** Scripts sueltos en la raíz | ✅ Cerrado | Borrados los 5 scripts, `task.md`, `public/_diagramas_tmp.html`, `public/images/` (duplicado sin uso) y el Excel de prueba |
| **C-1** README desactualizado | ✅ Cerrado | Reescrito: tabla de módulos retirados, cifras reales (87 migraciones, 25 modelos, 168 pruebas), sección «Motor de IA — se entrega APAGADO» |
| **C-2** Restos de la IA | ⏸️ **No se toca, por decisión** | El andamiaje se conserva inerte para poder reactivarlo. Se corrigió **solo la documentación** que inducía a error: `.env.example` y `deploy/.env.production.example` ahora dicen explícitamente que las variables van vacías y que no hay clave que conseguir |
| **C-3** Exportación Excel muerta | ✅ Cerrado | Borrados `PreconvalidacionExport` y sus tres hojas |
| **C-4** Estilo en rojo | ✅ Cerrado | `pint` pasa |
| **C-5** RUNBOOK con pasos imposibles | ✅ Cerrado | Corregidos §2, §3, §7 (lista de verificación) y §10 |
| **C-6** Permisos huérfanos | ✅ Parcial | `dashboard.ver` retirado con migración. `configuracion.gestionar` **se conserva**: es el de la pantalla de IA, que se entrega apagada (documentado en el catálogo) |

**Hallazgos adicionales encontrados al ejecutar, no presentes en el informe original:**

| Hallazgo | Resolución |
|---|---|
| `app/Http/Middleware/EnsureRole.php` — registrado en `bootstrap/app.php` pero **sin una sola ruta que lo use**; su propia documentación apuntaba al rol `Administrador`, renombrado a `Superusuario` hace meses | Borrado, junto con su alias |
| Pantalla **Planes de Estudio** (`PlanEstudioController` + `Estructura/Planes/Index.vue` y `Form.vue`) — construida pero **sin ruta desde el primer commit**: nunca fue alcanzable | Borrada. Los planes se cargan por seeder y se asocian desde Mallas |
| `docs/Documentación_Final/` (21 MB) fuera de Git | Decisión: se entrega aparte. Añadida a `.gitignore` |

**Verificación final:** `pint` pasa · **168 pruebas pasan, 0 fallan** · la compilación desde el
árbol versionado de Git funciona.

---

## REVISIÓN DE COHERENCIA CON LA BASE DE DATOS — 18/08/2026

Se reconstruyó el esquema desde cero con las 89 migraciones y se contrastó contra el código.
**El esquema quedó en 34 tablas: 27 de negocio y 7 de infraestructura.**

### Lo que está sólido

| Comprobación | Resultado |
|---|---|
| Modelos con tabla existente | 24/24 |
| Claves foráneas | 45, ninguna rota |
| `$fillable` contra columnas reales | sin discrepancias |
| Motor y cotejamiento | 34/34 InnoDB + `utf8mb4_unicode_ci`, uniforme |
| Tablas sin clave primaria | ninguna |

Dos cosas que parecen defectos y no lo son: `mallas_curriculares.activa_unica` y `vigente_flag`
no aparecen en el código porque son **columnas virtuales generadas** que sostienen índices
únicos —la regla vive en la base, que es donde debe estar—; y `auditoria_log.actor_id` y
`registro_id` no tienen clave foránea porque son **polimórficas**.

### Relaciones diamante

Un «diamante» es un camino por el que una fila alcanza al mismo ancestro por dos rutas y nada
impide que discrepen. Se detectaron 43 convergencias; descartando las que son dos actores
legítimamente distintos, quedan **siete que sí exigen coincidencia**. No se dedujeron: se
intentó escribir la incoherencia directamente en la base.

| Diamante | Antes | Ahora |
|---|---|---|
| `equivalencias`: carrera externa propagada | 🟢 cerrado | 🟢 cerrado |
| `postulantes`: institución de origen vs. la de su carrera externa | 🔴 abierto | 🟢 **cerrado** |
| `simulaciones`: carrera USIL vs. la de su malla | 🔴 abierto | 🟢 **cerrado** |
| `simulaciones`: carrera externa vs. la del postulante | 🔴 abierto | ⏸️ instantánea deliberada |
| `postulante_destinos`: carrera vs. `carrera_destino_id` | 🔴 abierto | ⏸️ Fase 2 |
| `simulacion_detalle`: curso USIL vs. malla de su simulación | 🔴 abierto | ⏸️ Fase 2 |
| `cursos_usil`: prerrequisito vs. malla | 🔴 abierto | ⏸️ Fase 2 |

Los dos cerrados eran los que más daño hacen: se podía registrar a un postulante «de la UNMSM»
con una carrera de otra universidad, y **emitir una preconvalidación que decía evaluar la
carrera A contra la malla de la carrera B** —un documento que firma la universidad—.

Los cuatro restantes se dejaron abiertos **a conciencia**, no por olvido:

- **Instantánea legítima.** El asesor puede corregir la carrera de origen de un postulante ya
  registrado; la simulación debe conservar contra qué se evaluó. Atarlas bloquearía la
  corrección o reescribiría el historial en silencio.
- **`postulante_destinos` es uno-a-muchos**: un postulante puede postular a varias carreras.
  Atarla obligaría a que todos sus destinos fueran el mismo. Lo que sobra es la columna
  duplicada `postulantes.carrera_destino_id`, y retirarla toca 39 puntos del código y cuatro
  pantallas Vue.
- **`cursos_usil` no tiene `malla_id` propio** (cuelga de `ciclos`), así que esas dos FK
  exigirían desnormalizar la columna y mantenerla sincronizada: es un cambio de modelo, no una
  restricción. Hoy lo cubre el código, con prueba propia.

### Normalización — deuda pendiente para Fase 2

`simulaciones` duplica cinco columnas de `postulantes` (`nombres`, `apellidos`,
`tipo_documento`, `numero_documento`, `email`). **No es una decisión de diseño: es deuda que el
propio código reconoce.** La migración del 13/08 lo dice literalmente:

> «La Fase 2 elimina esta columna: el tipo de documento es del postulante y no debe duplicarse
> aquí. Mientras exista, que al menos no mienta.»

Y ya causó un defecto real: los dos ENUM divergían y las simulaciones registraban «DNI» a
personas con carné de extranjería temporal, de modo que el documento emitido decía algo falso.
Se reparó el síntoma; la duplicación sigue.

### Lo que se corrigió

- Se retiró **«Plan de Estudios»**, una entidad construida entera que nunca funcionó: su
  pantalla jamás tuvo ruta, su columna era NULL en el 100% de las filas y ningún seeder la
  poblaba. Salieron tabla, columna, modelo y relaciones.
- Se cerraron los dos diamantes descritos con claves foráneas compuestas.
- Salió `postulantes.pais_residencia` (columna muerta) y dos índices redundantes.
- **No** se tocaron las columnas del memorándum en `convalidaciones`: la tabla es historial y
  hay instalaciones con memorandos ya emitidos.

---

## Veredicto

**El sistema funciona. El paquete que recibiría TI, no.**

Las 168 pruebas pasan. La instalación limpia funciona. El trabajo de contenido está terminado. El problema es de **empaquetado**: lo que está en tu máquina no es lo que está en Git, y lo que se entrega sale de Git.

Hay **3 bloqueantes**. Ninguno es de arquitectura y los tres se cierran en una mañana.

### Lo que se midió hoy

| Verificación | Resultado |
|---|---|
| Pruebas (`php artisan test`) | **168 pasan, 0 fallan** ✅ |
| Instalación limpia (migraciones + siembra) | funciona ✅ |
| **Compilación desde un clon limpio — lo que recibe TI** | **FALLA** ❌ |
| Estilo de código (`pint --test`) | falla en 14 archivos ❌ |
| Vulnerabilidades de dependencias (`composer audit`) | 3 avisos, 1 paquete ⚠️ |

---

## 1. Bloqueantes — hay que cerrarlos antes de entregar

### B-1 · La aplicación no compila con lo que hay en Git

Tres archivos que la aplicación necesita **existen en tu computadora pero nunca se agregaron al repositorio**:

| Archivo | Dónde se usa |
|---|---|
| `resources/images/usil_logo.jpg` | El logo. En 6 pantallas, incluido el login |
| `resources/images/usil_frontis.jpg` | El fondo del login |
| `resources/js/Components/ModalCredenciales.vue` | El modal de credenciales, en el layout de **toda** la aplicación |

Aquí compila porque los archivos están en el disco. En un clon limpio no existen y la compilación se detiene. Se reprodujo:

```
Could not resolve "../../../images/usil_logo.jpg" from "resources/js/Pages/Auth/OlvidePassword.vue"
✗ Build failed
```

Sin compilación no hay CSS ni JavaScript: la aplicación se abre en blanco.

**Es exactamente el mismo fallo que la auditoría del 10/08 encontró con `VolverA.vue`.** Aquel se corrigió; este es nuevo y son tres archivos en vez de uno.

**Corrección:**

```bash
git add resources/images resources/js/Components/ModalCredenciales.vue
```

---

### B-2 · Un mes de trabajo sigue sin confirmar en Git

**64 archivos modificados y 12 sin versionar. 4.539 líneas nuevas, 1.923 borradas.** Nada de eso está en ningún commit.

El último commit es del **14 de agosto**. Si mañana se entrega el repositorio o se ejecuta `deploy/empaquetar.sh` —que parte de un clon limpio, como debe ser—, **TI recibe la versión del 14 de agosto**, sin el trabajo de los últimos días.

**Corrección:** confirmar todo el trabajo *después* de cerrar B-1 y B-3, y verificar el paquete con `bash deploy/empaquetar.sh`.

---

### B-3 · Cinco scripts sueltos en la raíz del proyecto, uno de ellos destructivo

| Archivo | Qué hace |
|---|---|
| **`limpiar_db.php`** | **Vacía 14 tablas del sistema** —postulantes, documentos, simulaciones, auditoría, mallas— sin preguntar nada y sin pedir usuario |
| `check_records.php` | Volcado de mallas y permisos por consola |
| `listar_tablas.php` | Lista las tablas de la base |
| `prueba_lector.php` | Prueba manual del lector de Excel |
| `scratch_excel.php` | Inspección manual de la plantilla |
| `task.md` | Lista de tareas personal, con pasos aún marcados como pendientes |
| `public/_diagramas_tmp.html` | Archivo temporal **dentro de la carpeta pública del servidor web** |

Son restos de desarrollo. `limpiar_db.php` es el grave: es un botón de borrado total sin ninguna protección, en la carpeta principal del proyecto. Nadie debería recibirlo.

**Corrección:** borrar los siete. No están en Git, así que borrarlos no pierde nada.

---

## 2. Corregir antes de entregar — no rompen el sistema, pero se notan

### C-1 · El README describe un sistema que ya no existe

Es el primer archivo que abre TI. Hoy documenta como incluidos módulos que fueron retirados:

- **Asistente de IA** (Gemini/OpenAI, `SugerenciaController`, `Seudonimizador`) — retirado el 15/08
- **Mallas externas** — retirado el 15/08
- **Memorándum oficial y anulación** — retirado el 10/08
- **Reportes y exportación** — retirado el 10/08

La tabla «Cobertura del alcance (7 módulos)» marca con ✅ tres módulos que ya no están, e instruye a configurar `OPENAI_API_KEY` y `GEMINI_API_KEY`.

Un revisor de TI que compare el README con el sistema concluirá que falta la mitad de lo prometido.

**Corrección:** reescribir las secciones de Sprint 3, Sprint 4, «Cobertura del alcance» y «Configuración adicional (IA)».

---

### C-2 · Restos de la IA retirada

El módulo salió, pero quedaron piezas sueltas:

| Resto | Qué es |
|---|---|
| `resources/js/Pages/Configuracion/Index.vue` (196 líneas) | Pantalla de API keys. **Sin ruta ni controlador**: es inalcanzable |
| `app/Models/Configuracion.php` (67 líneas) | Modelo sin uso; sigue declarando `gemini_api_key` y `openai_api_key` como secretos |
| `app/Services/Seudonimizador.php` (26 líneas) | Limpiaba datos personales antes de llamar a la IA. Ya no hay a quién llamar |
| `config/services.php` | Conserva el bloque `openai` y un comentario sobre Gemini sin nada debajo |
| `.env.example` y `deploy/.env.production.example` | Piden `GEMINI_API_KEY`, `OPENAI_API_KEY`, `IA_PROVEEDOR` |
| Permiso `configuracion.gestionar` | En el catálogo de permisos, sin ninguna pantalla que lo use |

TI leerá `.env.production.example`, verá que pide claves de IA y preguntará dónde conseguirlas.

---

### C-3 · Código muerto: una exportación a Excel completa que nadie llama

`app/Exports/PreconvalidacionExport.php` y sus tres hojas (`PreconvalidacionSheet`, `NoConvalidadosSheet`, `FormatoErpSheet`) suman **329 líneas sin un solo consumidor**. Las descargas reales usan las plantillas institucionales (`formato_simulacion.xltx` y `plantilla_preconvalidacion_oficial.xlsx`), no estas clases.

Detalle que conviene saber: **esos tres archivos están entre los que modificaste sin confirmar.** Se editó código que no se ejecuta.

Otros dos huérfanos: `app/Models/Concerns/FiltraPorCarrera.php` (24 líneas, ningún modelo lo usa) y el permiso `dashboard.ver`, declarado y nunca exigido.

---

### C-4 · El verificador de estilo falla en 14 archivos

`./vendor/bin/pint --test` falla. Cinco de los catorce son los scripts sueltos de B-3, que desaparecen al borrarlos. Los nueve restantes se corrigen solos:

```bash
./vendor/bin/pint
```

Entregar un repositorio cuyo propio control de calidad está en rojo es un problema de confianza antes que técnico.

---

### C-5 · El manual de despliegue da instrucciones sobre cosas que ya no existen

`deploy/RUNBOOK.md` es el documento que TI va a seguir paso a paso. Cuatro puntos quedaron desactualizados:

| Dónde | Qué dice | Realidad |
|---|---|---|
| §2 | «`storage/` guarda […] los PDF de mallas externas» | El módulo se retiró |
| §3 | «`APP_KEY` […] cifra las API keys guardadas en `configuraciones`» | Ya no se guardan API keys |
| §7, lista de verificación | «Registrar una institución y su malla externa; el PDF adjunto se descarga…» | **No se puede hacer.** TI marcará este paso como fallido |
| §10 | «No ejecutar `key:generate` […]: invalidaría las API keys cifradas» | Solo aplica a las sesiones |

El §7 es el más costoso: es una prueba de aceptación que TI intentará ejecutar y no podrá.

---

## 3. Eliminar — lista completa

**Restos de desarrollo (no versionados, borrar sin más):**

```
check_records.php   limpiar_db.php   listar_tablas.php
prueba_lector.php   scratch_excel.php   task.md
public/_diagramas_tmp.html
docs/cursos_malla_externa_SENATI.xlsx
```

**Código muerto (versionado, requiere commit):**

```
app/Exports/PreconvalidacionExport.php          35 líneas
app/Exports/Sheets/PreconvalidacionSheet.php   123
app/Exports/Sheets/NoConvalidadosSheet.php      98
app/Exports/Sheets/FormatoErpSheet.php          73
resources/js/Pages/Configuracion/Index.vue     196
app/Models/Configuracion.php                    67
app/Services/Seudonimizador.php                 26
app/Models/Concerns/FiltraPorCarrera.php        24
                                        ──────────
                                          642 líneas
```

Más: el bloque `openai` de `config/services.php`, las variables de IA de los dos `.env.example`, y los permisos `configuracion.gestionar` y `dashboard.ver` del catálogo.

---

## 4. Lo que está bien y conviene decirlo

No todo son correcciones. Estos puntos se verificaron y están sólidos:

- **168 pruebas pasan, ninguna falla.** Cubren seguridad, RBAC, alcance por carrera, carga masiva, simulación, portal del postulante, exportaciones y auditoría de extremo a extremo.
- **La instalación limpia funciona.** Migraciones y siembra dejan una base operativa con instituciones, roles, facultades y programas.
- **La contraseña del administrador ya no está en el repositorio.** Se genera al azar y se muestra una sola vez, como se pidió en la auditoría anterior.
- **El `.env` local ya no contiene claves de API reales.**
- **`deploy/empaquetar.sh` parte de un clon limpio y aborta si falta algo o si viaja lo que no debe.** Es el que va a detectar B-1 si no se corrige antes.
- **No hay código de depuración olvidado** (`dd()`, `console.log`, volcados) en ninguna parte.

---

## 5. Lo que TI debe saber — declarar, no ocultar

Cosas que no son defectos a corregir, pero que deben ir por escrito en la entrega:

1. **Tres avisos de seguridad en `laravel/framework` 11.54.** Solo se corrigen subiendo a Laravel 12, que es un cambio de versión mayor. El más serio —inyección CRLF en la validación de correos— **está mitigado en el código** (`App\Rules\Correo`, con prueba que lo respalda). Los otros dos no aplican a este sistema.
2. **`composer.json` lleva `"advisories": {"block": false}`**, que desactiva a propósito el bloqueo por avisos. Sin eso, `composer install` fallaría en el servidor de TI. Debe explicarse por qué está y cuándo se retira.
3. **Documentación pendiente**, según `docs/expediente-pase-produccion/INDICE.md`: los anexos A1, A2, A4 y A6; el Documento 2 (Expediente Técnico) y el 3 (presentación); y los diagramas D-03, D-04 y D-06. Son los que suele pedir el comité.
4. **El servidor necesita salida a internet** para las tipografías de Google. Sin ella la aplicación funciona, pero se ve con tipografías del sistema.
5. **`backups/` contiene un respaldo con datos personales reales** (postulantes, documentos, usuarios). **Nunca comprimir la carpeta de trabajo para entregar**; usar siempre `deploy/empaquetar.sh`, que lo excluye.

---

## 6. Orden de trabajo sugerido — media jornada

```bash
# 1. Borrar los restos de desarrollo
rm check_records.php limpiar_db.php listar_tablas.php prueba_lector.php \
   scratch_excel.php task.md public/_diagramas_tmp.html

# 2. Incorporar los tres archivos que faltan (B-1)
git add resources/images resources/js/Components/ModalCredenciales.vue

# 3. Borrar el código muerto (sección 3)

# 4. Actualizar README.md y deploy/RUNBOOK.md (C-1 y C-5)

# 5. Corregir el estilo
./vendor/bin/pint

# 6. Verificar que nada se rompió
php artisan test

# 7. Confirmar todo el trabajo (B-2)
git add -A && git commit

# 8. Construir el paquete y comprobar que sale limpio
bash deploy/empaquetar.sh
```

El paso 8 es la verificación real: si el paquete se construye sin abortar, la entrega está lista.
