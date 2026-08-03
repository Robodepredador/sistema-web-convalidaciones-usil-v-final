<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import MapeoUsilMatch from '../../Components/MapeoUsilMatch.vue';

// El Coordinador evalúa pero no ve Convalidaciones: el popup no debe ofrecerle
// un enlace que le devolvería un 403.
const permisos = computed(() => usePage().props.auth?.user?.permisos ?? []);
const veConvalidaciones = computed(() => permisos.value.includes('*') || permisos.value.includes('convalidacion.ver'));

const props = defineProps({
    postulante: Object,
    poolUsil: Array,
    cursosOrigen: Array,
    documentos: Array,
    tieneMalla: Boolean,
    noConvalidar: String,
    ia: Object,
    edicion: { type: Object, default: null },   // simulación a editar (o null para nueva)
    simulacionesPrevias: Array,
});

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
                clasificacion: props.edicion.metodo === 'ia' ? f.clasificacion : 'convalidable',
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
const tabPreconv = ref('conv');   // 'conv' | 'no' | 'desap'

// Nombre y créditos llegan desde la tarjeta editable en línea de MapeoUsilMatch (sin window.prompt).
const agregarFila = ({ nombre, creditos } = {}) => {
    if (!nombre || !String(nombre).trim()) return;
    filas.push(filaBase({ nombre: String(nombre).trim(), creditos }));
};
const quitarFila = (i) => filas.splice(i, 1);
const limpiarFilas = () => { filas.splice(0, filas.length); };

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

// ================================================================ PIPELINE CON IA
const PASOS_IA = [
    { n: 1, label: 'Recepción', icon: '📥' },
    { n: 2, label: 'Validación documental', icon: '📄' },
    { n: 3, label: 'Extracción', icon: '🔍' },
    { n: 4, label: 'Aprobados', icon: '✅' },
    { n: 5, label: 'Mapeo USIL', icon: '🔗' },
    { n: 6, label: 'Preconvalidación', icon: '📜' },
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
            }));
        } else {
            const fd = new FormData();
            fd.append('documento', archivo.value);
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
        <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link href="/simulaciones" class="text-xs font-medium uppercase tracking-wide text-slate-400 hover:text-[#2E75B6]">← Simulaciones</Link>
                <p class="mt-2 font-heading text-xs font-bold uppercase tracking-wide text-[#2E75B6]">
                    {{ editando ? `Editar simulación #${edicion.id}` : (metodo === 'ia' ? 'Simulación con IA' : 'Simulación manual de convalidación') }}
                </p>
                <h1 class="mt-0.5 font-heading text-2xl font-extrabold text-[#1F3864]">
                    {{ postulante.institucion || postulante.carrera_externa || '—' }}
                    <span class="font-semibold text-slate-400">→</span>
                    {{ postulante.carrera_destino || '— sin carrera —' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500"><span class="font-medium text-slate-700">{{ postulante.nombre }}</span> · {{ postulante.documento }}</p>
            </div>
            <div class="text-right text-sm text-slate-500">
                <p>Malla <strong class="text-slate-700">{{ postulante.carrera_destino || '—' }}</strong></p>
                <p v-if="docSeleccionado">Récord:
                    <a v-if="docSeleccionado.url" :href="docSeleccionado.url" target="_blank" rel="noopener" class="font-semibold text-[#2E75B6] hover:underline">{{ docSeleccionado.nombre }}</a>
                    <strong v-else class="text-slate-700">{{ docSeleccionado.nombre }}</strong>
                </p>
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

        <!-- ============================= MODO MANUAL ============================= -->
        <template v-if="metodo === 'manual'">
            <!-- Tarjeta de configuración: método, escala/universidad y carga desde el récord -->
            <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                    <button @click="metodo = 'manual'" :class="metodo === 'manual' ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md px-4 py-1.5 text-sm font-bold">Manual</button>
                    <button @click="metodo = 'ia'" :class="metodo === 'ia' ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md px-4 py-1.5 text-sm font-bold">✨ Asistida</button>
                </div>

                <!-- Cabecera de datos de la solicitud: solo lectura (provienen del expediente del postulante) -->
                <dl class="grid gap-x-6 gap-y-3 border-b border-slate-100 pb-4 sm:grid-cols-2 lg:grid-cols-4">
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

                <div class="mt-4 flex flex-wrap gap-6">
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
                            <button type="button" @click="cargarCursosDesdeDocumento" :disabled="procesando || !ia?.disponible || (!documentoId && !archivo)"
                                    :title="ia?.disponible ? '' : 'Configura la API key en Configuración'"
                                    class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border border-violet-300 px-3.5 py-2 text-sm font-bold text-violet-700 hover:bg-violet-50 disabled:opacity-50">
                                ✨ {{ procesando ? 'Extrayendo…' : 'Cargar cursos automáticamente' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <MapeoUsilMatch :pool-usil="poolUsil" :filas="filas" :no-convalidar="noConvalidar" :procesando="procesando"
                             :ia="ia"
                             @sugerir-ia="sugerir('ia')" @sugerir-similitud="sugerir('similitud')"
                             @agregar="agregarFila" @quitar="(f) => quitarFila(filas.indexOf(f))" />

            <p v-if="duplicados.length" class="mt-2 text-xs text-red-600">⚠️ Hay cursos USIL asignados más de una vez. La convalidación es 1 a 1.</p>
            <div class="mt-4"><label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                <textarea v-model="observaciones" rows="2" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]"></textarea></div>
            <div class="mt-6 flex gap-3">
                <button @click="guardar" :disabled="!tieneMalla || procesando || guardadoId" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">
                    {{ guardadoId ? '✓ Guardada' : (procesando ? 'Guardando…' : 'Guardar simulación') }}</button>
                <Link href="/simulaciones" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            </div>
        </template>

        <!-- ============================= MODO IA (pipeline 6 etapas) ============================= -->
        <template v-else>
            <div class="mb-4 inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-sm">
                <button @click="metodo = 'manual'" :class="metodo === 'manual' ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-50'" class="rounded-md px-4 py-1.5 text-sm font-bold">Manual</button>
                <button @click="metodo = 'ia'" :class="metodo === 'ia' ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-slate-50'" class="rounded-md px-4 py-1.5 text-sm font-bold">✨ Asistida</button>
            </div>

            <p v-if="!ia?.disponible" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                IA inactiva: ve a <strong>Configuración</strong> y define la API key para ejecutar el pipeline. También puedes usar el modo <strong>Manual</strong>.
            </p>

            <!-- Indicador de etapas -->
            <div class="mb-5 grid grid-cols-3 gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-6">
                <div v-for="p in PASOS_IA" :key="p.n" class="text-center">
                    <div :class="p.n === pasoIA ? 'border-2 border-[#2E75B6] text-[#1F3864]' : (p.n < pasoIA ? 'text-green-600' : 'text-slate-400')" class="mx-auto rounded-lg px-2 py-2">
                        <div class="text-lg leading-none">{{ p.icon }}</div>
                        <div class="mt-1 text-xs font-semibold">{{ p.n }}</div>
                        <div class="text-[11px] leading-tight">{{ p.label }}</div>
                        <div v-if="p.n < pasoIA" class="text-xs">✓</div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <!-- Etapa 1 · Recepción -->
                <div v-if="pasoIA === 1">
                    <h2 class="text-lg font-semibold text-[#1F3864]">📥 Etapa 1 · Recepción del expediente</h2>
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
                    <h2 class="text-lg font-semibold text-[#1F3864]">📄 Etapa 2 · Validación documental</h2>
                    <p class="mb-4 text-sm text-slate-500">Expediente <strong>{{ expediente }}</strong> · Recepción: {{ fechaRecepcion }}</p>

                    <div v-if="documentos?.length">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Documento del postulante (ya cargado en su expediente)</label>
                        <select v-model="documentoId" class="w-full max-w-lg rounded-md border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500">
                            <option v-for="d in documentos" :key="d.id" :value="d.id">{{ TIPO_DOC[d.tipo] || d.tipo }} — {{ d.nombre }}</option>
                        </select>
                        <div v-if="docSeleccionado" class="mt-3 flex flex-wrap gap-4 text-sm text-green-700">
                            <span>✅ Documento en expediente</span><span>✅ Trazabilidad activa</span><span>✅ Formato aceptado</span>
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
                    <h2 class="text-lg font-semibold text-[#1F3864]">🔍 Etapa 3 · Extracción de cursos (OCR + IA)</h2>
                    <p class="mb-4 text-sm text-slate-500">Expediente <strong>{{ expediente }}</strong> · Documento: {{ docSeleccionado?.nombre || archivo?.name }}</p>

                    <button @click="ejecutarExtraccion" :disabled="!ia?.disponible || procesando" class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50">
                        {{ procesando ? 'Procesando con IA…' : (extraccion ? '🔁 Re-ejecutar extracción' : '🤖 Ejecutar extracción con IA') }}
                    </button>

                    <div v-if="extraccion" class="mt-5">
                        <div class="mb-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Estudiante (detectado)</dt><dd class="font-medium text-slate-700">{{ extraccion.estudiante?.nombre || '—' }}</dd></div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Universidad</dt><dd class="font-medium text-slate-700">{{ extraccion.institucion?.universidad || '—' }}</dd></div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Carrera (doc)</dt><dd class="font-medium text-slate-700">{{ extraccion.estudiante?.carrera || '—' }}</dd></div>
                        </div>
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-green-600">{{ extraccion.aprobados?.length || 0 }}</p><p class="text-xs text-slate-500">Aprobados</p></div>
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
                    <h2 class="text-lg font-semibold text-[#1F3864]">✅ Etapa 4 · Validación de aprobados</h2>
                    <p class="mb-4 text-sm text-slate-500">Ajusta la escala y la nota mínima. Los cursos que no cumplen quedan fuera del mapeo.</p>
                    <div class="mb-4 flex flex-wrap items-end gap-3">
                        <div><label class="mb-1 block text-xs font-medium text-slate-500">Escala</label>
                            <select v-model="escala" class="rounded-md border-slate-300 text-sm"><option value="0-20">0 - 20</option><option value="0-100">0 - 100</option><option value="0-5">0 - 5</option></select></div>
                        <div><label class="mb-1 block text-xs font-medium text-slate-500">Nota mínima</label>
                            <input v-model="notaMinima" type="number" step="0.1" class="w-28 rounded-md border-slate-300 text-sm" /></div>
                        <div class="flex gap-3 text-sm">
                            <span class="rounded-lg bg-green-50 px-3 py-2 text-green-700">Cumplen: <strong>{{ aprobadosValidados.length }}</strong></span>
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
                    <h2 class="text-lg font-semibold text-[#1F3864]">🔗 Etapa 5 · Mapeo USIL — {{ postulante.carrera_destino }}</h2>
                    <p class="mb-3 text-sm text-slate-500">Cada curso aprobado se empareja con un curso USIL (incluye electivos). Regla 1‑a‑1: cada curso USIL solo puede usarse una vez.</p>

                    <MapeoUsilMatch :pool-usil="poolUsil" :filas="filas" :no-convalidar="noConvalidar" :procesando="procesando"
                                     :ia="ia" solo-lectura
                                     @sugerir-ia="sugerir('ia')" @sugerir-similitud="sugerir('similitud')" />

                    <p v-if="duplicados.length" class="mt-2 text-xs text-red-600">⚠️ Hay cursos USIL asignados más de una vez. Corrige los duplicados (regla 1 a 1).</p>

                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="siguienteIA" :disabled="!puedeAvanzarIA" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 6 · Preconvalidación -->
                <div v-else-if="pasoIA === 6">
                    <h2 class="text-lg font-semibold text-[#1F3864]">📜 Etapa 6 · Preconvalidación</h2>
                    <p class="mb-4 text-sm text-slate-500">Revisa el resumen del expediente y guarda la preconvalidación. Podrás descargar el documento (PDF/Excel) al finalizar.</p>
                    <div class="mb-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Expediente</dt><dd class="font-medium text-slate-700">{{ expediente }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Solicitante</dt><dd class="font-medium text-slate-700">{{ postulante.nombre }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Universidad de origen</dt><dd class="font-medium text-slate-700">{{ universidadOrigen || '—' }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Carrera USIL destino</dt><dd class="font-medium text-slate-700">{{ postulante.carrera_destino }}</dd></div>
                    </div>
                    <div class="mb-4 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-[#1F3864]">{{ resumen.total }}</p><p class="text-xs text-slate-500">Cursos</p></div>
                        <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-green-600">{{ resumen.convalidados }}</p><p class="text-xs text-slate-500">Convalidados</p></div>
                        <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-[#2E75B6]">{{ resumen.creditos.toFixed(1) }}</p><p class="text-xs text-slate-500">Créditos reconocidos</p></div>
                    </div>

                    <!-- Pestañas de clasificación de cursos -->
                    <div class="mb-3 inline-flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-white p-1 shadow-sm">
                        <button type="button" @click="tabPreconv = 'conv'"
                                :class="tabPreconv === 'conv' ? 'bg-green-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
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
                                        <td class="px-4 py-2 font-medium text-green-700">{{ usilPorId[f.curso_usil_id] || '—' }}</td>
                                    </tr>
                                    <tr v-if="!convalidadosLista.length"><td colspan="4" class="px-4 py-6 text-center text-slate-400">Sin cursos convalidados.</td></tr>
                                </tbody>
                            </template>
                            <!-- No convalidados -->
                            <template v-else-if="tabPreconv === 'no'">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                                    <th class="px-4 py-2.5 font-semibold">Curso de origen</th><th class="w-16 px-4 py-2.5 font-semibold">Nota</th><th class="px-4 py-2.5 font-semibold">Motivo</th>
                                </tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(f, i) in noConvalidadosLista" :key="i" class="hover:bg-slate-50/70">
                                        <td class="px-4 py-2 text-slate-700">{{ f.curso_origen_nombre }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ f.nota_origen || '—' }}</td>
                                        <td class="px-4 py-2">
                                            <span class="inline-block rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                                                {{ f.clasificacion === 'no_convalidable' ? 'No convalidable (política)' : 'Sin equivalencia USIL' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!noConvalidadosLista.length"><td colspan="3" class="px-4 py-6 text-center text-slate-400">Sin cursos no convalidados.</td></tr>
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
                        <span>⚠️</span>
                        <span>Hay <strong>{{ sinAsignar }}</strong> curso(s) convalidable(s) <strong>sin asignar</strong> a un curso USIL. Puedes volver a la <button type="button" @click="pasoIA = 5" class="font-medium underline">Etapa 5 · Mapeo</button> para revisarlos, o guardar así si es intencional (quedarán como no convalidados).</span>
                    </div>

                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                        <textarea v-model="observaciones" rows="2" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]"></textarea></div>
                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="guardar" :disabled="!tieneMalla || procesando || guardadoId" class="rounded-lg bg-[#1F3864] px-6 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">
                            {{ guardadoId ? '✓ Guardada' : (procesando ? 'Guardando…' : 'Guardar preconvalidación') }}
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
    </div>
</template>
