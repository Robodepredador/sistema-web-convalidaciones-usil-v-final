<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import MapeoUsilMatch from '../../Components/MapeoUsilMatch.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import VolverA from '../../Components/VolverA.vue';

// El Coordinador evalúa pero no ve Convalidaciones: el popup no debe ofrecerle
// un enlace que le devolvería un 403.
const permisos = computed(() => usePage().props.auth?.user?.permisos ?? []);
const veConvalidaciones = computed(() => permisos.value.includes('*') || permisos.value.includes('convalidacion.ver'));

const props = defineProps({
    postulante: Object,
    poolUsil: Array,
    cursosOrigen: Array,           // filas con las que arranca la pantalla: lo que el alumno cursó
    cursosMallaOrigen: Array,      // catálogo del que elegir: la malla vigente de la carrera de origen
    documentos: Array,
    tieneMalla: Boolean,
    noConvalidar: String,
    ia: Object,
    edicion: { type: Object, default: null },   // simulación a editar (o null para nueva)
    simulacionesPrevias: Array,
});

// ponytail: IA apagada en el modo manual (pedido, temporal). En true vuelven a
// aparecer «Cargar cursos automáticamente» y «Sugerir con IA». El modo Asistida
// no se toca: ahí la IA es el flujo.
const IA_EN_MANUAL = false;

const editando = !!props.edicion;
// Al editar una simulación con IA, se entra al pipeline para volver a elegir el mapeo.
const metodo = ref(props.edicion?.metodo ?? 'manual');   // 'manual' | 'ia'
const escala = ref(props.edicion?.escala_notas ?? '0-20');
const notaMinima = ref(props.edicion?.nota_minima ?? 11);
const universidadOrigen = ref(props.edicion?.universidad_origen ?? props.postulante.institucion ?? '');
const observaciones = ref(props.edicion?.observaciones ?? '');
const procesando = ref(false);
const mensaje = ref(null);              // { tipo, texto } → se muestra como toast arriba a la derecha

// El toast se oculta solo: los "ok" a los 4 s, los errores a los 6 s (dan más tiempo de lectura).
let mensajeTimer = null;
watch(mensaje, (m) => {
    if (mensajeTimer) { clearTimeout(mensajeTimer); mensajeTimer = null; }
    if (m) mensajeTimer = setTimeout(() => { mensaje.value = null; }, m.tipo === 'ok' ? 4000 : 6000);
});

const TIPO_DOC = { certificado: 'Certificado de estudios', silabos: 'Sílabos', constancia: 'Constancia' };
// La escala es un dato de referencia de la solicitud (solo lectura en la cabecera).
const ESCALA_LABEL = { '0-20': '0 - 20', '0-100': '0 - 100', '0-5': '0 - 5' };
const escalaLabel = computed(() => ESCALA_LABEL[escala.value] ?? escala.value);
const documentoId = ref(props.documentos?.[0]?.id ?? '');
const documentoPath = ref(null);
const archivo = ref(null);
const onArchivo = (e) => { archivo.value = e.target.files[0] ?? null; };

// ---------------------------------------------------------------- filas / catálogo
// _uid: identidad estable en el cliente (clave de Vue y de las líneas de conexión);
// sin ella, dos cursos con el mismo nombre y sin curso_externo_id colisionan.
let uidSeq = 0;
const filaBase = (c = {}) => ({
    _uid: ++uidSeq,
    curso_externo_id: c.curso_externo_id ?? null,
    curso_origen_nombre: c.nombre ?? '',
    nota_origen: c.nota ?? '',
    creditos_origen: c.creditos ?? '',
    ciclo_origen: c.ciclo ?? '',
    clasificacion: c.clasificacion ?? 'convalidable',
    motivo: c.motivo ?? null,
    curso_usil_id: '',
    confianza: null,
    origen: 'manual',
});
// Al editar: se cargan las filas de la simulación existente; si no, los cursos de origen.
const filas = reactive(
    props.edicion?.filas?.length
        ? props.edicion.filas.map((f) => ({
            ...filaBase({
                curso_externo_id: f.curso_externo_id,
                nombre: f.curso_origen_nombre,
                nota: f.nota_origen,
                creditos: f.creditos_origen,
                ciclo: f.ciclo_origen,
                // En modo manual cada curso se evalúa por igual (todos convalidables);
                // solo el pipeline con IA conserva la clasificación extraída del récord.
                // Un descarte hecho a mano se conserva siempre: es una decisión
                // del evaluador sobre este expediente, no una lectura automática.
                clasificacion: props.edicion.metodo === 'ia' || f.motivo ? f.clasificacion : 'convalidable',
                motivo: f.motivo ?? null,
            }),
            curso_usil_id: f.curso_usil_id ?? '',
            confianza: f.confianza ?? null,
        }))
        : (props.cursosOrigen ?? []).map(filaBase)
);

const creditosPorId = computed(() => Object.fromEntries(props.poolUsil.map((p) => [p.id, p.creditos])));

const duplicados = computed(() => {
    const cont = {};
    filas.forEach((f) => { if (f.curso_usil_id) cont[f.curso_usil_id] = (cont[f.curso_usil_id] || 0) + 1; });
    return Object.keys(cont).filter((k) => cont[k] > 1).map(Number);
});

const resumen = computed(() => {
    const conv = filas.filter((f) => f.curso_usil_id && f.clasificacion === 'convalidable');
    const creditos = conv.reduce((s, f) => s + (Number(creditosPorId.value[f.curso_usil_id]) || 0), 0);
    return { total: filas.length, convalidados: conv.length, creditos };
});

// Píldoras de estado de la cabecera (aprobados / desaprobados / no convalidables).
const conteoEstados = computed(() => ({
    aprobados: filas.filter((f) => f.clasificacion === 'convalidable').length,
    desaprobados: filas.filter((f) => f.clasificacion === 'desaprobado').length,
    noConvalidables: filas.filter((f) => f.clasificacion === 'no_convalidable').length,
}));

// Cursos convalidables que quedaron SIN un curso USIL asignado (a revisar antes de guardar).
const filaSinAsignar = (f) => f.clasificacion === 'convalidable' && !f.curso_usil_id;
const sinAsignar = computed(() => filas.filter(filaSinAsignar).length);

// Clasificación de la preconvalidación en 3 grupos (Etapa 6).
const usilPorId = computed(() => Object.fromEntries(props.poolUsil.map((p) => [p.id, p.curso || p.label])));
const convalidadosLista = computed(() => filas.filter((f) => f.clasificacion === 'convalidable' && f.curso_usil_id));
const noConvalidadosLista = computed(() => filas.filter((f) => f.clasificacion === 'no_convalidable' || filaSinAsignar(f)));
const desaprobadosLista = computed(() => filas.filter((f) => f.clasificacion === 'desaprobado'));

// Descartar un curso de origen es una decisión del evaluador sobre ESTE
// expediente: no cambia la política de la carrera y exige su motivo, que es lo
// que el postulante leerá en el Excel. Reconsiderarlo lo devuelve al mapeo.
const marcando = ref(null);
const motivoDescarte = ref('');
const errorDescarte = ref('');

const abrirDescarte = (f) => { marcando.value = f; motivoDescarte.value = f.motivo ?? ''; errorDescarte.value = ''; };

const confirmarDescarte = () => {
    if (motivoDescarte.value.trim().length < 5) {
        errorDescarte.value = 'Indica el motivo (mínimo 5 caracteres).';

        return;
    }
    marcando.value.clasificacion = 'no_convalidable';
    marcando.value.motivo = motivoDescarte.value.trim();
    marcando.value.curso_usil_id = '';
    marcando.value = null;
};

const reconsiderar = (f) => { f.clasificacion = 'convalidable'; f.motivo = null; };
const tabPreconv = ref('conv');   // 'conv' | 'no' | 'desap'

// Nombre y créditos llegan desde la tarjeta editable en línea de MapeoUsilMatch (sin window.prompt).
// `curso_externo_id` viene solo cuando el curso se eligió de la malla de origen; escrito a
// mano llega nulo, y esa fila queda identificada únicamente por su nombre, como siempre.
const agregarFila = ({ nombre, creditos, curso_externo_id = null } = {}) => {
    if (!nombre || !String(nombre).trim()) return;
    filas.push(filaBase({ nombre: String(nombre).trim(), creditos, curso_externo_id }));
};
const quitarFila = (i) => filas.splice(i, 1);
const limpiarFilas = () => { filas.splice(0, filas.length); };

// ---- Importar cursos externos desde Excel (misma plantilla de mallas externas) ----
const importarDesdeExcel = async (file) => {
    procesando.value = true;
    mensaje.value = null;
    const formData = new FormData();
    formData.append('archivo', file);
    try {
        const { data } = await window.axios.post('/mallas-externas/previsualizar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        const cursosExcel = data.cursos || [];
        if (!cursosExcel.length) {
            mensaje.value = { tipo: 'error', texto: 'El archivo no tiene ningún curso con nombre.' };
            return;
        }
        // Deduplicar: no agregar cursos cuyo nombre ya esté en la bandeja.
        const norm = (s) => String(s ?? '').trim().toLowerCase();
        const existentes = new Set(filas.map((f) => norm(f.curso_origen_nombre)));
        let agregados = 0;
        cursosExcel.forEach((c) => {
            const nombre = String(c.nombre ?? '').trim();
            if (!nombre || existentes.has(norm(nombre))) return;
            existentes.add(norm(nombre));
            filas.push(filaBase({ nombre, creditos: c.creditos }));
            agregados++;
        });
        const omitidos = cursosExcel.length - agregados;
        const omitidosExcel = data.omitidas?.length || 0;
        let texto = `${agregados} curso(s) importados desde Excel`;
        if (omitidos) texto += ` · ${omitidos} ya estaban en la bandeja`;
        if (omitidosExcel) texto += ` · ${omitidosExcel} fila(s) omitidas del archivo`;
        texto += '.';
        mensaje.value = { tipo: 'ok', texto };
    } catch (e) {
        mensaje.value = { tipo: 'error', texto: e.response?.data?.message || 'No se pudo leer el archivo Excel.' };
    } finally {
        procesando.value = false;
    }
};

// ---------------------------------------------------------------- sugerencias de mapeo
const nombresConvalidables = () =>
    filas.filter((f) => f.clasificacion === 'convalidable' && f.curso_origen_nombre.trim())
         .map((f) => f.curso_origen_nombre.trim());

// Aplica el mapeo garantizando la regla 1‑a‑1: un curso USIL no se asigna dos veces.
// Los cursos de origen sin sugerencia única quedan SIN convalidar (columna vacía).
const aplicarMapa = (mapa) => {
    const usados = new Set();
    filas.forEach((f) => {
        if (f.clasificacion !== 'convalidable') return;
        const s = mapa[f.curso_origen_nombre.trim()];
        const id = s?.curso_usil_id ? Number(s.curso_usil_id) : null;
        if (id && !usados.has(id)) {
            f.curso_usil_id = id;
            f.confianza = s.confianza ?? null;
            usados.add(id);
        } else {
            // Sin sugerencia o el curso USIL ya fue tomado por otra fila → queda vacío.
            f.curso_usil_id = '';
            f.confianza = null;
        }
    });
};

// origen: 'ia' | 'similitud' — ambas alimentan el mismo panel de emparejamiento.
const sugerir = async (origenSugerencia) => {
    if (!props.postulante.carrera_destino_id) { mensaje.value = { tipo: 'error', texto: 'El postulante no tiene carrera destino.' }; return; }
    const cursos = nombresConvalidables();
    if (!cursos.length) { mensaje.value = { tipo: 'error', texto: 'No hay cursos convalidables para mapear.' }; return; }
    procesando.value = true; mensaje.value = null;
    try {
        const url = { ia: '/simulaciones/sugerir-ia', similitud: '/simulaciones/sugerir-similitud' }[origenSugerencia];
        const payload = { carrera_usil_id: props.postulante.carrera_destino_id, cursos };
        const { data } = await window.axios.post(url, payload);
        aplicarMapa(data.mapa || {});
        filas.forEach((f) => { if (f.confianza !== null || Object.prototype.hasOwnProperty.call(data.mapa || {}, f.curso_origen_nombre.trim())) f.origen = origenSugerencia; });
        mensaje.value = { tipo: 'ok', texto: { ia: 'Sugerencias de IA aplicadas.', similitud: 'Mapeo por similitud aplicado.' }[origenSugerencia] };
    } catch (e) {
        mensaje.value = { tipo: 'error', texto: e.response?.data?.message || 'No se pudo generar la sugerencia.' };
    } finally { procesando.value = false; }
};

// ---------------------------------------------------------------- antecedentes del histórico
// Al seleccionar un curso de origen se consulta cómo se resolvió antes ese mismo
// curso. Es evidencia para el evaluador: no asigna nada ni altera las filas.
const antecedentes = ref([]);
const criteriosAntecedentes = ref(null);   // ≥2 = el curso se ha resuelto de formas distintas
const catalogoDeclarado = ref(null);       // criterio del coordinador para este curso
const cargandoAntecedentes = ref(false);
const cacheAntecedentes = new Map();   // el evaluador vuelve sobre los mismos cursos al comparar
let peticionAntecedentes = 0;

const SIN_ANTECEDENTES = { antecedentes: [], criterios: null, catalogo: null };
const aplicarAntecedentes = (r) => {
    antecedentes.value = r.antecedentes;
    criteriosAntecedentes.value = r.criterios;
    catalogoDeclarado.value = r.catalogo;
};

// La clave de caché incluye el id: el mismo nombre elegido de la malla y tecleado a
// mano dan respuestas distintas, porque solo el primero puede traer catálogo.
const buscarAntecedentes = async (seleccion) => {
    const nombre = seleccion?.nombre ?? null;
    const cursoExternoId = seleccion?.curso_externo_id ?? null;
    const token = ++peticionAntecedentes;
    const clave = `${cursoExternoId ?? ''}|${nombre ?? ''}`;

    if (!nombre) { aplicarAntecedentes(SIN_ANTECEDENTES); cargandoAntecedentes.value = false; return; }
    if (cacheAntecedentes.has(clave)) { aplicarAntecedentes(cacheAntecedentes.get(clave)); cargandoAntecedentes.value = false; return; }

    cargandoAntecedentes.value = true;
    try {
        const { data } = await window.axios.get('/simulaciones/antecedentes', {
            params: {
                curso: nombre,
                carrera_usil_id: props.postulante.carrera_destino_id,
                carrera_externa_id: props.postulante.carrera_externa_id,
                curso_externo_id: cursoExternoId,
            },
        });
        const r = { antecedentes: data.antecedentes ?? [], criterios: data.criterios ?? null, catalogo: data.catalogo ?? null };
        cacheAntecedentes.set(clave, r);
        // Una respuesta lenta no debe pisar la selección que el usuario ya cambió.
        if (token === peticionAntecedentes) aplicarAntecedentes(r);
    } catch {
        // El histórico es una ayuda opcional: si falla, el emparejamiento manual sigue igual.
        if (token === peticionAntecedentes) aplicarAntecedentes(SIN_ANTECEDENTES);
    } finally {
        if (token === peticionAntecedentes) cargandoAntecedentes.value = false;
    }
};

// ================================================================ PIPELINE CON IA
// Trazos de Heroicons (outline, viewBox 24). Se guarda solo el `d` porque el <svg>
// que los envuelve es común a los seis pasos.
const ICONO_HECHO = 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z';
const PASOS_IA = [
    { n: 1, label: 'Recepción', d: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3' },
    { n: 2, label: 'Validación documental', d: 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z' },
    { n: 3, label: 'Extracción', d: 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z' },
    { n: 4, label: 'Aprobados', d: 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M4.5 6.75h.008v.008H4.5V6.75Zm0 5.25h.008v.008H4.5V12Zm0 5.25h.008v.008H4.5v-.008Z' },
    { n: 5, label: 'Mapeo USIL', d: 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244' },
    { n: 6, label: 'Preconvalidación', d: 'M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 1 13.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375M9 15l2.25 2.25L15 12' },
];
// Al editar una simulación IA, se abre directamente en la etapa de Mapeo para re-elegir.
const pasoIA = ref(editando && props.edicion?.metodo === 'ia' ? 5 : 1);
const expediente = ref(editando ? `EXP-EDIT-${props.edicion?.id ?? ''}` : '');
const fechaRecepcion = ref('');
const extraccion = ref(null);           // { estudiante, institucion, aprobados, desaprobados, no_convalidables }

const docSeleccionado = computed(() => props.documentos?.find((d) => d.id === documentoId.value) ?? null);

const parseNota = (v) => {
    const n = parseFloat(String(v ?? '').replace(',', '.'));
    return Number.isNaN(n) ? null : n;
};
const aprobadosValidados = computed(() => (extraccion.value?.aprobados ?? [])
    .filter((c) => { const n = parseNota(c.nota); return n !== null && n >= Number(notaMinima.value); }));
const aprobadosFuera = computed(() => (extraccion.value?.aprobados ?? [])
    .filter((c) => { const n = parseNota(c.nota); return n === null || n < Number(notaMinima.value); }));

const iniciarPipeline = () => {
    const f = new Date();
    const pad = (x) => String(x).padStart(2, '0');
    const stamp = `${f.getFullYear()}${pad(f.getMonth() + 1)}${pad(f.getDate())}-${pad(f.getHours())}${pad(f.getMinutes())}${pad(f.getSeconds())}`;
    expediente.value = `EXP-${stamp}`;
    fechaRecepcion.value = `${pad(f.getDate())}/${pad(f.getMonth() + 1)}/${f.getFullYear()} ${pad(f.getHours())}:${pad(f.getMinutes())}`;
    pasoIA.value = 2;
};

const ejecutarExtraccion = async () => {
    if (!documentoId.value && !archivo.value) { mensaje.value = { tipo: 'error', texto: 'Selecciona un documento.' }; return; }
    procesando.value = true; mensaje.value = null;
    try {
        let data;
        if (documentoId.value && !archivo.value) {
            ({ data } = await window.axios.post('/simulaciones/extraer-ia', {
                documento_id: documentoId.value,
                carrera_externa_id: props.postulante.carrera_externa_id,
                carrera_usil_id: props.postulante.carrera_destino_id,
            }));
        } else {
            const fd = new FormData();
            fd.append('documento', archivo.value);
            // De quién es el récord: el servidor comprueba su consentimiento antes de enviarlo a la IA.
            fd.append('postulante_id', props.postulante.id);
            if (props.postulante.carrera_destino_id) fd.append('carrera_usil_id', props.postulante.carrera_destino_id);
            if (props.postulante.carrera_externa_id) fd.append('carrera_externa_id', props.postulante.carrera_externa_id);
            ({ data } = await window.axios.post('/simulaciones/extraer-ia', fd, { headers: { 'Content-Type': 'multipart/form-data' } }));
        }
        extraccion.value = data;
        documentoPath.value = data.documento_path ?? null;
        if (data.institucion?.universidad) universidadOrigen.value = data.institucion.universidad;
        mensaje.value = { tipo: 'ok', texto: `Extraídos ${data.aprobados?.length || 0} aprobados · ${data.desaprobados?.length || 0} desaprobados · ${data.no_convalidables?.length || 0} no convalidables.` };
    } catch (e) {
        mensaje.value = { tipo: 'error', texto: e.response?.data?.message || 'No se pudo procesar el documento.' };
    } finally { procesando.value = false; }
};

// Modo Manual: extrae los cursos del récord académico (mismo endpoint que el pipeline IA)
// y los agrega a la bandeja de "Cursos externos" sin tocar las filas ya cargadas, para
// que el usuario los empareje a mano o con los botones de sugerencia.
// Idempotente: los cursos cuyo nombre ya está en la bandeja se omiten (recargar no duplica).
const cargarCursosDesdeDocumento = async () => {
    await ejecutarExtraccion();
    if (!extraccion.value) return;
    const norm = (s) => String(s ?? '').trim().toLowerCase();
    const existentes = new Set(filas.map((f) => norm(f.curso_origen_nombre)));
    // Modo manual: todos los cursos del récord entran por igual como convalidables;
    // el coordinador decide cada equivalencia a mano (no se bloquea ninguno de antemano).
    const candidatas = [
        ...(extraccion.value.aprobados ?? []),
        ...(extraccion.value.no_convalidables ?? []),
        ...(extraccion.value.desaprobados ?? []),
    ].map((c) => filaBase({ ...c, clasificacion: 'convalidable' }));
    let agregados = 0;
    candidatas.forEach((f) => {
        if (existentes.has(norm(f.curso_origen_nombre))) return;
        existentes.add(norm(f.curso_origen_nombre));
        filas.push(f);
        agregados++;
    });
    const omitidos = candidatas.length - agregados;
    mensaje.value = {
        tipo: 'ok',
        texto: `${agregados} curso(s) agregados a la bandeja` + (omitidos ? ` · ${omitidos} ya estaban cargados.` : '.'),
    };
};

// Construye la tabla de mapeo a partir de la extracción validada (al entrar a la etapa 5).
const construirFilasMapeo = () => {
    limpiarFilas();
    aprobadosValidados.value.forEach((c) => filas.push(filaBase({ ...c, clasificacion: 'convalidable' })));
    (extraccion.value?.no_convalidables ?? []).forEach((c) => filas.push(filaBase({ ...c, clasificacion: 'no_convalidable' })));
    (extraccion.value?.desaprobados ?? []).forEach((c) => filas.push(filaBase({ ...c, clasificacion: 'desaprobado' })));
    filas.forEach((f) => { f.origen = 'ia'; });
};

const puedeAvanzarIA = computed(() => {
    if (pasoIA.value === 2) return !!(documentoId.value || archivo.value || filas.length);
    if (pasoIA.value === 3) return !!(extraccion.value?.aprobados?.length || filas.length);
    if (pasoIA.value === 4) return aprobadosValidados.value.length > 0 || filas.length > 0;
    if (pasoIA.value === 5) return duplicados.value.length === 0;
    return true;
});

const siguienteIA = async () => {
    if (!puedeAvanzarIA.value) return;
    if (pasoIA.value === 4) {
        // Se prepara el mapeo con la columna VACÍA (sin sugerencia automática).
        // Los cursos sugeridos aparecen solo al pulsar «Sugerir con IA» o «Re-sugerir por similitud».
        if (extraccion.value) {
            construirFilasMapeo();
        }
        pasoIA.value = 5;
        return;
    }
    pasoIA.value = Math.min(6, pasoIA.value + 1);
};
const anteriorIA = () => { pasoIA.value = Math.max(1, pasoIA.value - 1); };

// Al cambiar a modo IA sin haber iniciado, arrancar en Recepción.
watch(metodo, (m) => { if (m === 'ia') { pasoIA.value = 1; mensaje.value = null; } });

// ---------------------------------------------------------------- guardar
const guardadoId = ref(null);   // id de la preconvalidación guardada (habilita descargas)
const modalGuardado = ref(false);   // el resumen se muestra como popup al terminar
const cerrarModalGuardado = () => { modalGuardado.value = false; };

// Esc cierra el popup (mismo gesto que el clic en el fondo).
const onEsc = (e) => { if (e.key === 'Escape' && modalGuardado.value) cerrarModalGuardado(); };
onMounted(() => window.addEventListener('keydown', onEsc));
onBeforeUnmount(() => window.removeEventListener('keydown', onEsc));

// Convierte créditos (que la IA puede devolver como "3,000", "3.0", "4") a número o null.
const aNumero = (v) => {
    if (v === '' || v == null) return null;
    const n = parseFloat(String(v).replace(/[^\d.,]/g, '').replace(',', '.'));
    return Number.isNaN(n) ? null : n;
};

const guardar = () => {
    if (!props.tieneMalla) return;
    if (duplicados.value.length) { mensaje.value = { tipo: 'error', texto: 'Corrige los cursos USIL duplicados (regla 1 a 1).' }; return; }
    const payload = {
        postulante_id: props.postulante.id,
        carrera_usil_id: props.postulante.carrera_destino_id,
        metodo: metodo.value,
        documento_path: documentoPath.value,
        universidad_origen: universidadOrigen.value,
        escala_notas: escala.value,
        nota_minima: notaMinima.value,
        observaciones: [expediente.value ? `Expediente ${expediente.value}` : '', observaciones.value].filter(Boolean).join(' — '),
        filas: filas
            .filter((f) => f.curso_origen_nombre.trim())
            .map((f) => ({
                curso_externo_id: f.curso_externo_id ?? null,
                curso_origen_nombre: String(f.curso_origen_nombre).slice(0, 200),
                nota_origen: f.nota_origen == null || f.nota_origen === '' ? null : String(f.nota_origen).slice(0, 20),
                creditos_origen: aNumero(f.creditos_origen),
                ciclo_origen: f.ciclo_origen == null || f.ciclo_origen === '' ? null : String(f.ciclo_origen).slice(0, 30),
                clasificacion: f.clasificacion,
                motivo: f.motivo ? String(f.motivo).slice(0, 300) : null,
                curso_usil_id: f.curso_usil_id || null,
                confianza: aNumero(f.confianza),
                origen: f.origen || (metodo.value === 'ia' ? 'ia' : 'manual'),
            })),
    };
    procesando.value = true;
    mensaje.value = null;
    const peticion = editando
        ? window.axios.put(`/simulaciones/${props.edicion.id}`, payload)
        : window.axios.post('/simulaciones', payload);
    peticion
        .then(({ data }) => {
            guardadoId.value = data.id;
            modalGuardado.value = true;
            mensaje.value = { tipo: 'ok', texto: `Simulación #${data.id} ${editando ? 'actualizada' : 'guardada'}.` };
        })
        .catch((e) => {
            const errs = e.response?.data?.errors;
            mensaje.value = { tipo: 'error', texto: errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'No se pudo guardar. Revisa los datos.') };
        })
        .finally(() => { procesando.value = false; });
};

// Elimina una simulación previa registrando el motivo en la base de datos.
const eliminarSimulacion = (s) => {
    const motivo = window.prompt(`Motivo para eliminar la simulación #${s.id} (quedará registrado en la base de datos):`);
    if (motivo === null) return;
    if (motivo.trim().length < 5) { alert('El motivo debe tener al menos 5 caracteres.'); return; }
    router.delete(`/simulaciones/${s.id}`, { data: { motivo: motivo.trim() }, preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <!-- Encabezado -->
        <div class="mb-4">
            <!-- El retroceso va solo, en la primera línea: antes competía con el rótulo de modo
                 y los dos parecían la misma clase de texto, ninguno un control. -->
            <VolverA href="/simulaciones" texto="Simulaciones" />
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="font-heading text-xs font-bold uppercase tracking-wide text-[#2E75B6]">
                        {{ editando ? `Editar simulación #${edicion.id}` : (metodo === 'ia' ? 'Simulación con IA' : 'Simulación manual de convalidación') }}
                    </p>
                    <h1 class="mt-0.5 font-heading text-2xl font-extrabold text-[#1F3864]">
                        {{ postulante.institucion || postulante.carrera_externa || '—' }}
                        <span class="font-semibold text-slate-400">→</span>
                        {{ postulante.carrera_destino || '— sin carrera —' }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500"><span class="font-medium text-slate-700">{{ postulante.nombre }}</span> · {{ postulante.documento }}</p>
                </div>
                <!-- El récord, como ficha con su etiqueta y su icono en vez de texto suelto.
                     Se cayó la línea «Malla X»: repetía literalmente la carrera destino del <h1>. -->
                <div v-if="docSeleccionado" class="inline-flex min-w-0 max-w-[17rem] shrink-0 items-center gap-2.5 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                    <svg class="h-5 w-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Récord académico</span>
                        <a v-if="docSeleccionado.url" :href="docSeleccionado.url" target="_blank" rel="noopener"
                           class="block truncate text-sm font-medium text-[#2E75B6] hover:underline">{{ docSeleccionado.nombre }}</a>
                        <span v-else class="block truncate text-sm font-medium text-slate-700">{{ docSeleccionado.nombre }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Píldoras de estado -->
        <div v-if="filas.length" class="mb-4 flex flex-wrap gap-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5" style="min-width: 200px;">
                <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                <span class="text-sm text-emerald-800"><strong>{{ conteoEstados.aprobados }}</strong> aprobados</span>
            </div>
            <div class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5" style="min-width: 180px;">
                <span class="h-2 w-2 shrink-0 rounded-full bg-slate-400"></span>
                <span class="text-sm text-slate-600"><strong>{{ conteoEstados.desaprobados }}</strong> desaprobados</span>
            </div>
            <div class="flex flex-1 items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5" style="min-width: 200px;">
                <span class="h-2 w-2 shrink-0 rounded-full bg-rose-500"></span>
                <span class="text-sm text-rose-800"><strong>{{ conteoEstados.noConvalidables }}</strong> no convalidables</span>
            </div>
        </div>

        <div v-if="!postulante.carrera_destino_id" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            El postulante no tiene carrera destino USIL. Edítalo antes de simular.
        </div>
        <div v-else-if="!tieneMalla" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            La carrera destino no tiene un plan de estudios (malla) cargado. Carga la malla para poder mapear cursos.
        </div>
        <div v-else-if="!poolUsil.length" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            El plan de estudios de <strong>{{ postulante.carrera_destino }}</strong> no tiene cursos cargados, por lo que no hay a qué convalidar. Carga los cursos de la malla en <strong>Estructura → Mallas</strong>.
        </div>

        <!-- Notificaciones tipo toast: emergen arriba a la derecha y se ocultan solas -->
        <Transition
            enter-active-class="transition duration-300 ease-out" enter-from-class="translate-x-8 opacity-0" enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in" leave-from-class="translate-x-0 opacity-100" leave-to-class="translate-x-8 opacity-0">
            <div v-if="mensaje" role="alert"
                 class="fixed right-4 top-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] items-start gap-3 rounded-xl border bg-white p-4 shadow-lg"
                 :class="mensaje.tipo === 'ok' ? 'border-emerald-200' : 'border-red-200'">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white"
                      :class="mensaje.tipo === 'ok' ? 'bg-emerald-500' : 'bg-red-500'">
                    <svg v-if="mensaje.tipo === 'ok'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold" :class="mensaje.tipo === 'ok' ? 'text-emerald-800' : 'text-red-800'">{{ mensaje.tipo === 'ok' ? 'Listo' : 'Atención' }}</p>
                    <p class="mt-0.5 break-words text-sm text-slate-600">{{ mensaje.texto }}</p>
                </div>
                <button type="button" @click="mensaje = null" title="Cerrar" class="shrink-0 text-slate-300 hover:text-slate-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </Transition>

        <!-- Cabecera de modo: idéntica en Manual y Asistida. Antes el selector vivía dentro de
             cada rama, cambiaba de sitio al alternar y obligaba a mantener el control duplicado. -->
        <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-4 inline-flex rounded-lg border border-slate-200 bg-slate-100 p-1">
                <button @click="metodo = 'manual'" :class="metodo === 'manual' ? 'bg-[#1F3864] text-white shadow-sm' : 'text-slate-600 hover:bg-white'" class="rounded-md px-4 py-1.5 text-sm font-semibold transition">Manual</button>
                <button @click="metodo = 'ia'" :class="metodo === 'ia' ? 'bg-violet-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white'" class="rounded-md px-4 py-1.5 text-sm font-semibold transition">Asistida</button>
            </div>

            <!-- Datos de la solicitud: solo lectura, vienen del expediente del postulante -->
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="min-w-0">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Universidad de origen</dt>
                    <dd class="mt-0.5 truncate text-sm font-medium text-slate-700">{{ universidadOrigen || postulante.institucion || '—' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Carrera de origen</dt>
                    <dd class="mt-0.5 truncate text-sm font-medium text-slate-700">{{ postulante.carrera_externa || '—' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Ciclo de postulación</dt>
                    <dd class="mt-0.5 truncate text-sm font-medium text-slate-700">{{ postulante.ciclo_postulacion || '—' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Escala de notas</dt>
                    <dd class="mt-0.5 truncate text-sm font-medium text-slate-700">{{ escalaLabel }}</dd>
                </div>
            </dl>

            <!-- El récord solo se elige aquí en Manual: en Asistida lo pide la Etapa 2. -->
            <div v-if="metodo === 'manual'" class="mt-4 flex flex-wrap gap-6 border-t border-slate-100 pt-4">
                    <div class="flex min-w-[320px] flex-[2] flex-col gap-1.5">
                        <label class="text-xs font-semibold text-slate-500">Récord académico</label>
                        <select v-if="documentos?.length" v-model="documentoId" class="min-w-0 rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option v-for="d in documentos" :key="d.id" :value="d.id">{{ TIPO_DOC[d.tipo] || d.tipo }} — {{ d.nombre }}</option>
                        </select>
                        <input v-else type="file" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.txt,.csv" @change="onArchivo" class="min-w-0 text-sm text-slate-600" />
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- 1ª opción: revisar el récord -->
                            <a v-if="docSeleccionado?.url" :href="docSeleccionado.url" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F3864] px-3.5 py-2 text-sm font-bold text-white hover:bg-[#2E75B6]">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                Ver récord
                            </a>
                            <!-- 2ª opción: extraer los cursos automáticamente con IA -->
                            <button v-if="IA_EN_MANUAL" type="button" @click="cargarCursosDesdeDocumento" :disabled="procesando || !ia?.disponible || (!documentoId && !archivo)"
                                    :title="ia?.disponible ? '' : 'Configura la API key en Configuración'"
                                    class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border border-violet-300 px-3.5 py-2 text-sm font-bold text-violet-700 hover:bg-violet-50 disabled:opacity-50">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
                                {{ procesando ? 'Extrayendo…' : 'Cargar cursos automáticamente' }}
                            </button>
                        </div>
                    </div>
            </div>
        </div>

        <!-- ============================= MODO MANUAL ============================= -->
        <template v-if="metodo === 'manual'">
            <MapeoUsilMatch :pool-usil="poolUsil" :filas="filas" :no-convalidar="noConvalidar" :procesando="procesando"
                             :ia="ia" :sin-ia="!IA_EN_MANUAL"
                             :antecedentes="antecedentes" :cargando-antecedentes="cargandoAntecedentes"
                             :criterios="criteriosAntecedentes" :cursos-malla="cursosMallaOrigen ?? []"
                             :catalogo="catalogoDeclarado"
                             @seleccion-origen="buscarAntecedentes"
                             @sugerir-ia="sugerir('ia')" @sugerir-similitud="sugerir('similitud')"
                             @agregar="agregarFila" @quitar="(f) => quitarFila(filas.indexOf(f))"
                             @importar-excel="importarDesdeExcel" />

            <p v-if="duplicados.length" class="mt-2 inline-flex items-center gap-1.5 text-xs text-red-600">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                Hay cursos USIL asignados más de una vez. La convalidación es 1 a 1.
            </p>
            <div class="mt-4"><label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                <textarea v-model="observaciones" rows="2" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]"></textarea></div>
            <div class="mt-6 flex gap-3">
                <button @click="guardar" :disabled="!tieneMalla || procesando || guardadoId"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-semibold text-white hover:bg-[#2E75B6] disabled:opacity-50">
                    <svg v-if="guardadoId" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    {{ guardadoId ? 'Simulación guardada' : (procesando ? 'Guardando…' : 'Guardar simulación') }}</button>
                <Link href="/simulaciones" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            </div>
        </template>

        <!-- ============================= MODO IA (pipeline 6 etapas) ============================= -->
        <template v-else>
            <p v-if="!ia?.disponible" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                IA inactiva: ve a <strong>Configuración</strong> y define la API key para ejecutar el pipeline. También puedes usar el modo <strong>Manual</strong>.
            </p>

            <!-- Indicador de etapas -->
            <div class="mb-5 grid grid-cols-3 gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-6">
                <div v-for="p in PASOS_IA" :key="p.n" class="text-center">
                    <!-- El borde existe siempre (transparente si no es el paso activo): si solo
                         lo tuviera el activo, la fila entera se desplazaría 2px en cada avance. -->
                    <div :class="p.n === pasoIA ? 'border-[#2E75B6] bg-blue-50/60 text-[#1F3864]' : (p.n < pasoIA ? 'border-transparent text-emerald-600' : 'border-transparent text-slate-400')" class="mx-auto rounded-lg border-2 px-2 py-2 transition">
                        <!-- Un paso hecho cambia de icono, no solo de color: el estado no
                             puede depender únicamente del color para poder distinguirse. -->
                        <svg class="mx-auto h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="p.n < pasoIA ? ICONO_HECHO : p.d" />
                        </svg>
                        <div class="mt-1 text-xs font-semibold">{{ p.n }}</div>
                        <div class="text-[11px] leading-tight">{{ p.label }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <!-- Etapa 1 · Recepción -->
                <div v-if="pasoIA === 1">
                    <h2 class="text-lg font-semibold text-[#1F3864]">Etapa 1 · Recepción del expediente</h2>
                    <p class="mb-4 text-sm text-slate-500">Se registra el expediente para el postulante. La carrera destino proviene de su ficha.</p>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Postulante</dt><dd class="font-medium text-slate-700">{{ postulante.nombre }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Carrera USIL destino</dt><dd class="font-medium text-slate-700">{{ postulante.carrera_destino || '—' }}</dd></div>
                    </dl>
                    <div class="mt-6 flex justify-end">
                        <button @click="iniciarPipeline" :disabled="!tieneMalla" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 2 · Validación documental -->
                <div v-else-if="pasoIA === 2">
                    <h2 class="text-lg font-semibold text-[#1F3864]">Etapa 2 · Validación documental</h2>
                    <p class="mb-4 text-sm text-slate-500">Expediente <strong>{{ expediente }}</strong> · Recepción: {{ fechaRecepcion }}</p>

                    <div v-if="documentos?.length">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Documento del postulante (ya cargado en su expediente)</label>
                        <select v-model="documentoId" class="w-full max-w-lg rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option v-for="d in documentos" :key="d.id" :value="d.id">{{ TIPO_DOC[d.tipo] || d.tipo }} — {{ d.nombre }}</option>
                        </select>
                        <div v-if="docSeleccionado" class="mt-3 flex flex-wrap gap-4 text-sm text-emerald-700">
                            <span v-for="t in ['Documento en expediente', 'Trazabilidad activa', 'Formato aceptado']" :key="t" class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                {{ t }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                        <p class="mb-2">El postulante no tiene documentos cargados. Sube uno (o cárgalo en su ficha):</p>
                        <input type="file" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.txt,.csv" @change="onArchivo" class="text-sm text-slate-600" />
                    </div>

                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="siguienteIA" :disabled="!puedeAvanzarIA" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 3 · Extracción -->
                <div v-else-if="pasoIA === 3">
                    <h2 class="text-lg font-semibold text-[#1F3864]">Etapa 3 · Extracción de cursos (OCR + IA)</h2>
                    <p class="mb-4 text-sm text-slate-500">Expediente <strong>{{ expediente }}</strong> · Documento: {{ docSeleccionado?.nombre || archivo?.name }}</p>

                    <button @click="ejecutarExtraccion" :disabled="!ia?.disponible || procesando"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
                        {{ procesando ? 'Procesando con IA…' : (extraccion ? 'Re-ejecutar extracción' : 'Ejecutar extracción con IA') }}
                    </button>

                    <div v-if="extraccion" class="mt-5">
                        <dl class="mb-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Estudiante (detectado)</dt><dd class="font-medium text-slate-700">{{ extraccion.estudiante?.nombre || '—' }}</dd></div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Universidad</dt><dd class="font-medium text-slate-700">{{ extraccion.institucion?.universidad || '—' }}</dd></div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Carrera (doc)</dt><dd class="font-medium text-slate-700">{{ extraccion.estudiante?.carrera || '—' }}</dd></div>
                        </dl>
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-emerald-600">{{ extraccion.aprobados?.length || 0 }}</p><p class="text-xs text-slate-500">Aprobados</p></div>
                            <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-red-500">{{ extraccion.desaprobados?.length || 0 }}</p><p class="text-xs text-slate-500">Desaprobados</p></div>
                            <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-amber-500">{{ extraccion.no_convalidables?.length || 0 }}</p><p class="text-xs text-slate-500">No convalidables</p></div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="siguienteIA" :disabled="!puedeAvanzarIA" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 4 · Aprobados -->
                <div v-else-if="pasoIA === 4">
                    <h2 class="text-lg font-semibold text-[#1F3864]">Etapa 4 · Validación de aprobados</h2>
                    <p class="mb-4 text-sm text-slate-500">Ajusta la escala y la nota mínima. Los cursos que no cumplen quedan fuera del mapeo.</p>
                    <div class="mb-4 flex flex-wrap items-end gap-3">
                        <div><label class="mb-1 block text-xs font-medium text-slate-500">Escala</label>
                            <select v-model="escala" class="rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]"><option value="0-20">0 - 20</option><option value="0-100">0 - 100</option><option value="0-5">0 - 5</option></select></div>
                        <div><label class="mb-1 block text-xs font-medium text-slate-500">Nota mínima</label>
                            <!-- En escala vigesimal el piso es 11 (Reglamento de Estudios, Art. 15); el servidor lo rechaza igual. -->
                            <input v-model="notaMinima" type="number" step="0.1" :min="escala === '0-20' ? 11 : 0"
                                   class="w-28 rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                            <p v-if="escala === '0-20' && Number(notaMinima) < 11" class="mt-1 text-xs text-red-600">
                                Mínimo 11 en escala vigesimal.
                            </p></div>
                        <div class="flex gap-3 text-sm">
                            <span class="rounded-lg bg-emerald-50 px-3 py-2 text-emerald-700">Cumplen: <strong>{{ aprobadosValidados.length }}</strong></span>
                            <span class="rounded-lg bg-slate-100 px-3 py-2 text-slate-500">Fuera: <strong>{{ aprobadosFuera.length }}</strong></span>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-2 font-semibold">Curso</th><th class="px-4 py-2 font-semibold">Nota</th><th class="px-4 py-2 font-semibold">Créditos</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(c, i) in aprobadosValidados" :key="i"><td class="px-4 py-2 text-slate-700">{{ c.nombre }}</td><td class="px-4 py-2 text-slate-600">{{ c.nota }}</td><td class="px-4 py-2 text-slate-600">{{ c.creditos || '—' }}</td></tr>
                                <tr v-if="!aprobadosValidados.length"><td colspan="3" class="px-4 py-6 text-center text-slate-400">Ningún curso cumple la nota mínima.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="siguienteIA" :disabled="!puedeAvanzarIA" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 5 · Mapeo USIL -->
                <div v-else-if="pasoIA === 5">
                    <h2 class="text-lg font-semibold text-[#1F3864]">Etapa 5 · Mapeo USIL — {{ postulante.carrera_destino }}</h2>
                    <p class="mb-3 text-sm text-slate-500">Cada curso aprobado se empareja con un curso USIL (incluye electivos). Regla 1‑a‑1: cada curso USIL solo puede usarse una vez.</p>

                    <MapeoUsilMatch :pool-usil="poolUsil" :filas="filas" :no-convalidar="noConvalidar" :procesando="procesando"
                                     :ia="ia" solo-lectura
                                     @sugerir-ia="sugerir('ia')" @sugerir-similitud="sugerir('similitud')" />

                    <p v-if="duplicados.length" class="mt-2 inline-flex items-center gap-1.5 text-xs text-red-600">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                        Hay cursos USIL asignados más de una vez. La convalidación es 1 a 1.
                    </p>

                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="siguienteIA" :disabled="!puedeAvanzarIA" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 6 · Preconvalidación -->
                <div v-else-if="pasoIA === 6">
                    <h2 class="text-lg font-semibold text-[#1F3864]">Etapa 6 · Preconvalidación</h2>
                    <p class="mb-4 text-sm text-slate-500">Revisa el resumen del expediente y guarda la preconvalidación. Podrás descargar el documento (PDF/Excel) al finalizar.</p>
                    <dl class="mb-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Expediente</dt><dd class="font-medium text-slate-700">{{ expediente }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Solicitante</dt><dd class="font-medium text-slate-700">{{ postulante.nombre }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Universidad de origen</dt><dd class="font-medium text-slate-700">{{ universidadOrigen || '—' }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Carrera USIL destino</dt><dd class="font-medium text-slate-700">{{ postulante.carrera_destino }}</dd></div>
                    </dl>
                    <div class="mb-4 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-[#1F3864]">{{ resumen.total }}</p><p class="text-xs text-slate-500">Cursos</p></div>
                        <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-emerald-600">{{ resumen.convalidados }}</p><p class="text-xs text-slate-500">Convalidados</p></div>
                        <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-[#2E75B6]">{{ resumen.creditos.toFixed(1) }}</p><p class="text-xs text-slate-500">Créditos reconocidos</p></div>
                    </div>

                    <!-- Pestañas de clasificación de cursos -->
                    <div class="mb-3 inline-flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-white p-1 shadow-sm">
                        <button type="button" @click="tabPreconv = 'conv'"
                                :class="tabPreconv === 'conv' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                                class="rounded-md px-3 py-1.5 text-sm font-medium">Convalidados ({{ convalidadosLista.length }})</button>
                        <button type="button" @click="tabPreconv = 'no'"
                                :class="tabPreconv === 'no' ? 'bg-amber-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                                class="rounded-md px-3 py-1.5 text-sm font-medium">No convalidados ({{ noConvalidadosLista.length }})</button>
                        <button type="button" @click="tabPreconv = 'desap'"
                                :class="tabPreconv === 'desap' ? 'bg-red-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                                class="rounded-md px-3 py-1.5 text-sm font-medium">Desaprobados ({{ desaprobadosLista.length }})</button>
                    </div>

                    <div class="mb-4 overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <!-- Convalidados -->
                            <template v-if="tabPreconv === 'conv'">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                                    <th class="px-4 py-2.5 font-semibold">Curso de origen</th><th class="w-16 px-4 py-2.5 font-semibold">Nota</th>
                                    <th class="w-20 px-4 py-2.5 font-semibold">Créd.</th><th class="px-4 py-2.5 font-semibold">Convalida con (USIL)</th>
                                </tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(f, i) in convalidadosLista" :key="i" class="hover:bg-slate-50/70">
                                        <td class="px-4 py-2 text-slate-700">{{ f.curso_origen_nombre }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ f.nota_origen || '—' }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ f.creditos_origen !== '' && f.creditos_origen != null ? f.creditos_origen : '—' }}</td>
                                        <td class="px-4 py-2 font-medium text-emerald-700">{{ usilPorId[f.curso_usil_id] || '—' }}</td>
                                    </tr>
                                    <tr v-if="!convalidadosLista.length"><td colspan="4" class="px-4 py-6 text-center text-slate-400">Sin cursos convalidados.</td></tr>
                                </tbody>
                            </template>
                            <!-- No convalidados -->
                            <template v-else-if="tabPreconv === 'no'">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                                    <th class="px-4 py-2.5 font-semibold">Curso de origen</th><th class="w-16 px-4 py-2.5 font-semibold">Nota</th>
                                    <th class="px-4 py-2.5 font-semibold">Motivo</th><th class="w-36 px-4 py-2.5 font-semibold"></th>
                                </tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(f, i) in noConvalidadosLista" :key="i" class="hover:bg-slate-50/70">
                                        <td class="px-4 py-2 text-slate-700">{{ f.curso_origen_nombre }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ f.nota_origen || '—' }}</td>
                                        <td class="px-4 py-2">
                                            <span class="inline-block rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                                                {{ f.clasificacion === 'no_convalidable' ? 'No convalidable' : 'Sin equivalencia USIL' }}
                                            </span>
                                            <span v-if="f.motivo" class="ml-2 text-xs text-slate-500">{{ f.motivo }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <button v-if="f.clasificacion === 'no_convalidable'" type="button" @click="reconsiderar(f)"
                                                    class="text-xs font-medium text-[#2E75B6] hover:underline">Reconsiderar</button>
                                            <button v-else type="button" @click="abrirDescarte(f)"
                                                    class="text-xs font-medium text-amber-700 hover:underline">Marcar no convalidable</button>
                                        </td>
                                    </tr>
                                    <tr v-if="!noConvalidadosLista.length"><td colspan="4" class="px-4 py-6 text-center text-slate-400">Sin cursos no convalidados.</td></tr>
                                </tbody>
                            </template>
                            <!-- Desaprobados -->
                            <template v-else>
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                                    <th class="px-4 py-2.5 font-semibold">Curso de origen</th><th class="w-16 px-4 py-2.5 font-semibold">Nota</th><th class="w-20 px-4 py-2.5 font-semibold">Créd.</th>
                                </tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(f, i) in desaprobadosLista" :key="i" class="hover:bg-slate-50/70">
                                        <td class="px-4 py-2 text-slate-700">{{ f.curso_origen_nombre }}</td>
                                        <td class="px-4 py-2 text-red-600">{{ f.nota_origen || '—' }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ f.creditos_origen !== '' && f.creditos_origen != null ? f.creditos_origen : '—' }}</td>
                                    </tr>
                                    <tr v-if="!desaprobadosLista.length"><td colspan="3" class="px-4 py-6 text-center text-slate-400">Sin cursos desaprobados.</td></tr>
                                </tbody>
                            </template>
                        </table>
                    </div>
                    <div v-if="sinAsignar" class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                        <span>Hay <strong>{{ sinAsignar }}</strong> {{ sinAsignar === 1 ? 'curso convalidable sin asignar' : 'cursos convalidables sin asignar' }} a un curso USIL. Puedes volver a la <button type="button" @click="pasoIA = 5" class="font-medium underline">Etapa 5 · Mapeo</button> para revisarlos, o guardar así si es intencional: quedarán como no convalidados.</span>
                    </div>

                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                        <textarea v-model="observaciones" rows="2" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]"></textarea></div>
                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="guardar" :disabled="!tieneMalla || procesando || guardadoId"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-semibold text-white hover:bg-[#2E75B6] disabled:opacity-50">
                            <svg v-if="guardadoId" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            {{ guardadoId ? 'Simulación guardada' : (procesando ? 'Guardando…' : 'Guardar simulación') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Simulaciones previas -->
        <div v-if="simulacionesPrevias?.length" class="mt-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-400">Simulaciones previas</h2>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm"><tbody class="divide-y divide-slate-100">
                    <tr v-for="s in simulacionesPrevias" :key="s.id" class="hover:bg-slate-50/70">
                        <td class="px-4 py-2 text-slate-600">#{{ s.id }} · {{ s.fecha }}</td>
                        <td class="px-4 py-2"><span :class="s.metodo === 'ia' ? 'text-violet-600' : 'text-slate-600'" class="text-xs font-medium capitalize">{{ s.metodo }}</span></td>
                        <td class="px-4 py-2 text-slate-600">{{ s.carrera }}</td>
                        <td class="px-4 py-2 text-right">
                            <Link :href="`/simulaciones/${s.id}/editar`" class="mr-3 text-[#2E75B6] hover:underline">Editar</Link>
                            <Link :href="`/simulaciones/${s.id}`" class="mr-3 text-slate-500 hover:underline">Ver</Link>
                            <button type="button" @click="eliminarSimulacion(s)" class="text-red-600 hover:underline">Eliminar</button>
                        </td>
                    </tr>
                </tbody></table>
            </div>
        </div>

        <!-- Popup de cierre: resumen de la preconvalidación guardada.
             Las descargas (PDF/Excel) se hacen en Convalidaciones. -->
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0"
                    enter-active-class="transition duration-150" leave-active-class="transition duration-150">
            <div v-if="modalGuardado" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 role="dialog" aria-modal="true" aria-labelledby="titulo-guardado">
                <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="cerrarModalGuardado"></div>
                <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl">
                    <button type="button" @click="cerrarModalGuardado" title="Cerrar"
                            class="absolute right-4 top-4 text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>

                    <div class="border-b border-emerald-100 bg-emerald-50 px-6 py-5">
                        <div class="flex items-start gap-3 pr-8">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            </span>
                            <div class="min-w-0">
                                <p id="titulo-guardado" class="font-heading text-base font-bold text-emerald-900">Simulación #{{ guardadoId }} guardada</p>
                                <p class="mt-0.5 text-sm text-emerald-700">{{ postulante.nombre }} · {{ postulante.carrera_destino }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-5">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-xl border border-slate-200 p-3 text-center">
                                <p class="text-2xl font-bold text-[#1F3864]">{{ resumen.total }}</p><p class="text-xs text-slate-500">Cursos evaluados</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3 text-center">
                                <p class="text-2xl font-bold text-emerald-600">{{ resumen.convalidados }}</p><p class="text-xs text-slate-500">Convalidados</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3 text-center">
                                <p class="text-2xl font-bold text-[#2E75B6]">{{ resumen.creditos.toFixed(1) }}</p><p class="text-xs text-slate-500">Créditos reconocidos</p>
                            </div>
                            <div class="rounded-xl p-3 text-center" :class="sinAsignar ? 'border border-amber-300 bg-amber-50' : 'border border-slate-200'">
                                <p class="text-2xl font-bold" :class="sinAsignar ? 'text-amber-600' : 'text-slate-400'">{{ sinAsignar }}</p><p class="text-xs text-slate-500">Sin asignar</p>
                            </div>
                        </div>

                        <p v-if="sinAsignar" class="mt-3 text-sm text-amber-800">
                            Quedaron <strong>{{ sinAsignar }}</strong> curso(s) aprobado(s) sin equivalencia USIL; no suman créditos.
                            Puedes cerrar este aviso y seguir asignándolos.
                        </p>
                        <p v-else class="mt-3 text-sm text-slate-500">
                            Todos los cursos aprobados quedaron resueltos.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                        <Link :href="`/simulaciones/${guardadoId}`" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6]">
                            Ver la preconvalidación
                        </Link>
                        <Link v-if="veConvalidaciones" href="/convalidaciones" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-white/60">
                            Ir a Convalidaciones
                        </Link>
                        <button type="button" @click="cerrarModalGuardado" class="ml-auto text-sm text-slate-500 hover:text-slate-700 hover:underline">
                            Seguir editando
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <ConfirmDialog :open="!!marcando"
                       titulo="¿Marcar como no convalidable?"
                       mensaje="El curso quedará descartado en este expediente y el motivo aparecerá en el Excel que recibe el postulante. No cambia la política de la carrera."
                       texto-confirmar="Marcar" tono="aviso"
                       @cancelar="marcando = null" @confirmar="confirmarDescarte">
            <p class="mb-3 font-medium text-slate-700">{{ marcando?.curso_origen_nombre }}</p>
            <label class="mb-1 block text-xs font-medium text-slate-500" for="motivo-descarte">Motivo</label>
            <textarea id="motivo-descarte" v-model="motivoDescarte" rows="3" maxlength="300"
                      class="w-full rounded-md border-slate-300 text-sm"
                      placeholder="Ej.: el sílabo no cubre las competencias de ningún curso del plan."></textarea>
            <p v-if="errorDescarte" class="mt-1 text-xs text-red-600">{{ errorDescarte }}</p>
        </ConfirmDialog>
    </div>
</template>
