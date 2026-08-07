# Diseño — Catálogo de equivalencias por malla: mapeo del coordinador y sugerencia en la simulación

**Fecha:** 2026-08-07
**Rama base:** `feat/cumplimiento-proceso-traslado-externo`
**Autor:** Frank Rodríguez
**Prerrequisito:** `2026-08-07-malla-externa-sin-ia-design.md` (fases 1 y 2)

## Problema

La base de conocimiento solo sabe lo que ya se evaluó. El coordinador, en cambio, puede
saber de antemano cómo convalida una carrera externa concreta contra una carrera USIL —
y hoy no tiene dónde dejarlo escrito. Ese criterio se pierde y se vuelve a reconstruir
expediente por expediente.

## Objetivo

1. Que el coordinador registre, en una sesión, las equivalencias entre una malla externa
   y la malla de una carrera USIL, con duplicados rechazados.
2. Que en la simulación, al elegir un curso de origen, ese criterio aparezca como primera
   sugerencia junto al histórico — sin asignar nada por su cuenta.

## Relación con BD-07

Esto **es** un catálogo curso↔curso, del tipo que se eliminó en BD-07 con confirmación de
TI. Se documenta abiertamente en vez de disimularlo.

**TI levantó la restricción el 2026-08-07**, así que no hay bloqueo para construirlo. El
resto de esta sección se conserva igual: explica por qué este artefacto no repite el
defecto del que se eliminó, y esa justificación sigue haciendo falta cuando alguien lea el
informe de auditoría y pregunte.

Lo que cambia respecto de la tabla eliminada, y por qué el argumento en contra ya no
aplica:

| | Tabla de BD-07 | Esta |
|---|---|---|
| Se ancla en | `carrera_externa_id` (la carrera) | **La versión de malla** de cada lado |
| Al cambiar un plan | Seguía aplicando en silencio al plan nuevo | Deja de coincidir y se ve |
| Se poblaba | Exhaustivamente, curso × institución × carrera | A demanda, un par de mallas por sesión |
| Precondición | Ninguna: no había mallas externas cargadas | La carga de la malla es el paso 3 del propio flujo |

Anclarse en la carrera y no en la versión de la malla **era el defecto de la tabla
eliminada**. Corregido eso, el artefacto es distinto.

## Contexto — lo que YA existe (no se modifica)

- **RN-02: una sola malla activa por carrera USIL**, garantizada por índice único con
  columna generada (`2026_07_13_000001`). `ConvalidacionEngine::mallaDeCarrera()` ya
  resuelve «la malla de esta carrera» con esa convención, y la simulación la usa. Por eso
  el formulario **no pide el plan**: pide la carrera.
- **Cursos externos versionados**: `cursos_externos.malla_externa_id` es obligatorio, con
  único `(malla_externa_id, nombre)`. Al registrar una malla externa nueva se desactivan
  las anteriores de esa carrera.
- **Jerarquías para el formulario en cascada**: institución → carrera externa
  (`CatalogoController::carrerasExternas`), y unidad de negocio → facultad → carrera USIL.
- **`Autocomplete.vue`** con `allowFree` y `creatable`.
- **`AuditoriaService::registrar()`** con verbos `crear|editar|eliminar|...` y payload de
  valores antiguos/nuevos.
- **`AlcanceService::carrerasVisibles()`** (RF-40): acota por carrera USIL asignada.
- **La regla 1 a 1 ya existe en la simulación**: avisa cuando un curso USIL queda asignado
  más de una vez.

## La regla

> Dentro de un par (malla externa, malla USIL), cada curso externo equivale como máximo a
> un curso USIL, y cada curso USIL a como máximo un curso externo.

Es la misma regla que la simulación ya exige, así que el catálogo **nunca puede proponer
algo que después el sistema rechace**.

Que un curso USIL **no tenga equivalente es el resultado normal**, no un trabajo a medio
hacer: la mayoría de carreras externas no cubren la malla completa. La interfaz no debe
presentarlo como pendiente — si sugiere que falta algo, alguien forzará equivalencias
malas para «terminar», y esas acaban en un memorándum.

## Fase 3 · Registro del mapeo

### 3.1 Tabla `equivalencias_malla`

| Columna | Nota |
|---|---|
| `curso_externo_id` → `cursos_externos` | El par |
| `curso_usil_id` → `cursos_usil` | El par |
| `malla_externa_id` → `mallas_externas` | Derivable, pero **necesaria como clave de índice**: MySQL no indexa a través de un join |
| `malla_usil_id` → `mallas_curriculares` | Íd. |
| `usuario_id` → `usuarios` | Quién declaró el criterio |

Las dos columnas de malla son redundantes y **no pueden desincronizarse**: un curso nunca
cambia de malla, se crea dentro de una.

Índices únicos — son la validación de duplicados:

- `(curso_externo_id, malla_usil_id)` — un curso externo apunta a un solo curso USIL **por
  malla destino**. El mismo curso puede convalidar distinto contra otra carrera USIL, que
  es legítimo.
- `(curso_usil_id, malla_externa_id)` — un curso USIL recibe un solo curso externo **por
  malla de origen**.

La interfaz avisa antes, pero **la garantía está en el esquema**: dos coordinadores
guardando a la vez no pueden crear el duplicado.

**Sin `softDeletes`, a diferencia del resto del proyecto.** Un índice único convive mal
con el borrado lógico: la fila borrada sigue ocupando la combinación y volver a crear el
mismo par fallaría. Es exactamente el problema que `2026_07_13_000001` tuvo que arreglar
en `mallas_curriculares` con una columna generada, y no vale la pena repetirlo aquí. El
borrado se audita con `AuditoriaService::registrar('eliminar', ...)`, que guarda el par en
el payload. Un mapeo es una **declaración vigente**, no un registro histórico: la historia
vive en `simulacion_detalle` y no se toca.

### 3.2 El asistente

Rutas bajo `/mapeo-mallas`. **No** bajo `/equivalencias`: ese prefijo ya significa «Mallas
Externas» por herencia del módulo anterior, y añadirle un vecino casi homónimo sería
cruel con quien lea las rutas.

| Paso | Qué pide |
|---|---|
| 1 · Origen | Institución externa → carrera externa (cascada) |
| 2 · Destino | Facultad USIL → carrera USIL. El sistema toma su malla vía `ConvalidacionEngine::mallaDeCarrera()`, la misma convención que usa la simulación. Si la carrera no tiene ninguna, el asistente se detiene ahí con el motivo — mapear contra una malla inexistente no significa nada |
| 3 · Malla de origen | Si la carrera externa ya tiene malla activa, **se usa y se sigue**. Si no hay, se sube por plantilla (fase 1), se valida y se revisa |
| 4 · Mapeo | Recorriendo los cursos USIL, se asigna a cada uno su equivalente externo **o ninguno**. Cada par se guarda al confirmarlo (ver abajo): no hay paso final de guardado |

**El paso 3 no puede exigir el archivo siempre.** El caso más común es mapear la misma
malla externa contra varias carreras USIL; si el asistente pidiera el archivo en cada
pase, al registrarlo se desactivaría la versión anterior y **el primer mapeo quedaría
apuntando a una malla desactivada**. Solo se pide archivo cuando no hay malla activa, o
cuando el coordinador declara explícitamente que registra una versión nueva.

**Guardado par a par, no al final.** Cada equivalencia confirmada se persiste en el acto
(`POST /mapeo-mallas`), y quitarla la borra (`DELETE /mapeo-mallas/{id}`). Motivos: mapear
40 cursos y perderlos por un cierre de navegador es inaceptable; el duplicado se detecta
al instante contra el índice; y «volver cuantas veces quiera» deja de necesitar un estado
de borrador — al reentrar en el mismo par de mallas, lo guardado ya está ahí.

Al confirmar un par, el curso externo usado **sale del grupo disponible** para el resto de
cursos USIL, que es cómo se hace visible la regla 1 a 1.

**Pantalla índice** en `GET /mapeo-mallas`: los pares de mallas ya mapeados con su
cantidad de equivalencias, y el botón para iniciar uno nuevo. Es la puerta de entrada y
además responde «¿qué llevo mapeado?» sin tener que recordarlo.

### 3.3 No se reutiliza `MapeoUsilMatch`

Es la decisión cara y conviene justificarla. Ese componente son ~510 líneas atadas al
expediente: clasificación de cursos, notas de origen, exclusiones, botones de IA, panel de
antecedentes, créditos reconocidos, líneas de conexión. **Nada de eso existe al mapear dos
mallas.** Doblarlo para servir a dos amos lo convertiría en el archivo más frágil del
proyecto.

La pantalla nueva es menor: dos listas con buscador, clic‑clic‑confirmar y bandeja de
pares guardados.

### 3.4 Permisos

**Se concede `mallas_externas.gestionar` al rol Coordinador de Carrera.** Hoy no lo tiene,
y hay un comentario en `Permiso::POR_ROL` que lo justifica explícitamente («no registra
mallas de otras instituciones»); **ese comentario también debe actualizarse**, no solo el
array. Decisión tomada: el coordinador ejecuta el flujo completo sin depender de otro rol.

Consecuencia que conviene tener presente: `mallas_externas.gestionar` **no tiene alcance
por carrera**, así que el coordinador podrá registrar mallas de cualquier institución, no
solo de las que le tocan. Es más de lo estrictamente necesario; se acepta a cambio de no
inventar un permiso nuevo.

El **mapeo** va con `evaluacion.editar` —cuya descripción en el catálogo es literalmente
«Registrar/editar equivalencias y mapeo»— **más alcance RF-40 sobre la carrera USIL
destino**: un coordinador mapea solo hacia sus carreras asignadas.

## Fase 4 · La sugerencia en la simulación

### 4.1 Las dos fuentes se mantienen separadas

`HistorialEquivalenciasService` seguirá siendo **solo derivado** de `simulacion_detalle`:
no consulta el catálogo. El catálogo se lee aparte y **el controlador junta las dos
respuestas**.

No es purismo: es lo que permite **detectar que se contradicen**. Si una fuente absorbiera
a la otra, la contradicción dejaría de ser observable, y es justo la señal más valiosa.

El docblock de `HistorialEquivalenciasService` afirma hoy que el catálogo «se eliminó y
esto no lo reintroduce». Debe actualizarse: el histórico sigue siendo derivado; el
catálogo declarado vive aparte y se combina más arriba.

### 4.2 Cambios

`GET /simulaciones/antecedentes` acepta `curso_externo_id` (opcional) y devuelve una clave
más:

```
{ antecedentes: [...], criterios: n|null, catalogo: {curso_usil_id, curso_usil, codigo_usil}|null }
```

`catalogo` sale de buscar el par `(curso_externo_id, malla_usil_id)` en
`equivalencias_malla`. Es `null` si no hay declaración.

La `malla_usil_id` **no se pide en la petición**: se deriva de la `carrera_usil_id` que el
endpoint ya recibe, con `mallaDeCarrera()`. Así el permiso y el alcance se siguen
comprobando sobre la carrera —como hoy— y no hay un identificador de malla llegando desde
el cliente que haya que volver a autorizar.

En el panel del evaluador: **el catálogo primero**, marcado como criterio declarado del
coordinador; el histórico debajo con sus veces y sus memorandos. Pulsar cualquiera de los
dos solo **mueve la selección**, igual que hoy: la equivalencia la confirma el evaluador.

**Contradicción**: si el curso USIL del catálogo difiere del antecedente más frecuente, se
dice. Es criterio dividido otra vez, ahora entre lo declarado y lo practicado, y merece la
misma redacción de nota y no de alarma.

### 4.3 La dependencia que no se puede saltar

El catálogo se busca por `curso_externo_id`. Hoy el evaluador escribe el nombre a mano y
ese identificador queda nulo en todo el histórico, así que **sin la fase 2 el catálogo no
se puede consultar**. No es reordenable: la fase 2 es lo que conecta las dos mitades.

No se busca por nombre como plan B: volveríamos al emparejamiento difuso justo donde
tenemos un identificador exacto disponible.

## Orden de entrega

| Fase | Qué | Spec |
|---|---|---|
| 1 | Subir malla externa por plantilla | `malla-externa-sin-ia` |
| 2 | El evaluador elige de la malla → curso identificado | `malla-externa-sin-ia` |
| 3 | Asistente de mapeo + tabla + duplicados | Este |
| 4 | El catálogo aparece en la simulación | Este |

## Fuera de alcance

- **Equivalencia parcial** (varios cursos externos cubriendo uno USIL). Decidido: 1 a 1
  estricto. La tabla eliminada tenía `tipo_equivalencia`; aquí no.
- **Arrastrar mapeos al activar un plan USIL nuevo.** Los mapeos quedan atados al plan
  anterior y dejan de aparecer — el versionado funcionando. Arrastrarlos afirmaría que el
  curso del plan nuevo equivale a lo mismo sin que nadie lo haya mirado, y el plan cambió
  por algo. Re-mapear es trabajo real y también una revisión legítima.
- **Que el catálogo alimente `asignacionOptima()` o la IA.** Solo se muestra.
- **Agrupar el histórico por `curso_externo_id`.** Igual que en el spec anterior: se
  replantea cuando haya volumen de filas con identificador.
- **Exportar el mapeo a Excel.**

## Pruebas

**Fase 3**
| Test | Qué protege |
|---|---|
| Guardar un par lo persiste contra las dos mallas | El camino feliz |
| Repetir el mismo curso externo contra otro curso USIL de la misma malla destino se rechaza | Índice único 1 |
| Repetir el mismo curso USIL con otro curso externo de la misma malla origen se rechaza | Índice único 2 |
| El mismo curso externo SÍ puede mapearse contra otra carrera USIL | Que la regla no sea más estricta de la cuenta |
| Borrar un par y volver a crearlo funciona | Que la ausencia de `softDeletes` es deliberada |
| Un coordinador no puede mapear hacia una carrera fuera de su alcance | RF-40 |
| Reentrar en el mismo par de mallas muestra lo ya guardado | «Cuantas veces desee» |

**Fase 4**
| Test | Qué protege |
|---|---|
| Con `curso_externo_id` y mapeo declarado, el endpoint devuelve `catalogo` | El camino feliz |
| Sin `curso_externo_id`, `catalogo` es `null` y los antecedentes siguen igual | Que la fase 4 no rompa lo existente |
| Catálogo e histórico discrepantes se marcan como contradicción | La señal |
