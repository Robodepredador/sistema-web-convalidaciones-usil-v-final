<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    postulante: Object, instituciones: Array, carreras: Array, estados: Array,
    preconvalidaciones: { type: Array, default: () => [] }, preconvalidacion_estado: String,
    revision: { type: Object, default: null },
});
const editando = !!props.postulante;

// Revisión de admisión (aprobar / observar / reenviar).
const obs = reactive({ observaciones: '' });
const revProcesando = ref(false);
const REV = {
    pendiente: { label: 'Pendiente de revisión', clase: 'border-amber-200 bg-amber-50 text-amber-800' },
    aprobada:  { label: 'Aprobada',              clase: 'border-green-200 bg-green-50 text-green-800' },
    observada: { label: 'Observada',             clase: 'border-orange-200 bg-orange-50 text-orange-800' },
};
const postRevision = (url, payload) => router.post(url, payload, {
    preserveScroll: true,
    onStart: () => { revProcesando.value = true; },
    onFinish: () => { revProcesando.value = false; },
});
const aprobar = () => postRevision(`/postulantes/${props.postulante.id}/revisar`, { accion: 'aprobar' });
const observar = () => {
    if (!obs.observaciones.trim()) return;
    postRevision(`/postulantes/${props.postulante.id}/revisar`, { accion: 'observar', observaciones: obs.observaciones });
};
const reenviar = () => postRevision(`/postulantes/${props.postulante.id}/reenviar-revision`, {});

// Badge del estado de preconvalidación derivado (resultado de la evaluación del coordinador).
const PRECONV = {
    pendiente:   { label: 'Pendiente de evaluación', clase: 'bg-slate-100 text-slate-500 ring-slate-200' },
    atendida:    { label: 'Preconvalidación lista',  clase: 'bg-blue-50 text-[#2E75B6] ring-blue-200' },
    convalidada: { label: 'Convalidada',             clase: 'bg-green-50 text-green-700 ring-green-200' },
};
// Detalle de cada expediente (curso a curso), expandible bajo demanda.
const detalleAbierto = reactive({});
const toggleDetalle = (id) => { detalleAbierto[id] = !detalleAbierto[id]; };

const GENEROS = [
    { value: 'masculino', label: 'Masculino' },
    { value: 'femenino', label: 'Femenino' },
    { value: 'otro', label: 'Otro' },
    { value: 'no_especifica', label: 'Prefiere no especificar' },
];
const NACIONALIDADES = ['Peruana', 'Argentina', 'Boliviana', 'Brasileña', 'Chilena', 'Colombiana', 'Costarricense',
    'Cubana', 'Dominicana', 'Ecuatoriana', 'Salvadoreña', 'Española', 'Estadounidense', 'Francesa', 'Guatemalteca',
    'Hondureña', 'Italiana', 'Mexicana', 'Nicaragüense', 'Panameña', 'Paraguaya', 'Portuguesa', 'Puertorriqueña',
    'Uruguaya', 'Venezolana', 'China', 'Japonesa', 'Coreana', 'Alemana', 'Británica', 'Canadiense', 'Otra'];

const form = useForm({
    tipo_documento: props.postulante?.tipo_documento && props.postulante.tipo_documento !== 'TEMP' ? props.postulante.tipo_documento : 'DNI',
    numero_documento: props.postulante?.tipo_documento === 'TEMP' ? '' : (props.postulante?.numero_documento ?? ''),
    sin_documento: props.postulante?.sin_documento ?? false,
    nombres: props.postulante?.nombres ?? '',
    apellido_paterno: props.postulante?.apellido_paterno ?? '',
    apellido_materno: props.postulante?.apellido_materno ?? '',
    genero: props.postulante?.genero ?? '',
    fecha_nacimiento: props.postulante?.fecha_nacimiento ? String(props.postulante.fecha_nacimiento).substring(0, 10) : '',
    nacionalidad: props.postulante?.nacionalidad ?? 'Peruana',
    email: props.postulante?.email ?? '',
    telefono: props.postulante?.telefono ?? '',
    pais_residencia: props.postulante?.pais_residencia ?? '',
    direccion: props.postulante?.direccion ?? '',
    institucion_origen_id: props.postulante?.institucion_origen_id ?? '',
    carrera_externa_id: props.postulante?.carrera_externa_id ?? '',
    carrera_destino_ids: props.postulante?.carrera_destino_ids ?? [],
    ciclo_postulacion: props.postulante?.ciclo_postulacion ?? '',
    observaciones: props.postulante?.observaciones ?? '',
    certificado: null,
    silabos: null,
    constancia: null,
    borrador: false,
});

const PASOS = [
    { n: 1, label: 'Datos generales', icon: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z' },
    { n: 2, label: 'Contacto', icon: 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z' },
    { n: 3, label: 'Procedencia académica', icon: 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z' },
    { n: 4, label: 'Destino en USIL', icon: 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342' },
    { n: 5, label: 'Documentos', icon: 'M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776' },
    { n: 6, label: 'Confirmación', icon: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' },
];

const paso = ref(1);
const errores = reactive({});
const err = (campo) => errores[campo] || form.errors[campo];

// Carreras de origen cargadas bajo demanda según la institución.
const carrerasOrigen = ref([]);
const cargandoCarreras = ref(false);
const cargarCarreras = async (institucionId) => {
    if (!institucionId) { carrerasOrigen.value = []; return; }
    cargandoCarreras.value = true;
    try {
        const { data } = await window.axios.get('/catalogo/carreras-externas', { params: { institucion_id: institucionId } });
        carrerasOrigen.value = data;
    } finally {
        cargandoCarreras.value = false;
    }
};
const onInstitucionChange = (id) => { form.institucion_origen_id = id; form.carrera_externa_id = ''; cargarCarreras(id); };

// Crea una carrera de origen nueva al vuelo (mantenible) y la selecciona.
const crearCarreraOrigen = async (nombre) => {
    if (!form.institucion_origen_id || !nombre?.trim()) return;
    try {
        const { data } = await window.axios.post('/catalogo/carreras-externas', {
            institucion_id: form.institucion_origen_id,
            nombre: nombre.trim(),
        });
        if (!carrerasOrigen.value.some((c) => c.id === data.id)) {
            carrerasOrigen.value.push(data);
            carrerasOrigen.value.sort((a, b) => a.nombre.localeCompare(b.nombre));
        }
        form.carrera_externa_id = data.id;
    } catch (e) {
        alert(e.response?.data?.message || 'No se pudo crear la carrera.');
    }
};
onMounted(() => { if (form.institucion_origen_id) cargarCarreras(form.institucion_origen_id); });

const carrerasDestinoSel = computed(() =>
    form.carrera_destino_ids.map((id) => props.carreras.find((c) => c.id == id)).filter(Boolean));
const carreraDestinoNombre = computed(() =>
    carrerasDestinoSel.value.length ? carrerasDestinoSel.value.map((c) => c.nombre).join(', ') : '—');
const institucionNombre = computed(() => props.instituciones.find((i) => i.id == form.institucion_origen_id)?.nombre ?? '—');

// Multi-select de carreras destino: añadir (sin duplicar) y quitar.
const agregarDestino = (id) => {
    if (id && !form.carrera_destino_ids.some((x) => x == id)) form.carrera_destino_ids.push(id);
};
const quitarDestino = (id) => {
    form.carrera_destino_ids = form.carrera_destino_ids.filter((x) => x != id);
};
// Opciones disponibles (ocultar las ya seleccionadas).
const carrerasDestinoDisponibles = computed(() =>
    carrerasDestinoOpts.value.filter((o) => !form.carrera_destino_ids.some((x) => x == o.value)));

const institucionesOpts = computed(() => props.instituciones.map((i) => ({ value: i.id, label: i.nombre })));
const carrerasDestinoOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));
const carrerasOrigenOpts = computed(() => carrerasOrigen.value.map((c) => ({ value: c.id, label: c.nombre })));

const archivo = (campo, e) => { form[campo] = e.target.files[0] ?? null; };

// Pasos que exigen campos completos antes de continuar (5 = documentos y 6 = confirmación son opcionales).
const PASOS_OBLIGATORIOS = [1, 2, 3, 4];

function validarPaso(n) {
    Object.keys(errores).forEach((k) => delete errores[k]);
    if (n === 1) {
        if (!form.sin_documento) {
            if (!form.numero_documento.trim()) errores.numero_documento = 'Ingresa el número de documento.';
        }
        if (!form.nombres.trim()) errores.nombres = 'Ingresa los nombres.';
        if (!form.apellido_paterno.trim()) errores.apellido_paterno = 'Ingresa el apellido paterno.';
    }
    if (n === 2) {
        if (!form.email.trim()) errores.email = 'El correo es obligatorio.';
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errores.email = 'Correo no válido.';
        if (!form.telefono.trim()) errores.telefono = 'Ingresa el teléfono.';
        if (!form.pais_residencia.trim()) errores.pais_residencia = 'Ingresa el país de residencia.';
        if (!form.direccion.trim()) errores.direccion = 'Ingresa la dirección.';
    }
    if (n === 3) {
        if (!form.institucion_origen_id) errores.institucion_origen_id = 'Selecciona la institución de origen.';
        if (!form.carrera_externa_id) errores.carrera_externa_id = 'Selecciona la carrera de origen.';
    }
    if (n === 4) {
        if (!form.carrera_destino_ids.length) errores.carrera_destino_ids = 'Selecciona al menos una carrera destino.';
        if (!form.ciclo_postulacion.trim()) errores.ciclo_postulacion = 'Ingresa el ciclo (AAAA-N).';
        else if (!/^\d{4}-\d$/.test(form.ciclo_postulacion)) errores.ciclo_postulacion = 'Formato AAAA-N (ej. 2026-1).';
    }
    return Object.keys(errores).length === 0;
}

// Primer paso obligatorio que quede incompleto (o null si todos están completos).
const primerPasoInvalido = (hasta) => {
    for (const p of PASOS_OBLIGATORIOS) {
        if (p >= hasta) break;
        if (!validarPaso(p)) return p;
    }
    return null;
};

const irPaso = (n) => {
    // Retroceder siempre está permitido; avanzar exige completar los pasos intermedios.
    if (n <= paso.value) { paso.value = n; return; }
    const invalido = primerPasoInvalido(n);
    paso.value = invalido ?? n;
};

const siguiente = () => { if (validarPaso(paso.value)) paso.value = Math.min(6, paso.value + 1); };
const anterior = () => { paso.value = Math.max(1, paso.value - 1); };

const enviar = (borrador) => {
    form.borrador = borrador;
    // En registro completo, valida todos los pasos obligatorios antes de enviar.
    if (!borrador) {
        const invalido = primerPasoInvalido(7);
        if (invalido !== null) { paso.value = invalido; return; }
    }
    const url = editando ? `/postulantes/${props.postulante.id}` : '/postulantes';
    const opts = { forceFormData: true, preserveScroll: true };
    editando ? form.put(url, opts) : form.post(url, opts);
};

const inputCls = 'w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]';
</script>

<template>
    <div>
        <!-- Encabezado -->
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link href="/postulantes" class="inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-slate-400 hover:text-[#2E75B6]">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    Postulantes
                </Link>
                <h1 class="mt-1 text-2xl font-semibold text-[#1F3864]">{{ editando ? 'Editar postulante' : 'Nuevo postulante' }}</h1>
                <p class="mt-1 text-sm text-slate-500">Registra la información del postulante para su proceso de convalidación.</p>
            </div>
            <div class="flex items-center gap-2">
                <Link href="/postulantes" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancelar y salir</Link>
                <button @click="enviar(true)" :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864] disabled:opacity-60">
                    Guardar borrador
                </button>
            </div>
        </div>

        <!-- Revisión de admisión: aprobar/observar (Ejecutivo) o reenviar (Asesor) -->
        <div v-if="editando && revision" class="mb-5 rounded-2xl border p-4 shadow-sm" :class="REV[revision.estado]?.clase">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Revisión de admisión: {{ REV[revision.estado]?.label ?? revision.estado }}</h2>
                <span v-if="revision.revisado_por" class="text-xs opacity-80">{{ revision.revisado_por }} · {{ revision.revisado_en }}</span>
            </div>

            <!-- Observación visible para el asesor -->
            <p v-if="revision.estado === 'observada' && revision.observaciones" class="mt-2 text-sm">
                <span class="font-medium">Observación:</span> {{ revision.observaciones }}
            </p>

            <!-- Ejecutivo Comercial: aprobar u observar -->
            <div v-if="revision.puede_revisar" class="mt-3 space-y-2">
                <textarea v-model="obs.observaciones" rows="2" placeholder="Detalle de la observación (obligatorio para observar)"
                          class="w-full rounded-md border-slate-300 text-sm"></textarea>
                <div class="flex gap-2">
                    <button type="button" @click="aprobar" :disabled="revProcesando"
                            class="rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800 disabled:opacity-50">Aprobar</button>
                    <button type="button" @click="observar" :disabled="revProcesando || !obs.observaciones.trim()"
                            class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-40">Observar</button>
                </div>
            </div>

            <!-- Asesor dueño: reenviar tras corregir -->
            <button v-else-if="revision.puede_reenviar && revision.estado === 'observada'" type="button" @click="reenviar" :disabled="revProcesando"
                    class="mt-3 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">
                Reenviar a revisión
            </button>
        </div>

        <!-- Preconvalidación (solo lectura): resultado de la evaluación del coordinador -->
        <div v-if="editando" class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Preconvalidación</h2>
                <span :class="PRECONV[preconvalidacion_estado]?.clase" class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                    {{ PRECONV[preconvalidacion_estado]?.label }}
                </span>
            </div>

            <div v-if="preconvalidaciones.length" class="space-y-2">
                <div v-for="s in preconvalidaciones" :key="s.id" class="rounded-lg border border-slate-200 bg-slate-50/60">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2 text-sm">
                        <span class="font-medium text-slate-700">Expediente #{{ s.id }}</span>
                        <span class="text-slate-500">{{ s.carrera || '—' }}</span>
                        <span class="text-xs text-slate-400">{{ s.metodo === 'ia' ? 'Asistida' : 'Manual' }} · {{ s.fecha }}</span>
                        <span class="text-slate-600"><strong>{{ s.convalidados }}</strong> convalidados · <strong>{{ s.creditos.toFixed(1) }}</strong> créditos</span>
                        <span v-if="s.convalidada" class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-200">Convalidada · {{ s.memorandum }}</span>
                        <span v-else class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium capitalize text-[#2E75B6] ring-1 ring-inset ring-blue-200">{{ s.estado }}</span>
                        <div class="ml-auto flex items-center gap-3">
                            <button type="button" @click="toggleDetalle(s.id)" class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-[#2E75B6]">
                                <svg class="h-3.5 w-3.5 transition-transform" :class="detalleAbierto[s.id] ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                {{ detalleAbierto[s.id] ? 'Ocultar detalle' : 'Ver detalle' }}
                            </button>
                            <a :href="s.pdf" target="_blank" rel="noopener" class="text-xs font-medium text-[#2E75B6] hover:underline">PDF</a>
                            <a :href="s.excel" target="_blank" rel="noopener" class="text-xs font-medium text-green-700 hover:underline">Excel</a>
                        </div>
                    </div>
                    <div v-if="detalleAbierto[s.id]" class="overflow-x-auto border-t border-slate-200 px-3 py-2">
                        <table class="min-w-full text-xs">
                            <thead class="text-left text-slate-400">
                                <tr>
                                    <th class="py-1 pr-4 font-semibold">Curso de origen</th>
                                    <th class="py-1 pr-4 font-semibold">Nota</th>
                                    <th class="py-1 pr-4 font-semibold">Convalida con (USIL)</th>
                                    <th class="py-1 text-right font-semibold">Créd.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(c, i) in s.cursos" :key="i">
                                    <td class="py-1.5 pr-4 text-slate-600">{{ c.origen }}</td>
                                    <td class="py-1.5 pr-4 text-slate-500">{{ c.nota || '—' }}</td>
                                    <td class="py-1.5 pr-4 font-medium text-slate-700">{{ c.usil || '—' }}</td>
                                    <td class="py-1.5 text-right text-slate-600">{{ c.creditos.toFixed(1) }}</td>
                                </tr>
                                <tr v-if="!s.cursos.length"><td colspan="4" class="py-3 text-center text-slate-400">Sin cursos convalidados en este expediente.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-xs text-slate-400">Resultado de la evaluación del coordinador. Solo lectura.</p>
            </div>
            <p v-else class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-sm text-slate-400">
                Aún no hay una preconvalidación para este postulante. Aparecerá aquí cuando el coordinador la genere.
            </p>
        </div>

        <!-- Pestañas de pasos -->
        <div class="mb-5 grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-3 lg:grid-cols-6">
            <button v-for="p in PASOS" :key="p.n" @click="irPaso(p.n)"
                    :class="paso === p.n ? 'bg-[#2E75B6]/10 text-[#1F3864]' : 'text-slate-500 hover:bg-slate-50'"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium">
                <svg class="h-5 w-5 shrink-0" :class="paso === p.n ? 'text-[#2E75B6]' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path :d="p.icon" /></svg>
                <span class="truncate">{{ p.n }}. {{ p.label }}</span>
            </button>
        </div>

        <!-- Contenido del paso -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <!-- Paso 1: Datos generales -->
            <div v-show="paso === 1">
                <h2 class="text-lg font-semibold text-[#1F3864]">Datos generales</h2>
                <p class="mb-5 text-sm text-slate-500">Ingresa la información básica de identificación del postulante.</p>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Tipo de documento</label>
                        <select v-model="form.tipo_documento" :disabled="form.sin_documento" :class="inputCls">
                            <option value="DNI">DNI</option>
                            <option value="CE">Carné de extranjería</option>
                            <option value="PASAPORTE">Pasaporte</option>
                            <option value="PTP">PTP</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Número de documento</label>
                        <input v-model="form.numero_documento" :disabled="form.sin_documento" type="text" placeholder="Ingresa el número de documento" :class="inputCls" />
                        <p v-if="err('numero_documento')" class="mt-1 text-xs text-red-600">{{ err('numero_documento') }}</p>
                    </div>
                    <label class="flex items-start gap-2 pt-7 text-sm text-slate-600">
                        <input v-model="form.sin_documento" type="checkbox" class="mt-0.5 rounded border-slate-300 text-[#2E75B6]" />
                        El postulante no presenta documento de identidad
                    </label>
                </div>

                <div v-if="form.sin_documento" class="mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    <span>ℹ️</span> Si el postulante no cuenta con documento, se generará un identificador temporal único.
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nombres</label>
                        <input v-model="form.nombres" type="text" placeholder="Ingresa nombres" :class="inputCls" />
                        <p v-if="err('nombres')" class="mt-1 text-xs text-red-600">{{ err('nombres') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Apellido paterno</label>
                        <input v-model="form.apellido_paterno" type="text" placeholder="Ingresa apellido paterno" :class="inputCls" />
                        <p v-if="err('apellido_paterno')" class="mt-1 text-xs text-red-600">{{ err('apellido_paterno') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Apellido materno</label>
                        <input v-model="form.apellido_materno" type="text" placeholder="Ingresa apellido materno" :class="inputCls" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Género <span class="text-slate-400">(opcional)</span></label>
                        <Autocomplete v-model="form.genero" :options="GENEROS" placeholder="Escribe o selecciona…" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Fecha de nacimiento <span class="text-slate-400">(opcional)</span></label>
                        <input v-model="form.fecha_nacimiento" type="date" :class="inputCls" />
                        <p v-if="err('fecha_nacimiento')" class="mt-1 text-xs text-red-600">{{ err('fecha_nacimiento') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nacionalidad <span class="text-slate-400">(opcional)</span></label>
                        <Autocomplete v-model="form.nacionalidad" :options="NACIONALIDADES" :allow-free="true" placeholder="Escribe la nacionalidad…" />
                    </div>
                </div>
            </div>

            <!-- Paso 2: Contacto -->
            <div v-show="paso === 2">
                <h2 class="text-lg font-semibold text-[#1F3864]">Contacto</h2>
                <p class="mb-5 text-sm text-slate-500">Datos de contacto del postulante. El correo se usará para su acceso al portal.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Correo electrónico</label>
                        <input v-model="form.email" type="email" placeholder="correo@example.com" :class="inputCls" />
                        <p v-if="err('email')" class="mt-1 text-xs text-red-600">{{ err('email') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Teléfono</label>
                        <input v-model="form.telefono" type="text" :class="inputCls" />
                        <p v-if="err('telefono')" class="mt-1 text-xs text-red-600">{{ err('telefono') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">País de residencia</label>
                        <input v-model="form.pais_residencia" type="text" :class="inputCls" />
                        <p v-if="err('pais_residencia')" class="mt-1 text-xs text-red-600">{{ err('pais_residencia') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Dirección</label>
                        <input v-model="form.direccion" type="text" :class="inputCls" />
                        <p v-if="err('direccion')" class="mt-1 text-xs text-red-600">{{ err('direccion') }}</p>
                    </div>
                </div>
            </div>

            <!-- Paso 3: Procedencia académica -->
            <div v-show="paso === 3">
                <h2 class="text-lg font-semibold text-[#1F3864]">Procedencia académica</h2>
                <p class="mb-5 text-sm text-slate-500">Institución y carrera de origen del postulante.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Institución de origen</label>
                        <Autocomplete :model-value="form.institucion_origen_id" @update:modelValue="onInstitucionChange" :options="institucionesOpts" placeholder="Escribe el nombre de la institución…" />
                        <p v-if="err('institucion_origen_id')" class="mt-1 text-xs text-red-600">{{ err('institucion_origen_id') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Carrera de origen</label>
                        <Autocomplete v-model="form.carrera_externa_id" :options="carrerasOrigenOpts" :disabled="!form.institucion_origen_id"
                                       :creatable="!!form.institucion_origen_id" @create="crearCarreraOrigen"
                                       :placeholder="cargandoCarreras ? 'Cargando…' : (form.institucion_origen_id ? 'Escribe o agrega la carrera…' : 'Elige una institución primero')" />
                        <p v-if="err('carrera_externa_id')" class="mt-1 text-xs text-red-600">{{ err('carrera_externa_id') }}</p>
                    </div>
                </div>
            </div>

            <!-- Paso 4: Destino en USIL -->
            <div v-show="paso === 4">
                <h2 class="text-lg font-semibold text-[#1F3864]">Destino en USIL</h2>
                <p class="mb-5 text-sm text-slate-500">Carrera(s) USIL a las que postula y ciclo de postulación. Puedes solicitar una o más simulaciones.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Carreras destino (USIL) <span class="text-slate-400">— una o más</span>
                        </label>
                        <Autocomplete :model-value="''" :options="carrerasDestinoDisponibles"
                                      @update:modelValue="agregarDestino" placeholder="Escribe y agrega una carrera USIL…" />
                        <!-- Chips de carreras seleccionadas -->
                        <div v-if="carrerasDestinoSel.length" class="mt-2 flex flex-wrap gap-2">
                            <span v-for="c in carrerasDestinoSel" :key="c.id"
                                  class="inline-flex items-center gap-1.5 rounded-full bg-[#2E75B6]/10 py-1 pl-3 pr-1.5 text-xs font-medium text-[#1F3864]">
                                {{ c.nombre }}
                                <button type="button" @click="quitarDestino(c.id)"
                                        class="flex h-4 w-4 items-center justify-center rounded-full text-[#1F3864]/60 hover:bg-[#1F3864]/15 hover:text-[#1F3864]">✕</button>
                            </span>
                        </div>
                        <p v-else class="mt-2 text-xs text-slate-400">Aún no has agregado carreras destino.</p>
                        <p v-if="err('carrera_destino_ids')" class="mt-1 text-xs text-red-600">{{ err('carrera_destino_ids') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Ciclo de postulación</label>
                        <input v-model="form.ciclo_postulacion" type="text" placeholder="2026-1" :class="inputCls" />
                        <p v-if="err('ciclo_postulacion')" class="mt-1 text-xs text-red-600">{{ err('ciclo_postulacion') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                        <textarea v-model="form.observaciones" rows="2" :class="inputCls"></textarea>
                    </div>
                </div>
            </div>

            <!-- Paso 5: Documentos -->
            <div v-show="paso === 5">
                <h2 class="text-lg font-semibold text-[#1F3864]">Documentos</h2>
                <p class="mb-5 text-sm text-slate-500">Adjunta los documentos de sustento (opcional). PDF o imagen, máx. 5–10 MB.</p>
                <div class="space-y-3">
                    <div v-for="doc in [{k:'certificado',l:'Certificado de estudios'},{k:'silabos',l:'Sílabos de cursos'},{k:'constancia',l:'Constancia de matrícula / conducta'}]" :key="doc.k"
                         class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-3">
                        <div>
                            <p class="text-sm font-medium text-slate-700">{{ doc.l }}</p>
                            <p class="text-xs text-slate-400">{{ form[doc.k]?.name || 'Ningún archivo seleccionado' }}</p>
                            <p v-if="err(doc.k)" class="mt-1 text-xs text-red-600">{{ err(doc.k) }}</p>
                        </div>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.zip" @change="(e) => archivo(doc.k, e)" class="text-sm text-slate-600" />
                    </div>
                    <p v-if="editando && postulante.documentos?.length" class="text-xs text-slate-400">
                        Documentos ya cargados: {{ postulante.documentos.map(d => d.nombre).join(', ') }}
                    </p>
                </div>
            </div>

            <!-- Paso 6: Confirmación -->
            <div v-show="paso === 6">
                <h2 class="text-lg font-semibold text-[#1F3864]">Confirmación</h2>
                <p class="mb-5 text-sm text-slate-500">Revisa los datos antes de registrar al postulante.</p>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between border-b border-slate-100 py-1"><dt class="text-slate-400">Documento</dt><dd class="font-medium text-slate-700">{{ form.sin_documento ? 'Sin documento (temporal)' : `${form.tipo_documento} ${form.numero_documento}` }}</dd></div>
                    <div class="flex justify-between border-b border-slate-100 py-1"><dt class="text-slate-400">Postulante</dt><dd class="font-medium text-slate-700">{{ form.apellido_paterno }} {{ form.apellido_materno }}, {{ form.nombres }}</dd></div>
                    <div class="flex justify-between border-b border-slate-100 py-1"><dt class="text-slate-400">Correo</dt><dd class="text-slate-700">{{ form.email || '—' }}</dd></div>
                    <div class="flex justify-between border-b border-slate-100 py-1"><dt class="text-slate-400">Teléfono</dt><dd class="text-slate-700">{{ form.telefono || '—' }}</dd></div>
                    <div class="flex justify-between border-b border-slate-100 py-1"><dt class="text-slate-400">Procedencia</dt><dd class="text-slate-700">{{ institucionNombre }}</dd></div>
                    <div class="flex justify-between border-b border-slate-100 py-1"><dt class="text-slate-400">Carrera destino</dt><dd class="text-slate-700">{{ carreraDestinoNombre }}</dd></div>
                    <div class="flex justify-between border-b border-slate-100 py-1"><dt class="text-slate-400">Ciclo</dt><dd class="text-slate-700">{{ form.ciclo_postulacion || '—' }}</dd></div>
                </dl>
                <div class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-500">
                    Al registrar, si hay correo se generará el acceso al portal del postulante con una contraseña temporal.
                </div>
            </div>

            <!-- Pie de navegación -->
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                <div class="flex items-center gap-1.5">
                    <span v-for="p in PASOS" :key="p.n" @click="irPaso(p.n)"
                          :class="p.n === paso ? 'bg-[#1F3864] text-white' : (p.n < paso ? 'bg-[#2E75B6]/20 text-[#2E75B6]' : 'bg-slate-100 text-slate-400')"
                          class="grid h-7 w-7 cursor-pointer place-items-center rounded-full text-xs font-semibold">{{ p.n }}</span>
                    <span class="ml-2 text-xs text-slate-400">Paso {{ paso }} de 6</span>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="paso > 1" @click="anterior" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Anterior</button>
                    <button v-if="paso < 6" @click="siguiente" class="inline-flex items-center gap-1 rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6]">
                        Siguiente <span aria-hidden="true">›</span>
                    </button>
                    <button v-else @click="enviar(false)" :disabled="form.processing"
                            class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                        {{ editando ? 'Guardar cambios' : 'Registrar postulante' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
