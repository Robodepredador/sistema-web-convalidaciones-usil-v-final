# Diseño — Carga de mallas externas por plantilla y selección de cursos de origen

**Fecha:** 2026-08-07
**Rama base:** `feat/cumplimiento-proceso-traslado-externo`
**Autor:** Frank Rodríguez

## Problema

La base de conocimiento solo sabe lo que ya se evaluó dentro del sistema. Una carrera
o una institución nuevas arrancan en cero, y no hay forma de alimentarla salvo esperando
a que pasen expedientes.

Se evaluó y **se descartó** la propuesta de un catálogo curso↔curso mantenido a mano
(ver «Alternativa descartada» al final). El cuello de botella real es otro y es medible:

| Dato | Real al 2026-08-07 |
|---|---|
| Instituciones externas registradas | 206 |
| Carreras externas registradas | 5.085 |
| **Mallas externas cargadas** | **1** |
| **Cursos externos en toda la base** | **41** |

El lado de origen está esencialmente vacío. Mientras siga así, el evaluador escribe el
nombre del curso a mano, el histórico guarda texto libre y las sugerencias por parecido
comparan cadenas contra cadenas.

Además, la única vía para cargar una malla externa hoy es **extracción con IA desde un
PDF**. Dirección pide no depender de IA para este proceso.

## Objetivo

1. Poder cargar la malla de una institución de origen **sin IA**, subiendo un Excel con
   formato fijo que provee el propio sistema.
2. Que, cuando la carrera de origen tenga malla cargada, el evaluador **elija el curso de
   una lista** en vez de teclearlo, quedando registrado qué curso concreto era.

Se entregan en ese orden. El paso 2 es lo que hace rentable al paso 1: sin él quedarían
mallas registradas que nadie consulta durante la evaluación.

## Contexto — lo que YA existe (no se modifica)

**Carga de malla externa** (`Equivalencias/Form.vue`, `MallaExternaController`):

1. Se sube un PDF a `POST /mallas-externas/extraer-ia`; la IA devuelve `{cursos: [...]}`.
2. El usuario **revisa la lista en pantalla**.
3. `POST /mallas-externas` guarda PDF + la lista como JSON en `cursos`.

El paso 3 recibe `cursos` como cadena JSON y **no sabe de dónde salió**: es el punto de
enganche natural para una segunda fuente. El paso 2 —la revisión humana— ya existe; con
Excel se revisa una transcripción en vez de una extracción.

**Plantillas de importación**: `MallaPlantillaExport` (malla USIL) genera un Excel de dos
hojas —«Cursos» con cabeceras y filas de ejemplo, e «Instrucciones» con reglas de
validación—. `MallaCursosImport` es genérico (`ToCollection` + `WithHeadingRow`): lee
cualquier hoja con fila de cabecera a una colección.

**`LectorMallaExcel` NO se reutiliza.** Interpreta el Excel institucional real de USIL
(busca la fila de cabecera con «Ciclo» y «Curso», extrae metadatos, descarta filas de
«Total», reconoce bloques de mención). Es específico de un formato conocido; aquí el
formato lo define el sistema y es plano.

**Esquema de `cursos_externos`**: `codigo` (nullable, 30), `nombre` (200, obligatorio),
`creditos` (nullable, decimal 4,1), `silabo_texto` (nullable). Cuelga de la malla externa.
Bastante más simple que el curso USIL.

**Versionado de mallas externas**: `anio`, `version`, `activa`. Al registrar una nueva se
desactivan las anteriores de esa carrera. **No hace falta nada nuevo** para el cambio de
plan.

**Guardado de la simulación**: `SimulacionController::store()` ya valida
`filas.*.curso_externo_id` como `nullable|integer` y lo persiste en `simulacion_detalle`.
`show()` lo lee de vuelta. **La tubería está completa de punta a punta.**

**`Autocomplete.vue`** admite `allowFree` (valores libres) y `creatable` (crear a partir
del texto escrito, con evento `create`). Cubre «elige de la lista o escribe la tuya» sin
componente nuevo.

## Paso 1 · Carga por plantilla

### 1.1 Plantilla descargable

Nuevo `MallaExternaPlantillaExport`, espejo simplificado del de USIL, con dos hojas:

- **«Cursos»**: cabeceras `codigo`, `nombre`, `creditos` + filas de ejemplo.
- **«Instrucciones»**: qué es obligatorio, y que las filas con error se omiten y se
  reportan indicando el número de línea.

Solo tres columnas porque es lo único que `cursos_externos` guarda. No se pide ciclo,
horas ni carácter: no hay dónde ponerlos y pedir datos que se tiran es una invitación a
que la gente abandone la plantilla.

Ruta: `GET /mallas-externas/plantilla`, permiso `mallas_externas.gestionar`.

### 1.2 Lector

Nuevo `MallaExternaController::previsualizarExcel()`:

- Recibe `archivo` (`xlsx`/`xls`/`csv`, máx. 5 MB).
- Lee con `MallaCursosImport` (se reutiliza tal cual). Su docblock menciona `ciclo` entre
  las columnas esperadas, pero eso es documentación de su uso en la malla USIL, no una
  restricción: `WithHeadingRow` mapea las cabeceras que encuentre y aquí simplemente no
  habrá `ciclo`.
- Descarta filas sin `nombre`; trunca `codigo` a 30 y `nombre` a 200; `creditos` solo si
  es numérico, en línea con lo que ya hace `store()` con la salida de la IA.
- Devuelve `{cursos: [{codigo, nombre, creditos}], omitidas: [{linea, motivo}]}`.

**La forma de `cursos` es idéntica a la que devuelve `extraerIA`**, de modo que la
pantalla y el guardado no distinguen la fuente.

Ruta: `POST /mallas-externas/previsualizar`, mismo permiso.

`omitidas` es el añadido frente a la IA: con un archivo que llena una persona, decir *qué
línea se descartó y por qué* es la diferencia entre corregirlo y volver a intentarlo a
ciegas.

### 1.3 Pantalla

En `Equivalencias/Form.vue`, junto al botón de IA:

- Enlace «Descargar plantilla».
- Botón «Subir Excel de cursos» que llama a `previsualizarExcel` y vuelca el resultado en
  `cursosExtraidos` — la **misma** variable que llena la IA.
- Si vienen `omitidas`, se listan como aviso encima de la tabla de revisión.

La tabla de revisión y el guardado **no se tocan**.

### 1.4 Lo que se mantiene

- **PDF obligatorio.** Es la fuente oficial; el Excel es una transcripción. Sin el PDF no
  hay contra qué contrastar una transcripción errónea, y esto acaba en un memorándum.
- **La IA se queda** como segundo botón. «No depender de» no es «prohibir»: quitarla no
  ahorra mantenimiento y cierra una salida útil.

## Paso 2 · Selección del curso de origen

### 2.1 La distinción que hay que respetar

Existe ya un dato de entrada `cursosOrigen` en la pantalla de simulación
(`SimulacionController::crear()`, hoy `[]` fijo). **No es el sitio para la malla**: son
*las filas con las que arranca la pantalla*, es decir los cursos que el postulante
cursó. Llenarlo con la malla haría que la simulación arrancara afirmando que el alumno
llevó las 60 materias del plan.

Son dos conceptos distintos y necesitan dos datos distintos:

| Concepto | Qué es | Estado |
|---|---|---|
| `cursosOrigen` | Lo que el alumno cursó | Ya existe, arranca vacío |
| `cursosMallaOrigen` | De dónde puede elegir | **Nuevo** |

### 2.2 Cambios

**`SimulacionController::crear()`** pasa `cursosMallaOrigen`: los cursos de la malla
**activa** de la carrera externa del postulante, como `[{id, nombre, creditos}]`. Vacío si
no hay malla activa.

**`Simular.vue`**: `filaBase()` gana `curso_externo_id: c.curso_externo_id ?? null`, y
`agregarFila()` lo propaga. Es lo único que faltaba para que el identificador llegue al
guardado, que ya lo espera.

**`MapeoUsilMatch.vue`**: el alta en línea de curso externo pasa de `<input>` de texto a
`Autocomplete` con `allowFree` y `creatable` sobre `cursosMallaOrigen`.

- Elegir de la lista → emite `{nombre, creditos, curso_externo_id}` (créditos precargados
  de la malla).
- Escribir libre → emite `{nombre, creditos, curso_externo_id: null}`, exactamente como
  hoy.

Sin malla activa, la lista va vacía y el `Autocomplete` con `allowFree` se comporta como
el campo de texto actual.

### 2.3 El texto libre no se toca

El récord del postulante es lo que cursó de verdad: puede venir de un plan anterior,
traer electivos retirados o materias de otra carrera. Obligar a elegir de la lista dejaría
inevaluable justo el caso raro, que es el que más ayuda necesita. **La lista es una
comodidad, no una reja.**

## Fuera de alcance

- **Agrupar el histórico por `curso_externo_id`** en lugar de por nombre. Es lo que
  permitiría a la detección de criterio dividido unir «Matemática 1» con «Matemática I»
  (ver `2026-08-07-divergencia-criterio-equivalencias-design.md`). Hoy no tendría efecto
  —todo el histórico es texto— y sí rompería lo ya registrado. Se replantea cuando haya
  volumen de filas con identificador.
- **`CatalogoController::agregarCursoExterno`**, que hace `firstOrCreate` sobre la malla
  activa: agregar un curso suelto al récord de un postulante lo incorpora a la malla
  oficial de la institución. Es un problema real, pero vive en otra pantalla y no se
  invoca durante la evaluación (allí las filas son locales). Se anota, no se arregla aquí.
- **Aceptar el Excel que mande cada universidad** en su propio formato. Decidido: solo la
  plantilla fija. Se revisará si transcribir resulta ser el cuello de botella medido.
- **Retirar la IA.**
- **Ciclo, horas y carácter del curso externo**: no hay columnas donde guardarlos.

## Pruebas

**Paso 1**
| Test | Qué protege |
|---|---|
| La plantilla se descarga y exige el permiso | Ruta y RBAC |
| Un Excel válido devuelve los cursos en la forma de `extraerIA` | El contrato con la pantalla y el guardado |
| Las filas sin nombre se omiten y se reportan con su número de línea | Que el usuario pueda corregir |
| Un archivo que no es Excel se rechaza | Validación de entrada |

**Paso 2**
| Test | Qué protege |
|---|---|
| `cursosMallaOrigen` llega solo si la carrera de origen tiene malla activa | El dato nuevo |
| Una fila elegida de la lista guarda `curso_externo_id` | Que la tubería quede conectada |
| Una fila escrita a mano lo guarda nulo | Que el texto libre siga funcionando |

## Alternativa descartada

**Catálogo curso↔curso mantenido a mano** (cada curso USIL con sus equivalentes por
institución y carrera). Es la tabla `equivalencias` eliminada en BD-07 con confirmación
de TI. Descartada por tres razones independientes:

1. **Volumen**: 74 cursos de una sola carrera × 5.085 carreras externas ≈ 376.000
   anotaciones, para una carrera de 41. Un catálogo a medio llenar es peor que vacío: no
   se distingue un hueco de un «no equivale».
2. **No hay a qué apuntar**: con 1 malla externa cargada y 41 cursos externos, el otro
   extremo del par no existe en el 99,9 % de los casos. La precondición no está cumplida.
3. **Envejece**: afirma algo sobre el presente y caduca al cambiar cualquiera de las dos
   mallas, sin responsable de mantenimiento en el RBAC actual. El histórico registra
   decisiones tomadas, que no caducan.

Reponerlo revertiría un hallazgo de auditoría y requiere decisión explícita de TI. En
cualquier caso exigiría cargar antes las mallas externas, que es lo que hace este diseño.
