# Diseño — Detección de criterio dividido en la base de conocimiento de equivalencias

**Fecha:** 2026-08-07
**Rama base:** `feat/cumplimiento-proceso-traslado-externo`
**Autor:** Frank Rodríguez

## Problema

La base de conocimiento histórica ya responde "con qué se convalidó este curso antes y
cuántas veces", pero lo hace como un **ranking silencioso**: si un mismo curso de origen
se resolvió con tres cursos USIL distintos, se listan los tres ordenados por frecuencia
sin decir en ningún sitio que el criterio está dividido.

Eso deja dos huecos:

1. **El evaluador** no distingue "esto siempre se resolvió igual" de "aquí no hay
   criterio establecido", que son situaciones que exigen atención muy distinta.
2. **Nadie puede auditar** la coherencia acumulada: no hay forma de preguntarle al
   sistema qué cursos se están resolviendo de formas incompatibles.

## Objetivo

Marcar y poder listar los cursos de origen con **criterio dividido**, en dos superficies:

1. Aviso en línea para el evaluador, en el panel de antecedentes del espacio de trabajo.
2. Filtro en la pantalla del histórico, para revisión periódica del coordinador.

Es **material de consulta de solo lectura**. No bloquea nada, no propone nada y no
guarda estado nuevo.

## Advertencia sobre la premisa

**Divergencia no es error.** `ConvalidacionEngine::asignacionOptima()` hace asignación
1‑a‑1 sobre el expediente completo, así que «Matemática I» puede haber ido legítimamente
a *Cálculo I* en un caso y a *Matemática Básica* en otro porque en el segundo expediente
ya había otro curso ocupando *Cálculo I*.

La funcionalidad señala **divergencia para revisar**, no señala culpables. Toda la
redacción de la interfaz debe sostener eso; si se presenta como alarma, el evaluador
aprende a ignorarla en dos semanas y la función queda muerta.

## Contexto — lo que YA existe (no se modifica)

- `App\Services\HistorialEquivalenciasService` agrega **en vivo** sobre
  `simulacion_detalle`. No hay tabla ni caché de equivalencias: el catálogo curso↔curso
  se eliminó por decisión de TI (BD‑07, migración `2026_08_02_000003`) y este diseño
  **no lo reintroduce**.
- `base()` acota a filas reales de evaluación (`clasificacion = convalidable`,
  `curso_usil_id` no nulo, `excluido = false`, simulación viva) y aplica el alcance
  RF‑40 por carrera USIL destino.
- `agregado()` agrupa por `(origen_nombre, curso_usil_id, carrera_usil_id,
  carrera_externa_id, institucion)` y ya selecciona `cu.codigo as codigo_usil`.
- `antecedentes()` agrupa nombres de origen por `ConvalidacionEngine::similitud()`
  con umbral `UMBRAL_MISMO_CURSO = 0.8`, porque `curso_externo_id` es nulo en todo el
  histórico (nullable desde `2026_07_09_000001`) y solo queda el texto libre
  `curso_origen_nombre`.
- `consulta()` devuelve un Builder sin ejecutar; la pantalla pagina y la exportación
  hace `->get()` sobre los mismos filtros.
- Pantalla `/simulaciones/historico` (permiso `evaluacion.ver`) con filtros `q`,
  `institucion_id`, `carrera_usil_id`, paginación y descarga a Excel.
- Panel de antecedentes en `MapeoUsilMatch.vue`, alimentado por
  `GET /simulaciones/antecedentes`. Pulsar un antecedente solo mueve la selección; la
  equivalencia la confirma el evaluador.
- La colación por defecto es `utf8mb4_unicode_ci`: **insensible a mayúsculas y tildes**,
  de modo que un `GROUP BY curso_origen_nombre` ya colapsa `Matemática I` /
  `matematica i` / `MATEMÁTICA I` sin ayuda.
- Solo hay pruebas PHPUnit; **no hay infraestructura de tests de JS**.

## La regla

> Un curso de origen tiene **criterio dividido** cuando, dentro de una misma carrera
> USIL destino, se ha convalidado con **≥2 códigos de curso USIL distintos**.

Tres decisiones dentro de esa frase:

**Por código, no por id.** `cursos_usil` cuelga de `ciclo → malla → plan de estudio`,
así que un cambio de plan genera `curso_usil_id` nuevos para los mismos cursos. Comparar
por id marcaría como divergencia cada actualización de malla. Se asume que USIL conserva
el código del curso entre versiones de plan; si esa premisa cae, la regla hay que
revisarla entera.

**Dentro de la misma carrera USIL destino.** Entre carreras la malla es otra y divergir
ahí es lo esperado, no una incoherencia.

**Sin nada constante en el lado de origen.** «Matemática I» venga de la universidad que
venga. Es donde suele estar el criterio dispar (la misma asignatura tratada distinto
según la procedencia), a cambio de que algún homónimo real dispare una señal falsa.

**Sin umbral de reparto.** Cualquier caso con ≥2 destinos se marca. El ruido se controla
ordenando (lo más repartido primero), no escondiendo casos: así no hay ninguna constante
arbitraria que calibrar.

**Se calcula sobre todo el alcance visible, no sobre las filas filtradas.** Si el
coordinador filtra por una universidad, sigue viendo que el curso diverge; el filtro
acota lo que se lista, no lo que se considera.

## Enfoque: cada superficie calcula con lo que ya tiene en la mano

Las dos superficies responden preguntas de distinto tamaño y se resuelven distinto:

| Superficie | Cálculo | Agrupa nombres por |
|---|---|---|
| Panel del evaluador | En PHP, sobre la lista de antecedentes que la función ya trae (≤ decenas de filas) | Similitud ≥ 0.8 |
| Pantalla del histórico | En SQL, `HAVING COUNT(DISTINCT cu.codigo) > 1` | Igualdad de nombre (colación `_ci`) |

**Precio conocido y aceptado:** la pantalla puede no listar una divergencia que el panel
sí marca, cuando los nombres solo se *parecen* (`normaliza()` no traduce numerales
romanos, así que `Matemática 1` y `Matemática I` son cadenas distintas para SQL aunque
`similar_text` las una). **Nunca al revés**: la pantalla no inventa divergencias, solo se
queda corta.

Las alternativas descartadas y por qué:

- **SQL puro en ambas.** El panel enseñaría dos destinos distintos y a la vez «sin
  criterio dividido» en la misma tarjeta cuando los nombres difieren en el numeral.
  Incoherencia visible para el usuario.
- **Similitud en PHP en ambas.** Coherencia total, pero la pantalla pierde la paginación
  en SQL (traer todo y paginar en memoria) y hereda un O(n²) de `similar_text` en cada
  carga. Es el techo que la propia clase ya documenta en su comentario `ponytail:`.

## Cambios

### 1. `HistorialEquivalenciasService`

**a) `antecedentes()` pasa a devolver la cuenta junto a la lista.**

Firma nueva: `array{antecedentes: array<int,array<string,mixed>>, criterios: int|null}`.

```php
'criterios' => $carreraUsilId === null ? null
    : $lista->where('mismo_destino', true)->unique('codigo_usil')->count(),
```

`null` cuando la petición no trae carrera destino: sin contexto no se afirma nada. El
early return de nombre vacío y el de «sin equivalentes» devuelven
`['antecedentes' => [], 'criterios' => null]` — no distinguen `null` de `0` porque la
interfaz solo reacciona a `>= 2` y la diferencia no cambia nada de lo que se ve.

**b) Nuevo método privado `divergentes()`** — pares `(nombre, carrera)` con criterio
dividido y su cuenta:

```php
$this->base($carrerasPermitidas)
    ->selectRaw('sd.curso_origen_nombre as nombre, s.carrera_usil_id as carrera,
                 COUNT(DISTINCT cu.codigo) as criterios')
    ->groupBy('sd.curso_origen_nombre', 's.carrera_usil_id')
    ->havingRaw('COUNT(DISTINCT cu.codigo) > 1');
```

Reutiliza `base($carrerasPermitidas)`, de modo que el alcance RF‑40 se aplica igual y sin
duplicar la definición de «fila real de evaluación».

**c) `consulta()` acepta `solo_divergentes`.** Solo cuando está activo:

- `joinSub($this->divergentes(...), 'dv', ...)` por `(nombre, carrera)`.
- `addSelect('dv.criterios')` y `dv.criterios` añadido al `groupBy` — MySQL con
  `ONLY_FULL_GROUP_BY` no deduce la dependencia funcional a través de una subconsulta.
- Orden `criterios DESC, curso_origen_nombre, veces DESC`: lo más repartido primero y las
  filas del mismo curso contiguas.

Con el toggle apagado la consulta actual **no cambia en nada**: ni join, ni columna, ni
orden distinto.

### 2. `HistorialEquivalenciasController`

- `antecedentes()`: `response()->json($this->historial->antecedentes(...))` — el servicio
  ya devuelve la forma final, así que desaparece el envoltorio manual.
- `index()`: sumar la clave al array de filtros y exponer `criterios` en el mapeo de
  `through()`.
- `exportar()`: construir los filtros igual que `index()`, para que la descarga siga
  llevando impreso lo que se está viendo.

En ambos, el valor **no** sale de `$request->only()` sino de
`$request->boolean('solo_divergentes')`:

```php
$filtros = $request->only(['q', 'institucion_id', 'carrera_usil_id'])
    + ['solo_divergentes' => $request->boolean('solo_divergentes')];
```

Llega por query string, así que `"1"`, `"true"` y `"on"` cuentan como activo y cualquier
otra cosa como apagado. El caso que esto evita es concreto: la casilla del filtro es un
booleano real, e Inertia serializa `false` como la cadena `"false"`. Con `only()` crudo,
`?solo_divergentes=false` entraría al `when()` del Builder como `"false"` — *truthy* en
PHP, donde solo `""` y `"0"` son cadenas falsas — y activaría el filtro justo cuando el
usuario lo desmarca.

### 3. Interfaz

**`Historico.vue`**

- Casilla *«Solo cursos con criterio dividido»* en la tarjeta de filtros, dentro del
  objeto `filtro` reactivo (así `urlExcel` la arrastra sin tocar nada más).
- Columna con insignia `N criterios`, visible solo con el filtro activo.
- Vacío específico: «Ningún curso con criterio dividido», distinto del actual «Todavía no
  hay equivalencias registradas» — `sinFiltros` debe tener en cuenta la casilla.
- Una línea en la descripción de la pantalla explicando que agrupa por nombre exacto (el
  precio del enfoque, dicho en la propia interfaz y no solo en el código).

**`MapeoUsilMatch.vue`**

- Nueva prop `criterios` (Number, default null), pasada desde `Simular.vue`.
- Cuando `criterios >= 2`, una línea sobre la lista de antecedentes con tono de contexto:
  *«Este curso se ha resuelto de N formas distintas en esta carrera. Revisa cuál encaja
  con este expediente.»* Sin color de alarma, sin bloquear nada.

**`Simular.vue`**

- `buscarAntecedentes()` guarda también `data.criterios`. La caché por nombre
  (`cacheAntecedentes`) pasa a guardar el objeto completo en vez del array, y el guard de
  respuesta fuera de orden (`token === peticionAntecedentes`) se aplica a ambos valores.
- El `catch` sigue degradando a lista vacía y `criterios = null`: el histórico es una
  ayuda opcional y su fallo no puede afectar al emparejamiento manual.

### 4. Alcance y permisos

Ninguno nuevo. `divergentes()` reutiliza `base($carrerasPermitidas)` y la pantalla ya
está tras `evaluacion.ver`, así que el evaluador también ve el toggle. Es deliberado: la
señal no es confidencial y un permiso de coordinador aparte añadiría un rol que hoy no
existe en el RBAC.

### 5. Pruebas

En `tests/Feature/HistorialEquivalenciasTest.php`, contra MySQL real como el resto de la
suite:

| Test | Qué protege |
|---|---|
| `test_el_toggle_lista_solo_los_cursos_con_criterio_dividido` | El filtro básico: un curso con dos destinos aparece, uno con un solo destino no. |
| `test_dos_planes_de_la_misma_carrera_no_son_divergencia` | La decisión código‑vs‑id. Mismo `codigo`, distinto `curso_usil_id` ⇒ **no** divergente. El test más importante. |
| `test_dos_carreras_destino_distintas_no_son_divergencia` | El límite por carrera destino. |
| `test_el_panel_informa_cuantos_criterios_hay` | El `criterios` del endpoint, incluido el `null` cuando no se manda carrera destino. |

## Fuera de alcance

- **Columna nueva en el Excel.** El filtro ya hace útil la descarga y las filas del mismo
  curso salen contiguas.
- **Detección por similitud en la pantalla del histórico.** Precio aceptado del enfoque.
- **Tocar `normaliza()` o `similitud()`.** Sostienen el motor entero; cambiarlas por esta
  función sería mover un cimiento para colgar un cuadro.
- **Que el coordinador fije un criterio como el correcto.** Eso es exactamente el catálogo
  curso↔curso que TI eliminó en BD‑07 entrando por otra puerta, y necesitaría aval
  explícito de TI.
- **Notas o comentarios sobre un caso divergente.** Sería estado nuevo que mantener; la
  base sigue siendo 100 % derivada de `simulacion_detalle`.
- **Rendimiento de `antecedentes()` y filtros muertos de `consulta()`**
  (`carrera_externa_id`, `curso_usil_id`, que el controlador nunca reenvía). Son
  fricciones reales detectadas en la revisión, pero independientes de esta funcionalidad.
