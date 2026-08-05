# Plan — Cumplimiento del proceso de traslado externo

**Fecha:** 5 de agosto de 2026
**Origen:** auditoría del proceso de traslado externo contra la normativa USIL
(Reglamento de Admisión set-2024, Reglamento de Estudios Pregrado Regular ago-2025 y
la página oficial de la modalidad).

## Alcance

**Dentro:** integridad del acto oficial, fidelidad de los documentos emitidos, nota mínima
aprobatoria, expediente documental, trazabilidad y datos personales.

**Fuera, por decisión de negocio (5 ago 2026):**

| Hallazgo | Decisión |
|---|---|
| Comparar créditos de origen contra los del curso USIL | No se consideran los créditos de la institución de origen: se convalida el curso, no los créditos. |
| Elegibilidad de 72 créditos / 4 periodos lectivos | Depende del mismo dato de origen: queda diferido. |
| Etapa propia del Director de Carrera (aprobar / observar / reasignar) | El Director evalúa con las mismas atribuciones que el Coordinador —que ya tiene en el sistema— hasta que se discuta a nivel de negocio. La entrevista se realiza fuera del sistema. |
| Tope máximo de créditos convalidables | Requiere la directiva interna de convalidaciones, no publicada. |

---

## Fase 1 — Congelar el acto oficial ya emitido

El memorándum es el documento con efecto académico. Hoy se puede alterar después de emitido.

1. **`Simulacion::estaConvalidada()`** — una sola definición de "expediente cerrado"
   (convalidación en estado `confirmada`). Hoy la comprobación vive suelta en `destroy()`.
2. **Bloquear la mutación** en `SimulacionController::update()` y `toggleDetalle()`, y
   reescribir `destroy()` sobre el mismo helper.
3. **`memorandumPdf()` sirve el PDF archivado** (`memorandum_pdf_path`) en lugar de
   re-renderizarlo con los datos del momento. Solo lo regenera si el archivo no existe.
4. **Un único número de memorándum**: el que se guarda es el que se imprime. Se congela al
   confirmar (incluye la unidad, que es configurable y no debe reescribir documentos ya
   emitidos). Migración de reencauce para las filas existentes.
5. **`toggleDetalle` exige motivo** y registra auditoría.
6. **`anular()` devuelve la simulación** a `generada`.
7. **UI:** el botón «Editar mapeo» desaparece cuando el expediente está convalidado.

## Fase 2 — Fidelidad de los documentos

8. **Créditos sin redondear** en el memorándum y en la preconvalidación. La BD guarda
   `decimal(4,1)`; `number_format(…, 0)` imprime 4 donde hay 3.5.
9. **Fecha de revisión estable**: la de la evaluación, no la de la impresión.
10. **Nota de origen en el memorándum**: sin ella el documento no evidencia que el curso
    estaba aprobado.

## Fase 3 — Nota mínima aprobatoria (Art. 15, escala vigesimal, nota 11)

11. **Validación en el servidor** de las filas convalidables: si la nota de origen es
    numérica y queda por debajo de la mínima, se rechaza. Las notas no numéricas
    («APROBADO», «A») no se bloquean: no hay criterio para juzgarlas.
12. **Piso normativo**: en escala `0-20` la nota mínima no puede fijarse por debajo de 11.

## Fase 4 — Expediente documental

13. **Checklist oficial completo**: se agregan `dni` y `solicitud` (formato USIL), y la
    constancia pasa a llamarse lo que es, «constancia de primera matrícula».
14. **Aprobar exige expediente completo**. La vía provisional del proceso real (récord de
    notas con declaración jurada) se modela como aprobación provisional: se permite, pero
    queda marcada y justificada en el expediente.

## Fase 5 — Trazabilidad, estados y datos personales

15. **Licenciamiento SUNEDU** en las instituciones de origen (`licenciada`, `resolucion`).
16. **Máquina de estados del postulante**: hoy `PATCH /postulantes/{id}/estado` acepta
    cualquier salto (de `nuevo` a `matriculado` sin evaluar).
17. **Lista de no convalidables**: se elimina la lista fija del código —duplica la tabla
    gestionable y no se puede desactivar desde Configuración— y la coincidencia pasa a ser
    por palabra completa (hoy «cartera» cae por contener «arte»).
18. **Consentimiento de tratamiento de datos** (Art. 15 Reglamento de Admisión, Ley 29733):
    se registra al dar de alta al postulante y habilita el envío del récord al proveedor de
    IA. Sin consentimiento, la extracción automática no procede.

---

## Verificación

Cada fase deja pruebas de regresión en `tests/Feature/`. Al cierre: suite completa en verde
contra MySQL real y `pint --test` limpio.
