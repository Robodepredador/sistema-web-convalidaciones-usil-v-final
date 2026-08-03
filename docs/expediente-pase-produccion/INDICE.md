# Expediente de Pase a Producción — Sistema de Convalidaciones USIL

Índice de entregables. Actualizar a medida que se completen.

## Estructura documental acordada

| Doc | Título | Audiencia | Estado |
|-----|--------|-----------|--------|
| **1** | Informe Ejecutivo de Cierre de la Fase de Construcción | Alta Dirección | ✅ Generado |
| **2** | Expediente Técnico de Pase a Producción | TI / Negocios Digitales | Pendiente |
| **3** | Presentación de sustentación | Alta Dirección | Pendiente |

### Campos pendientes en el Documento 1

Marcados en **rojo entre corchetes** dentro del archivo. Son 4:

1. Denominación del comité receptor (§8.3)
2. Fecha de la sesión (§8.3)
3. Fecha objetivo del pase a producción (§7.2)
4. Responsable de la emisión del reporte de indicadores (§7.3)

Las firmas se omitieron por indicación expresa. El contenido técnico no depende de estos campos.

## Anexos (§9 de los Lineamientos Técnicos)

| Anexo | Título | Estado |
|-------|--------|--------|
| **A0** | Catálogo Maestro de Requisitos y Matriz de Trazabilidad | ✅ Generado |
| A1 | Documento funcional (base: ERS actualizada) | Pendiente |
| A2 | Documento técnico (base: Manual_Tecnico_USIL.docx) | Pendiente |
| A3 | Diagrama de arquitectura | ✅ D-01 en Lucid |
| A4 | Manual de usuario por rol | Pendiente |
| **A5** | Diccionario de Datos (31 tablas) | ✅ Generado |
| A6 | RUP consolidado (histórico de ingeniería) | Pendiente |

## Diagramas en Lucid

| ID | Diagrama | Enlace |
|----|----------|--------|
| D-01 | Arquitectura del sistema (8 capas) | https://lucid.app/lucidchart/3711d92c-ee65-473a-afeb-204cfd6fe5ad/edit |
| D-02 | Despliegue en producción | https://lucid.app/lucidchart/103931a4-c747-48df-b44c-694417f7228e/edit |
| D-05 | Modelo Entidad-Relación (31 tablas) | https://lucid.app/lucidchart/422466dd-38db-4f1a-9604-f4cd6c41ed76/edit |
| D-07 | Modelo RBAC de dos ejes | https://lucid.app/lucidchart/6c38d36d-c8eb-4025-bea0-6ad202de5666/edit |
| D-03 | Proceso actual AS-IS | Pendiente — requiere validación del proceso |
| D-04 | Proceso propuesto TO-BE | Pendiente — requiere validación del proceso |
| D-06 | Casos de uso (8 actores) | Pendiente |
| D-08 | Secuencia end-to-end | Opcional |

## Decisiones tomadas

1. **Métricas retiradas.** No se cuantifican beneficios sin evidencia. Se sustituye por un
   Acuerdo de Medición de Beneficios: línea base cero hoy, medición real a 90 días con datos
   del propio sistema (`auditoria_log` + timestamps del expediente).
2. **API REST (§5):** se solicita excepción formal, argumentando sobre la tercera viñeta
   ("estructura preparada para integración"), que el sistema sí cumple mediante su capa de servicios.
3. **Accesibilidad:** se audita y corrige antes del pase, generando el anexo de evidencia.

## Convenciones editoriales

- Numeración decimal de hasta 4 niveles (`1.` / `1.1` / `1.1.1` / `1.1.1.1`).
- **Ningún título sin texto propio.** Todo título lleva al menos un párrafo antes de tablas o figuras.
- Un título nunca es seguido de inmediato por otro título.
- Tablas y figuras numeradas por capítulo (`Tabla 3.2`, `Figura 5.1`), con título arriba en
  tablas, abajo en figuras, y línea de **Fuente** al pie.
- Toda tabla o figura se cita en el cuerpo antes de aparecer.

## Trazabilidad de la generación

El Anexo A5 y el diagrama D-05 se generan por extracción automática del catálogo de metadatos
de la base de datos (`information_schema`), no por transcripción manual. Los scripts viven en el
scratchpad de la sesión (`schema.php`, `erd.py`, `dicc.js`, `catalogo.js`). Si el esquema cambia,
ambos artefactos se regeneran y no pueden divergir de la implementación.
