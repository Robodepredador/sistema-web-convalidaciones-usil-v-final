<script setup>
import { useForm, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import VolverA from '../../Components/VolverA.vue';

const props = defineProps({
    postulante: Object,
    instituciones: Array,
    carreras: Array,
    estados: Array,
    preconvalidaciones: { type: Array, default: () => [] },
    preconvalidacion_estado: String,
    revision: { type: Object, default: null },
    documentos_tipos: { type: Object, default: () => ({}) },
    es_ejecutivo: Boolean,
    es_asesor: Boolean,
});

const editando = !!props.postulante;

// RBAC: distingue el flujo del Asesor (registro) del Ejecutivo (revisión).
const userRol = computed(() => {
    const r = usePage().props.auth?.user?.rol;
    if (!r) return '';
    return typeof r === 'object' ? (r.nombre ?? '') : String(r);
});
const permisos = computed(() => usePage().props.auth?.user?.permisos ?? []);
const puede = (clave) => permisos.value.includes('*') || permisos.value.includes(clave);
const esEjecutivo = computed(() => props.es_ejecutivo || userRol.value === 'Ejecutivo Comercial de Admisión' || (puede('solicitudes.validar') && !puede('evaluacion.editar') && userRol.value !== 'Asesor de Admisión'));
const esAsesor = computed(() => props.es_asesor || userRol.value === 'Asesor de Admisión');
const esRegistrador = computed(() => esAsesor.value || puede('solicitudes.crear'));
const esRevisor = computed(() => esEjecutivo.value || (puede('solicitudes.validar') && !esAsesor.value));

const mostrandoReevaluacion = ref(false);

const docAdjunto = (tipo) => props.postulante?.documentos?.find((d) => d.tipo === tipo);

// Carga y reemplazo directo de documentos desde la ficha
const subiendoDoc = reactive({});
const fileInputs = reactive({});

const dispararSeleccionDoc = (tipo) => {
    fileInputs[tipo]?.click();
};

const reemplazarDoc = (tipo, event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    subiendoDoc[tipo] = true;
    const formData = new FormData();
    formData.append('tipo', tipo);
    formData.append('archivo', file);

    router.post(`/postulantes/${props.postulante.id}/documentos`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            subiendoDoc[tipo] = false;
            if (fileInputs[tipo]) fileInputs[tipo].value = '';
        },
    });
};

// Revisión de admisión (aprobar / observar / reenviar).
const obs = reactive({ observaciones: '' });
const revProcesando = ref(false);
const errorRevision = ref('');
const REV = {
    pendiente: { label: 'Pendiente de revisión', clase: 'border-amber-200 bg-amber-50 text-amber-800' },
    aprobada:  { label: 'Aprobada',              clase: 'border-emerald-200 bg-emerald-50 text-emerald-800' },
    observada: { label: 'Observada',             clase: 'border-rose-200 bg-rose-50 text-rose-800' },
};

const confirmacion = ref(null);

const postRevision = (url, payload) => router.post(url, payload, {
    preserveScroll: true,
    onStart: () => { revProcesando.value = true; },
    onFinish: () => { revProcesando.value = false; confirmacion.value = null; },
});

const cancelarConfirmacion = () => { confirmacion.value = null; };
const ejecutarConfirmacion = () => confirmacion.value?.accion?.();

const nombreCompleto = computed(() => {
    const apellidos = [props.postulante?.apellido_paterno, props.postulante?.apellido_materno].filter(Boolean).join(' ');
    return [apellidos, props.postulante?.nombres].filter(Boolean).join(', ') || '—';
});

// Art. 24 del Reglamento de Admisión: apto solo con todos los documentos.
const faltantes = computed(() => props.revision?.documentos_faltantes ?? []);

const aprobar = () => {
    const incompleto = faltantes.value.length > 0;
    if (incompleto && !obs.observaciones.trim()) {
        errorRevision.value = 'Falta documentación: para aprobar de forma provisional indica qué falta y bajo qué declaración jurada se admite.';
        return;
    }
    errorRevision.value = '';
    confirmacion.value = {
        titulo: incompleto ? '¿Aprobar de forma PROVISIONAL?' : '¿Aprobar este expediente?',
        mensaje: incompleto
            ? `Faltan ${faltantes.value.length} documento(s). El expediente avanzará a evaluación con la regularización pendiente, y así constará para el postulante.`
            : 'Pasará a evaluación del coordinador y el postulante verá el avance en su portal. La aprobación no se puede revertir desde aquí.',
        textoConfirmar: incompleto ? 'Sí, aprobar provisionalmente' : 'Sí, aprobar',
        tono: incompleto ? 'aviso' : 'exito',
        detalle: incompleto ? faltantes.value.join(' · ') : null,
        accion: () => postRevision(`/postulantes/${props.postulante.id}/revisar`,
            { accion: 'aprobar', provisional: incompleto, observaciones: obs.observaciones }),
    };
};

const observar = () => {
    if (!obs.observaciones.trim()) {
        errorRevision.value = 'Debes escribir el motivo de la observación en el campo de texto antes de observar el expediente.';
        return;
    }
    errorRevision.value = '';
    confirmacion.value = {
        titulo: '¿Observar este expediente?',
        mensaje: 'El asesor y el postulante leerán la observación. El expediente quedará observado y no avanzará a evaluación hasta que se subsane y reenvíe.',
        textoConfirmar: 'Sí, observar expediente',
        tono: 'peligro',
        detalle: obs.observaciones.trim(),
        accion: () => postRevision(`/postulantes/${props.postulante.id}/revisar`, { accion: 'observar', observaciones: obs.observaciones }),
    };
};

// Modal de subsanación y reenvío
const modalReenviarAbierto = ref(false);
const notaSubsanacion = ref('');

const abrirModalReenviar = () => {
    notaSubsanacion.value = '';
    modalReenviarAbierto.value = true;
};

const confirmarReenviar = () => {
    postRevision(`/postulantes/${props.postulante.id}/reenviar-revision`, {
        nota_subsanacion: notaSubsanacion.value.trim(),
    });
    modalReenviarAbierto.value = false;
};

// Detalle de cada expediente en la vista de preconvalidación
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

const DOCUMENTOS_REGLAS = {
    DNI:       { re: /^\d{8}$/,             max: 8,  numerico: true,  hint: '8 dígitos',            msg: 'El DNI debe tener exactamente 8 dígitos.' },
    CE:        { re: /^[A-Za-z0-9]{9,12}$/, max: 12, numerico: false, hint: '9 a 12 alfanuméricos', msg: 'El carné de extranjería debe tener de 9 a 12 caracteres alfanuméricos.' },
    PASAPORTE: { re: /^[A-Za-z0-9]{6,12}$/, max: 12, numerico: false, hint: '6 a 12 alfanuméricos', msg: 'El pasaporte debe tener de 6 a 12 caracteres alfanuméricos.' },
    PTP:       { re: /^[A-Za-z0-9]{9,12}$/, max: 12, numerico: false, hint: '9 a 12 alfanuméricos', msg: 'El PTP debe tener de 9 a 12 caracteres alfanuméricos.' },
};

const RE_NOMBRE = /^[\p{L}\p{M}\s'’.-]+$/u;
const RE_TELEFONO = /^[0-9+()\s-]{6,20}$/;
const RE_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

const EDAD_MINIMA = 15;

const edad = (iso) => {
    const [y, m, d] = String(iso).split('-').map(Number);
    if (!y || !m || !d) return NaN;
    const hoy = new Date();
    const cumplido = hoy.getMonth() + 1 > m || (hoy.getMonth() + 1 === m && hoy.getDate() >= d);
    return hoy.getFullYear() - y - (cumplido ? 0 : 1);
};

const maxNacimiento = (() => {
    const h = new Date();
    return `${h.getFullYear() - EDAD_MINIMA}-${String(h.getMonth() + 1).padStart(2, '0')}-${String(h.getDate()).padStart(2, '0')}`;
})();

const cicloActual = (() => {
    const h = new Date();
    return `${h.getFullYear()}-${h.getMonth() < 6 ? 1 : 2}`;
})();

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
    institucion_origen_id: props.postulante?.institucion_origen_id ?? '',
    carrera_externa_id: props.postulante?.carrera_externa_id ?? '',
    carrera_destino_ids: props.postulante?.carrera_destino_ids ?? [],
    ciclo_postulacion: props.postulante?.ciclo_postulacion ?? cicloActual,
    observaciones: props.postulante?.observaciones ?? '',
    consentimiento_datos: props.postulante?.consentimiento_datos ?? false,
    dni: null,
    certificado: null,
    silabos: null,
    constancia: null,
    solicitud: null,
    borrador: false,
});

const PASOS = [
    { n: 1, label: 'Datos Generales', desc: 'Identificación y contacto' },
    { n: 2, label: 'Procedencia Académica', desc: 'Institución y carrera origen' },
    { n: 3, label: 'Destino en USIL', desc: 'Carrera(s) de interés' },
    { n: 4, label: 'Expediente Documental', desc: 'Requisitos oficiales' },
    { n: 5, label: 'Confirmación', desc: 'Resumen y consentimiento' },
];
const ULTIMO_PASO = PASOS.length;

const paso = ref(1);
const errores = reactive({});
const err = (campo) => errores[campo] || form.errors[campo];
const reglaDoc = computed(() => DOCUMENTOS_REGLAS[form.tipo_documento] ?? DOCUMENTOS_REGLAS.DNI);

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

// Crea una carrera de origen nueva al vuelo y la selecciona.
const guardandoCarrera = ref(false);
const modalNuevaCarreraVisible = ref(false);
const nuevaCarreraNombre = ref('');

const abrirModalNuevaCarrera = () => {
    nuevaCarreraNombre.value = '';
    modalNuevaCarreraVisible.value = true;
};

const crearCarreraOrigen = async (nombre) => {
    if (!form.institucion_origen_id || !nombre?.trim()) return;
    guardandoCarrera.value = true;
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
        alert(e.response?.data?.message || 'No se pudo registrar la carrera de origen.');
    } finally {
        guardandoCarrera.value = false;
    }
};

const guardarNuevaCarreraDesdeModal = async () => {
    const nom = nuevaCarreraNombre.value.trim();
    if (!nom) return;
    await crearCarreraOrigen(nom);
    modalNuevaCarreraVisible.value = false;
};

onMounted(() => { if (form.institucion_origen_id) cargarCarreras(form.institucion_origen_id); });

const carrerasDestinoSel = computed(() =>
    form.carrera_destino_ids.map((id) => props.carreras.find((c) => c.id == id)).filter(Boolean));
const carreraDestinoNombre = computed(() =>
    carrerasDestinoSel.value.length ? carrerasDestinoSel.value.map((c) => c.nombre).join(', ') : '—');
const institucionNombre = computed(() => props.instituciones.find((i) => i.id == form.institucion_origen_id)?.nombre ?? '—');
const carreraOrigenNombre = computed(() => carrerasOrigen.value.find((c) => c.id == form.carrera_externa_id)?.nombre ?? '—');

const agregarDestino = (id) => {
    if (id && !form.carrera_destino_ids.some((x) => x == id)) form.carrera_destino_ids.push(id);
};
const quitarDestino = (id) => {
    form.carrera_destino_ids = form.carrera_destino_ids.filter((x) => x != id);
};

const carrerasDestinoDisponibles = computed(() =>
    carrerasDestinoOpts.value.filter((o) => !form.carrera_destino_ids.some((x) => x == o.value)));

const institucionesOpts = computed(() => props.instituciones.map((i) => ({ value: i.id, label: i.nombre })));
const carrerasDestinoOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));
const carrerasOrigenOpts = computed(() => carrerasOrigen.value.map((c) => ({ value: c.id, label: c.nombre })));

const archivo = (campo, e) => { form[campo] = e.target.files[0] ?? null; };

const PASOS_OBLIGATORIOS = [1, 2, 3];

function validarPaso(n) {
    Object.keys(errores).forEach((k) => delete errores[k]);

    if (n === 1) {
        if (!form.sin_documento) {
            const num = (form.numero_documento || '').trim();
            if (!num) errores.numero_documento = 'El número de documento es obligatorio.';
            else if (!reglaDoc.value.re.test(num)) errores.numero_documento = reglaDoc.value.msg;
        }

        const nom = (form.nombres || '').trim();
        if (!nom) errores.nombres = 'Los nombres son obligatorios.';
        else if (!RE_NOMBRE.test(nom)) errores.nombres = 'Los nombres solo admiten letras, espacios y guiones.';

        const pat = (form.apellido_paterno || '').trim();
        if (!pat) errores.apellido_paterno = 'El apellido paterno es obligatorio.';
        else if (!RE_NOMBRE.test(pat)) errores.apellido_paterno = 'El apellido paterno solo admite letras, espacios y guiones.';

        const mat = (form.apellido_materno || '').trim();
        if (mat && !RE_NOMBRE.test(mat)) errores.apellido_materno = 'El apellido materno solo admite letras, espacios y guiones.';

        if (form.fecha_nacimiento) {
            const e = edad(form.fecha_nacimiento);
            if (isNaN(e) || e < EDAD_MINIMA) errores.fecha_nacimiento = `El postulante debe tener al menos ${EDAD_MINIMA} años.`;
            else if (e > 100) errores.fecha_nacimiento = 'Revisa la fecha: la edad supera los 100 años.';
        }

        const em = (form.email || '').trim();
        if (!em) errores.email = 'El correo electrónico es obligatorio.';
        else if (!RE_EMAIL.test(em)) errores.email = 'Escribe un correo electrónico válido.';

        const tel = (form.telefono || '').trim();
        if (tel && !RE_TELEFONO.test(tel)) errores.telefono = 'El teléfono admite de 6 a 20 caracteres entre dígitos, espacios y + ( ) -.';
    }

    if (n === 2) {
        if (!form.institucion_origen_id) errores.institucion_origen_id = 'Selecciona la universidad o instituto de procedencia.';
        if (!form.carrera_externa_id) errores.carrera_externa_id = 'Selecciona la carrera de origen.';
    }

    if (n === 3) {
        if (!form.carrera_destino_ids.length) errores.carrera_destino_ids = 'Selecciona al menos una carrera destino en USIL.';
        const c = (form.ciclo_postulacion || '').trim();
        if (!c) errores.ciclo_postulacion = 'El ciclo de postulación es obligatorio.';
        else if (!/^\d{4}-[12]$/.test(c)) errores.ciclo_postulacion = 'Formato inválido: usa AAAA-1 o AAAA-2 (ej. 2026-1).';
    }

    return Object.keys(errores).length === 0;
}

const irA = (n) => {
    if (editando) {
        paso.value = n;
        return;
    }
    if (n < paso.value) { paso.value = n; return; }
    for (let i = paso.value; i < n; i++) {
        if (PASOS_OBLIGATORIOS.includes(i) && !validarPaso(i)) return;
    }
    paso.value = n;
};

const siguiente = () => { if (validarPaso(paso.value) && paso.value < ULTIMO_PASO) paso.value++; };
const anterior = () => { if (paso.value > 1) paso.value--; };

const enviar = (comoBorrador = false) => {
    if (!comoBorrador) {
        for (const p of PASOS_OBLIGATORIOS) {
            if (!validarPaso(p)) { paso.value = p; return; }
        }
        if (!form.consentimiento_datos) {
            errores.consentimiento_datos = 'El postulante debe autorizar expresamente el tratamiento de sus datos personales.';
            paso.value = 5;
            return;
        }
    }
    form.borrador = comoBorrador;
    if (editando) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(`/postulantes/${props.postulante.id}`);
    } else {
        form.post('/postulantes');
    }
};
</script>

<template>
    <div class="w-full pb-16">
        <VolverA href="/postulantes" texto="Volver al Listado de Postulantes" class="mb-4" />

        <!-- NOTIFICACIÓN EN PANTALLA (FLASH STATUS) -->
        <div v-if="$page.props.flash?.status" class="mb-6 rounded-2xl border border-emerald-300 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 p-4 text-white shadow-lg flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] font-black uppercase tracking-wider text-emerald-200">Acción Realizada con Éxito</p>
                    <p class="text-xs sm:text-sm font-bold text-white leading-snug">{{ $page.props.flash.status }}</p>
                </div>
            </div>
        </div>

        <!-- HERO HEADER BANNER -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#0036DC] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-8 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 mb-2 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                        <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">
                            {{ editando ? `Expediente · ${postulante.codigo}` : 'Admisión · Registro de Traslado Externo' }}
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                        {{ editando ? (esEjecutivo ? 'Revisión y Dictamen de Expediente' : 'Editar Expediente de Postulante') : 'Registrar Nuevo Postulante' }}
                    </h1>
                    <p class="mt-1 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-2xl">
                        {{ editando
                            ? `${nombreCompleto} · Estado: ${postulante.estado}`
                            : 'Completa el asistente de 5 pasos para dar de alta al postulante y cargar su expediente documental.' }}
                    </p>
                </div>

                <div v-if="editando" class="flex flex-wrap items-center gap-2">
                    <span v-if="revision?.estado === 'aprobada'" class="rounded-2xl border border-emerald-300 bg-emerald-500/30 text-white px-4 py-2 text-xs font-extrabold shadow-xs backdrop-blur-md flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        Dictamen: Aprobada
                    </span>
                    <span v-else-if="revision?.estado === 'observada'" class="rounded-2xl border border-rose-300 bg-rose-500/30 text-white px-4 py-2 text-xs font-extrabold shadow-xs backdrop-blur-md flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                        Dictamen: Observada
                    </span>
                    <span v-else-if="revision?.estado === 'pendiente'" class="rounded-2xl border border-amber-300 bg-amber-500/30 text-white px-4 py-2 text-xs font-extrabold shadow-xs backdrop-blur-md flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                        Dictamen: Pendiente
                    </span>

                    <span v-if="revision?.provisional" class="rounded-2xl border border-amber-300 bg-amber-400 text-slate-900 px-3.5 py-1.5 text-xs font-extrabold shadow-xs">
                        Aprobación Provisional
                    </span>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- PANEL DE REVISIÓN DE ADMISIÓN (SOLO EDITANDO)                       -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div v-if="editando && revision" class="mb-8 rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-8 shadow-sm transition-all">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-5">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-base font-extrabold text-[#00205B]">Revisión de Admisión (Ejecutivo Comercial)</h2>
                        <span v-if="revision.estado === 'aprobada'" class="rounded-full bg-emerald-100 border border-emerald-300 text-emerald-800 px-2.5 py-0.5 text-[10px] font-black tracking-wider uppercase">
                            {{ revision.provisional ? 'Aprobada Provisional' : 'Aprobada' }}
                        </span>
                        <span v-else-if="revision.estado === 'observada'" class="rounded-full bg-rose-100 border border-rose-300 text-rose-800 px-2.5 py-0.5 text-[10px] font-black tracking-wider uppercase">
                            Observada
                        </span>
                        <span v-else class="rounded-full bg-amber-100 border border-amber-300 text-amber-800 px-2.5 py-0.5 text-[10px] font-black tracking-wider uppercase">
                            Pendiente
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ revision.revisado_por ? `Última acción por ${revision.revisado_por} el ${revision.revisado_en}` : 'Expediente pendiente de verificación por el equipo de Admisión.' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-600">Documentación esencial:</span>
                    <span :class="revision.documentos >= revision.documentos_total ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                          class="rounded-full border px-3 py-1 text-xs font-extrabold tabular-nums flex items-center gap-1.5 shadow-2xs">
                        <span :class="revision.documentos >= revision.documentos_total ? 'bg-emerald-500' : 'bg-amber-500'" class="h-1.5 w-1.5 rounded-full"></span>
                        {{ revision.documentos }} de {{ revision.documentos_total }} requisitos verificados
                    </span>
                </div>
            </div>

            <!-- 1. CASO APROBADA -->
            <div v-if="revision.estado === 'aprobada'" class="mt-5 space-y-4">
                <div class="rounded-2xl border border-emerald-300 bg-gradient-to-br from-emerald-50/90 via-teal-50/40 to-emerald-50/90 p-5 shadow-xs">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-600/20">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-black text-emerald-950 uppercase tracking-wide">
                                    {{ revision.provisional ? 'Aprobación Provisional Registrada' : 'Expediente Aprobado y Derivado a Coordinación Académica' }}
                                </h3>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-100/90 text-emerald-900 border border-emerald-300 shadow-2xs">
                                    <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                                    Estado: {{ postulante.estado === 'en_evaluacion' ? 'En Evaluación' : postulante.estado }}
                                </span>
                            </div>

                            <p class="mt-1.5 text-xs text-emerald-900 leading-relaxed font-medium">
                                Este expediente fue verificado y <strong>aprobado</strong> por <strong>{{ revision.revisado_por || 'Ejecutivo Comercial' }}</strong> el <strong>{{ revision.revisado_en }}</strong>.
                                Se encuentra listo y habilitado en el módulo de <strong>Simulaciones</strong> para que la Coordinación Académica realice el dictamen de convalidación curricular.
                            </p>

                            <!-- Sustento si es provisional -->
                            <div v-if="revision.provisional && revision.observaciones" class="mt-3 p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-950">
                                <div class="flex items-center gap-1.5 font-black text-amber-900 uppercase tracking-wider text-[11px] mb-1">
                                    <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                    </svg>
                                    <span>Sustento de Aprobación Provisional / Declaración Jurada:</span>
                                </div>
                                <p class="whitespace-pre-wrap font-semibold leading-relaxed">{{ revision.observaciones }}</p>
                            </div>

                            <!-- Barra de navegación rápida -->
                            <div class="mt-3.5 pt-3 border-t border-emerald-200/70 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2 text-xs text-emerald-800 font-semibold">
                                    <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                    <span>Siguiente paso: El área académica de la carrera evaluará las asignaturas en Simulaciones.</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Link href="/postulantes" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-white hover:bg-emerald-50 text-emerald-900 px-3.5 py-1.5 text-xs font-extrabold shadow-2xs transition-colors">
                                        <span>Volver al Listado</span>
                                    </Link>
                                    <Link v-if="puede('evaluacion.ver')" href="/simulaciones" class="inline-flex items-center gap-1.5 rounded-xl bg-[#00205B] hover:bg-[#0036DC] text-white px-3.5 py-1.5 text-xs font-extrabold shadow-2xs transition-colors">
                                        <span>Ir a Simulaciones</span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. CASO OBSERVADA -->
            <div v-else-if="revision.estado === 'observada'" class="mt-5 space-y-4">
                <div class="rounded-2xl border border-rose-300 bg-gradient-to-br from-rose-50/90 via-red-50/40 to-rose-50/90 p-5 shadow-xs">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-600 text-white shadow-md shadow-rose-600/20">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-black text-rose-950 uppercase tracking-wide">
                                    Expediente Observado por Admisión
                                </h3>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-rose-100 text-rose-900 border border-rose-300 shadow-2xs">
                                    Corrección Requerida
                                </span>
                            </div>

                            <p class="mt-1 text-xs text-rose-800 font-medium">
                                Observado por <strong>{{ revision.revisado_por || 'Ejecutivo Comercial' }}</strong> el <strong>{{ revision.revisado_en }}</strong>.
                            </p>

                            <div class="mt-3 p-3.5 rounded-xl bg-white border border-rose-200 text-xs text-rose-950 shadow-2xs">
                                <p class="text-[11px] font-black uppercase tracking-wider text-rose-800 mb-1">Motivo de la Observación:</p>
                                <p class="whitespace-pre-wrap font-semibold leading-relaxed">{{ revision.observaciones || '(Sin detalle especificado)' }}</p>
                            </div>

                            <!-- ACCIÓN DE REENVÍO PARA EL ASESOR -->
                            <div v-if="revision.puede_reenviar" class="mt-4 pt-3 border-t border-rose-200 flex flex-wrap items-center justify-between gap-3">
                                <p class="text-xs text-rose-800 font-medium">
                                    Actualiza los datos o reemplaza los documentos requeridos abajo y luego pulsa reevaluar.
                                </p>
                                <button type="button" @click="abrirModalReenviar" :disabled="revProcesando"
                                        class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] text-white px-5 py-2.5 text-xs font-extrabold shadow-md hover:shadow-lg transition-all cursor-pointer">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                    </svg>
                                    <span>Reenviar con Subsanación</span>
                                </button>
                            </div>

                            <div v-else-if="esEjecutivo" class="mt-3.5 pt-2.5 border-t border-rose-200/80 text-xs text-rose-800 font-medium flex items-center gap-2">
                                <span>⏳ Expediente devuelto al Asesor de Admisión. En cuanto el asesor cargue los documentos corregidos y reenvíe el expediente, volverá a tu bandeja como Pendiente.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. CASO PENDIENTE -->
            <div v-else class="mt-4">
                <!-- Banners contextuales de estado documental -->
                <div v-if="faltantes.length" class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <div>
                            <p class="font-extrabold text-amber-900">Requisitos esenciales pendientes para convalidación</p>
                            <p class="text-amber-800 mt-1 leading-relaxed">
                                Falta adjuntar: <strong class="underline decoration-amber-400 font-bold">{{ faltantes.join(', ') }}</strong>. 
                                Puedes <strong class="text-amber-950">Observar el Expediente</strong> para que el asesor lo subsane, o emitir una <strong class="text-amber-950">Aprobación Provisional</strong> si el postulante presentó declaración jurada.
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 text-xs">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <div>
                            <p class="font-extrabold text-emerald-900">Expediente con documentación completa</p>
                            <p class="text-emerald-800 mt-0.5 leading-relaxed">
                                Se verificó la copia de documento de identidad y el certificado/récord académico oficial. El expediente está listo para ser aprobado y pasar a evaluación del coordinador académico.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INSPECCIÓN Y REEMPLAZO RÁPIDO DE DOCUMENTOS ADJUNTOS -->
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <!-- DNI -->
                <div class="rounded-2xl border p-4 transition-all"
                     :class="docAdjunto('dni') ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40'">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-wider text-slate-800">1. Documento de Identidad</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">{{ form.tipo_documento }} {{ form.numero_documento || '(Sin número)' }}</p>
                        </div>
                        <span :class="docAdjunto('dni') ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                              class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md">
                            {{ docAdjunto('dni') ? 'Adjuntado' : 'Pendiente' }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 pt-2 border-t"
                         :class="docAdjunto('dni') ? 'border-emerald-100' : 'border-amber-100'">
                        <span class="text-xs text-slate-600 truncate max-w-[150px]">
                            {{ docAdjunto('dni') ? docAdjunto('dni').nombre : 'Sin archivo adjunto' }}
                        </span>

                        <div class="flex items-center gap-1.5">
                            <a v-if="docAdjunto('dni')" :href="docAdjunto('dni').url" target="_blank"
                               class="inline-flex items-center gap-1 rounded-xl bg-emerald-600 text-white px-2.5 py-1.5 text-xs font-bold hover:bg-emerald-700 transition-colors shadow-2xs">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <span>Ver</span>
                            </a>

                            <!-- Botón de reemplazo / subida rápida -->
                            <button type="button" @click="dispararSeleccionDoc('dni')" :disabled="subiendoDoc['dni']"
                                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 px-2.5 py-1.5 text-xs font-bold transition-all cursor-pointer shadow-2xs">
                                <svg v-if="subiendoDoc['dni']" class="h-3.5 w-3.5 animate-spin text-[#0036DC]" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                <span>{{ docAdjunto('dni') ? 'Reemplazar' : 'Subir' }}</span>
                            </button>
                            <input type="file" :ref="el => fileInputs['dni'] = el" @change="reemplazarDoc('dni', $event)" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                        </div>
                    </div>
                </div>

                <!-- Certificado / Récord -->
                <div class="rounded-2xl border p-4 transition-all"
                     :class="docAdjunto('certificado') ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40'">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-wider text-slate-800">2. Récord / Certificado</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">Historial oficial de asignaturas y notas</p>
                        </div>
                        <span :class="docAdjunto('certificado') ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                              class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md">
                            {{ docAdjunto('certificado') ? 'Adjuntado' : 'Pendiente' }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 pt-2 border-t"
                         :class="docAdjunto('certificado') ? 'border-emerald-100' : 'border-amber-100'">
                        <span class="text-xs text-slate-600 truncate max-w-[150px]">
                            {{ docAdjunto('certificado') ? docAdjunto('certificado').nombre : 'Sin archivo adjunto' }}
                        </span>

                        <div class="flex items-center gap-1.5">
                            <a v-if="docAdjunto('certificado')" :href="docAdjunto('certificado').url" target="_blank"
                               class="inline-flex items-center gap-1 rounded-xl bg-emerald-600 text-white px-2.5 py-1.5 text-xs font-bold hover:bg-emerald-700 transition-colors shadow-2xs">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <span>Ver</span>
                            </a>

                            <!-- Botón de reemplazo / subida rápida -->
                            <button type="button" @click="dispararSeleccionDoc('certificado')" :disabled="subiendoDoc['certificado']"
                                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 px-2.5 py-1.5 text-xs font-bold transition-all cursor-pointer shadow-2xs">
                                <svg v-if="subiendoDoc['certificado']" class="h-3.5 w-3.5 animate-spin text-[#0036DC]" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                <span>{{ docAdjunto('certificado') ? 'Reemplazar' : 'Subir' }}</span>
                            </button>
                            <input type="file" :ref="el => fileInputs['certificado'] = el" @change="reemplazarDoc('certificado', $event)" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- CAJA DE ACCIONES PARA EL EJECUTIVO COMERCIAL -->
            <div v-if="revision.puede_revisar" class="mt-6 pt-5 border-t border-slate-100">
                <!-- Si ya está aprobada: mostramos botón para desplegar formulario de re-evaluación/observación -->
                <div v-if="revision.estado === 'aprobada'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <button type="button" @click="mostrandoReevaluacion = !mostrandoReevaluacion"
                                class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-slate-900 transition-colors cursor-pointer">
                            <svg class="h-4 w-4 transform transition-transform" :class="mostrandoReevaluacion ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                            <span>¿Deseas modificar el dictamen u observar este expediente?</span>
                        </button>
                    </div>

                    <div v-show="mostrandoReevaluacion" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Indicaciones o Motivo de Modificación / Observación
                            </label>
                            <textarea v-model="obs.observaciones" rows="3"
                                      placeholder="Escribe el motivo del cambio de dictamen o las observaciones que deben subsanarse…"
                                      class="w-full rounded-2xl border border-slate-200 bg-white p-4 text-xs text-slate-800 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/20 focus:outline-hidden transition-all"></textarea>
                            <p v-if="errorRevision" class="mt-1 text-xs font-bold text-rose-600">{{ errorRevision }}</p>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3 pt-1">
                            <button type="button" @click="observar" :disabled="revProcesando || !obs.observaciones.trim()"
                                    class="rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 px-5 py-2.5 text-xs font-bold transition-all disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed">
                                Cambiar a Observado
                            </button>
                            <button type="button" @click="aprobar" :disabled="revProcesando"
                                    class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 text-xs font-bold shadow-md transition-all cursor-pointer">
                                Re-aprobar Expediente
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Si está pendiente o no está aprobada: mostramos el formulario de revisión directo -->
                <div v-else class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                            {{ faltantes.length ? 'Justificación de Aprobación Provisional u Observación' : 'Observaciones de Revisión (Opcional al Aprobar)' }}
                        </label>
                        <textarea v-model="obs.observaciones" rows="3"
                                  :placeholder="faltantes.length ? 'Si vas a aprobar provisionalmente, indica el sustento o declaración jurada. Si vas a observar, detalla qué debe subsanarse…' : 'Opcional: ingresa indicaciones para coordinación o motivos de observación…'"
                                  class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 p-4 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/20 focus:outline-hidden transition-all"></textarea>
                        <p v-if="errorRevision" class="mt-1 text-xs font-bold text-rose-600">{{ errorRevision }}</p>
                    </div>

                    <!-- Botones de Acción de Revisión -->
                    <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                        <button type="button" @click="observar" :disabled="revProcesando || !obs.observaciones.trim()"
                                class="rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 px-5 py-2.5 text-xs font-bold transition-all disabled:opacity-50 cursor-pointer disabled:cursor-not-allowed">
                            Observar Expediente
                        </button>
                        <button type="button" @click="aprobar" :disabled="revProcesando"
                                :class="faltantes.length ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-emerald-600 hover:bg-emerald-700 text-white'"
                                class="inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-xs font-bold shadow-md transition-all cursor-pointer">
                            <svg v-if="revProcesando" class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ faltantes.length ? 'Aprobar con Documentos Pendientes' : 'Aprobar Expediente Completo' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- RESULTADOS DE PRECONVALIDACIÓN (SOLO CONSULTA)                     -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div v-if="preconvalidaciones.length" class="mb-8 rounded-3xl border border-blue-200/80 bg-blue-50/40 p-6 sm:p-8 shadow-xs">
            <div class="flex items-center justify-between border-b border-blue-100 pb-4 mb-4">
                <div>
                    <h2 class="text-base font-extrabold text-[#00205B]">Evaluación Curricular Emitida por Coordinación</h2>
                    <p class="text-xs text-slate-500 mt-0.5">El postulante visualiza estos resultados en su portal.</p>
                </div>
                <span class="rounded-full bg-blue-100 border border-blue-200 px-3.5 py-1 text-xs font-extrabold text-[#00205B]">
                    {{ preconvalidaciones.length }} evaluación(es) realizada(s)
                </span>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="sim in preconvalidaciones" :key="sim.id" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                        <div>
                            <span class="text-xs font-extrabold text-[#00205B]">{{ sim.carrera }}</span>
                            <p class="text-[11px] text-slate-500 mt-0.5">{{ sim.fecha }}</p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <a :href="sim.pdf" target="_blank" class="rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-2.5 py-1 text-[11px] font-bold text-slate-700">PDF</a>
                            <a v-if="sim.excel_oficial" :href="sim.excel_oficial" target="_blank" class="rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800">Excel Oficial</a>
                            <a :href="sim.excel" target="_blank" class="rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-2.5 py-1 text-[11px] font-bold text-slate-700">Excel ERP</a>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between text-xs">
                        <span class="text-slate-600">Convalidados: <strong class="text-slate-800">{{ sim.convalidados }}</strong></span>
                        <span class="text-slate-600">Créditos: <strong class="text-[#0036DC]">{{ sim.creditos }}</strong></span>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100">
                        <button @click="toggleDetalle(sim.id)" class="text-xs font-bold text-[#0036DC] hover:underline cursor-pointer">
                            {{ detalleAbierto[sim.id] ? 'Ocultar asignaturas' : 'Ver asignaturas convalidadas' }}
                        </button>
                        <div v-if="detalleAbierto[sim.id]" class="mt-3 space-y-1.5 max-h-48 overflow-y-auto">
                            <div v-for="(c, ci) in sim.cursos" :key="ci" class="rounded-xl bg-slate-50 p-2.5 text-[11px] flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-slate-800">{{ c.usil }}</p>
                                    <p class="text-slate-500 text-[10px]">{{ c.origen }}</p>
                                </div>
                                <span class="font-bold text-[#0036DC] shrink-0">{{ c.creditos }} cr.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- STEPPER WIZARD DE 5 PASOS                                           -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div class="mb-8 overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <button v-for="p in PASOS" :key="p.n" type="button" @click="irA(p.n)"
                        :class="[
                            paso === p.n ? 'bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] text-white shadow-md' :
                            p.n < paso ? 'bg-blue-50/70 border border-blue-200/80 text-[#00205B]' : 'bg-slate-50 border border-slate-200/60 text-slate-500'
                        ]"
                        class="flex flex-col items-start rounded-2xl p-4 text-left transition-all hover:scale-[1.02] cursor-pointer">
                    <div class="flex items-center justify-between w-full mb-1">
                        <span :class="paso === p.n ? 'bg-white/20 text-white' : (p.n < paso ? 'bg-[#00205B] text-white' : 'bg-slate-200 text-slate-600')"
                              class="h-6 w-6 rounded-full flex items-center justify-center text-xs font-extrabold">
                            <svg v-if="p.n < paso" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <span v-else>{{ p.n }}</span>
                        </span>
                        <span v-if="paso === p.n" class="text-[10px] font-bold uppercase tracking-wider text-[#FFB81C]">Activo</span>
                    </div>
                    <p class="text-xs font-bold leading-tight">{{ p.label }}</p>
                    <p class="text-[10px] opacity-80 mt-0.5 truncate w-full">{{ p.desc }}</p>
                </button>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- FORMULARIO POR PASOS                                                -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div class="rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-10 shadow-sm">
            <form @submit.prevent="enviar(false)" class="space-y-8">
                <!-- ──────────────────────────────────────────────────────────── -->
                <!-- PASO 1: DATOS GENERALES                                      -->
                <!-- ──────────────────────────────────────────────────────────── -->
                <div v-show="paso === 1" class="space-y-8">
                    <!-- Sección 1: Documento -->
                    <div>
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-[#00205B] mb-4 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#0036DC]"></span>
                            <span>1.1 Documento de Identidad</span>
                        </h3>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Tipo de Documento</label>
                                <select v-model="form.tipo_documento" :disabled="form.sin_documento"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15 transition-all">
                                    <option value="DNI">DNI (Documento Nacional de Identidad)</option>
                                    <option value="CE">Carné de Extranjería (CE)</option>
                                    <option value="PASAPORTE">Pasaporte</option>
                                    <option value="PTP">Permiso Temporal de Permanencia (PTP)</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Número de Documento <span v-if="!form.sin_documento" class="text-red-500">*</span>
                                </label>
                                <input v-model="form.numero_documento" type="text" :maxlength="reglaDoc.max" :placeholder="reglaDoc.hint"
                                       :disabled="form.sin_documento"
                                       :class="err('numero_documento') ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15'"
                                       class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all" />
                                <p v-if="err('numero_documento')" class="mt-1 text-xs font-bold text-red-600">{{ err('numero_documento') }}</p>
                            </div>
                        </div>

                        <!-- Checkbox Sin Documento -->
                        <div class="mt-3 flex items-center gap-2">
                            <input v-model="form.sin_documento" type="checkbox" id="sin_doc" class="h-4 w-4 rounded-md text-[#00205B] focus:ring-[#0036DC]" />
                            <label for="sin_doc" class="text-xs text-slate-600 font-medium cursor-pointer">
                                No cuenta con documento oficial temporalmente (se autogenerará un código provisional).
                            </label>
                        </div>
                    </div>

                    <!-- Sección 2: Datos Personales -->
                    <div class="border-t border-slate-100 pt-6">
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-[#00205B] mb-4 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#0036DC]"></span>
                            <span>1.2 Datos Personales</span>
                        </h3>
                        <div class="grid gap-6 sm:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Nombres <span class="text-red-500">*</span></label>
                                <input v-model="form.nombres" type="text" placeholder="Ej. Ana María"
                                       :class="err('nombres') ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15'"
                                       class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all" />
                                <p v-if="err('nombres')" class="mt-1 text-xs font-bold text-red-600">{{ err('nombres') }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Apellido Paterno <span class="text-red-500">*</span></label>
                                <input v-model="form.apellido_paterno" type="text" placeholder="Ej. Pérez"
                                       :class="err('apellido_paterno') ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15'"
                                       class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all" />
                                <p v-if="err('apellido_paterno')" class="mt-1 text-xs font-bold text-red-600">{{ err('apellido_paterno') }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Apellido Materno</label>
                                <input v-model="form.apellido_materno" type="text" placeholder="Ej. García"
                                       :class="err('apellido_materno') ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15'"
                                       class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all" />
                                <p v-if="err('apellido_materno')" class="mt-1 text-xs font-bold text-red-600">{{ err('apellido_materno') }}</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-6 sm:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Fecha de Nacimiento</label>
                                <input v-model="form.fecha_nacimiento" type="date" :max="maxNacimiento"
                                       :class="err('fecha_nacimiento') ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15'"
                                       class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all" />
                                <p v-if="err('fecha_nacimiento')" class="mt-1 text-xs font-bold text-red-600">{{ err('fecha_nacimiento') }}</p>
                                <p v-else-if="form.fecha_nacimiento && !isNaN(edad(form.fecha_nacimiento))" class="mt-1 text-[11px] font-bold text-[#0036DC]">
                                    Edad calculada: {{ edad(form.fecha_nacimiento) }} años
                                </p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Género</label>
                                <select v-model="form.genero"
                                        :class="err('genero') ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15'"
                                        class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all">
                                    <option value="">Selecciona género…</option>
                                    <option v-for="g in GENEROS" :key="g.value" :value="g.value">{{ g.label }}</option>
                                </select>
                                <p v-if="err('genero')" class="mt-1 text-xs font-bold text-red-600">{{ err('genero') }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Nacionalidad</label>
                                <select v-model="form.nacionalidad"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15 transition-all">
                                    <option v-for="n in NACIONALIDADES" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3: Contacto -->
                    <div class="border-t border-slate-100 pt-6">
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-[#00205B] mb-4 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#0036DC]"></span>
                            <span>1.3 Información de Contacto</span>
                        </h3>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Correo Electrónico <span class="text-red-500">*</span></label>
                                <input v-model="form.email" type="email" placeholder="postulante@ejemplo.com"
                                       :class="err('email') ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15'"
                                       class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all" />
                                <p v-if="err('email')" class="mt-1 text-xs font-bold text-red-600">{{ err('email') }}</p>
                                <p class="mt-1 text-[11px] text-slate-500">Se utilizará para remitir las credenciales de acceso al portal.</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Teléfono Móvil</label>
                                <input v-model="form.telefono" type="text" placeholder="Ej. +51 999 888 777"
                                       :class="err('telefono') ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15'"
                                       class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all" />
                                <p v-if="err('telefono')" class="mt-1 text-xs font-bold text-red-600">{{ err('telefono') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────── -->
                <!-- PASO 2: PROCEDENCIA ACADÉMICA                                -->
                <!-- ──────────────────────────────────────────────────────────── -->
                <div v-show="paso === 2" class="space-y-6">
                    <div>
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-[#00205B] mb-2 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#0036DC]"></span>
                            <span>Institución y Carrera de Origen</span>
                        </h3>
                        <p class="text-xs text-slate-500 mb-6">Selecciona la casa de estudios y el programa formativo del cual procede el postulante.</p>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Universidad o Instituto de Origen <span class="text-red-500">*</span>
                                </label>
                                <Autocomplete :model-value="form.institucion_origen_id"
                                              @update:model-value="onInstitucionChange"
                                              :options="institucionesOpts"
                                              placeholder="Escribe para buscar universidad o instituto…" />
                                <p v-if="err('institucion_origen_id')" class="mt-1 text-xs font-bold text-red-600">{{ err('institucion_origen_id') }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Carrera de Origen <span class="text-red-500">*</span>
                                </label>
                                <div v-if="form.institucion_origen_id" class="space-y-1.5">
                                    <Autocomplete v-model="form.carrera_externa_id"
                                                  :options="carrerasOrigenOpts"
                                                  :creatable="true"
                                                  @create="crearCarreraOrigen"
                                                  placeholder="Selecciona o escribe para registrar nueva…" />
                                    <div class="flex items-center justify-between pt-0.5">
                                        <button type="button" @click="abrirModalNuevaCarrera"
                                                class="inline-flex items-center gap-1.5 text-[11px] font-bold text-[#0036DC] hover:text-[#00205B] hover:underline cursor-pointer">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                            <span>¿No encuentras la carrera en la lista? Haz clic aquí para registrarla</span>
                                        </button>
                                        <span v-if="guardandoCarrera" class="text-[11px] font-bold text-amber-600 animate-pulse">
                                            Guardando carrera…
                                        </span>
                                    </div>
                                </div>
                                <div v-else class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-400">
                                    Primero selecciona una institución de origen.
                                </div>
                                <p v-if="err('carrera_externa_id')" class="mt-1 text-xs font-bold text-red-600">{{ err('carrera_externa_id') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────── -->
                <!-- PASO 3: DESTINO EN USIL                                      -->
                <!-- ──────────────────────────────────────────────────────────── -->
                <div v-show="paso === 3" class="space-y-6">
                    <div>
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-[#00205B] mb-2 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#0036DC]"></span>
                            <span>Programa Académico Destino en USIL</span>
                        </h3>
                        <p class="text-xs text-slate-500 mb-6">Indica el ciclo de postulación y una o más carreras de interés del postulante.</p>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Ciclo de Postulación <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.ciclo_postulacion" type="text" placeholder="Ej. 2026-1"
                                       :class="err('ciclo_postulacion') ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15'"
                                       class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all" />
                                <p v-if="err('ciclo_postulacion')" class="mt-1 text-xs font-bold text-red-600">{{ err('ciclo_postulacion') }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Añadir Carrera Destino USIL <span class="text-red-500">*</span>
                                </label>
                                <Autocomplete :model-value="''"
                                              @update:model-value="agregarDestino"
                                              :options="carrerasDestinoDisponibles"
                                              placeholder="Selecciona carrera para añadir…" />
                                <p v-if="err('carrera_destino_ids')" class="mt-1 text-xs font-bold text-red-600">{{ err('carrera_destino_ids') }}</p>
                            </div>
                        </div>

                        <!-- Lista de Carreras Destino Seleccionadas -->
                        <div class="mt-6">
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-700">Carreras seleccionadas ({{ form.carrera_destino_ids.length }}):</label>
                            <div v-if="carrerasDestinoSel.length" class="flex flex-wrap gap-2.5">
                                <div v-for="c in carrerasDestinoSel" :key="c.id"
                                     class="inline-flex items-center gap-2 rounded-2xl bg-blue-50 border border-blue-200 px-4 py-2 text-xs font-bold text-[#00205B]">
                                    <span>{{ c.nombre }}</span>
                                    <button type="button" @click="quitarDestino(c.id)" class="text-blue-400 hover:text-rose-600 transition-colors">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div v-else class="rounded-2xl border border-dashed border-slate-200 p-4 text-center text-xs text-slate-400">
                                Ninguna carrera destino seleccionada aún.
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="mt-6">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Observaciones Generales</label>
                            <textarea v-model="form.observaciones" rows="3" placeholder="Información adicional relevante sobre el traslado…"
                                      class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 p-4 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15 transition-all"></textarea>
                        </div>
                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────── -->
                <!-- PASO 4: EXPEDIENTE DOCUMENTAL                                -->
                <!-- ──────────────────────────────────────────────────────────── -->
                <div v-show="paso === 4" class="space-y-6">
                    <div>
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-[#00205B] mb-2 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#0036DC]"></span>
                            <span>Carga de Expediente Documental Esencial</span>
                        </h3>
                        <p class="text-xs text-slate-500 mb-6">Para emitir la preconvalidación académica, adjunta el documento de identidad y el récord de notas o certificado oficial.</p>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <!-- DNI / Documento -->
                            <div class="rounded-3xl border border-slate-200/80 bg-slate-50/50 p-5 hover:bg-white hover:border-[#0036DC]/50 transition-all">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="h-2 w-2 rounded-full bg-[#0036DC]"></span>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-800">Copia de Documento de Identidad</label>
                                </div>
                                <p class="text-[11px] text-slate-500 mb-4">DNI, CE o Pasaporte legible (PDF o imagen hasta 5MB).</p>
                                <input type="file" @change="archivo('dni', $event)" accept=".pdf,.jpg,.jpeg,.png"
                                       class="block w-full text-xs text-slate-600 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#00205B] hover:file:bg-[#0036DC] file:text-white file:transition-colors cursor-pointer" />
                            </div>

                            <!-- Certificado de Estudios / Récord Académico -->
                            <div class="rounded-3xl border border-slate-200/80 bg-slate-50/50 p-5 hover:bg-white hover:border-[#0036DC]/50 transition-all">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-800">Certificado Oficial o Récord de Notas</label>
                                </div>
                                <p class="text-[11px] text-slate-500 mb-4">Historial de asignaturas, notas y créditos de origen (PDF hasta 5MB).</p>
                                <input type="file" @change="archivo('certificado', $event)" accept=".pdf,.jpg,.jpeg,.png"
                                       class="block w-full text-xs text-slate-600 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#00205B] hover:file:bg-[#0036DC] file:text-white file:transition-colors cursor-pointer" />
                            </div>

                            <!-- Documentos Complementarios / Sílabos (Opcional) -->
                            <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50/30 p-5 sm:col-span-2 hover:bg-white transition-all">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Sílabos o Documentos Complementarios (Opcional)</label>
                                    <span class="text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-500 px-2 py-0.5 rounded-md">Opcional</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mb-4">Solo en caso de asignaturas atípicas no contempladas en el catálogo de equivalencias (PDF o ZIP hasta 10MB).</p>
                                <input type="file" @change="archivo('silabos', $event)" accept=".pdf,.jpg,.jpeg,.png,.zip"
                                       class="block w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border file:border-slate-200 file:text-xs file:font-bold file:bg-white file:text-slate-700 hover:file:bg-slate-50 file:transition-colors cursor-pointer" />
                            </div>
                        </div>

                        <!-- Lista de documentos ya adjuntados en edición -->
                        <div v-if="postulante?.documentos?.length" class="mt-6 pt-6 border-t border-slate-100">
                            <label class="mb-3 block text-xs font-bold uppercase tracking-wider text-slate-700">Archivos adjuntos en el expediente:</label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div v-for="d in postulante.documentos" :key="d.tipo"
                                     class="flex items-center gap-3 rounded-xl bg-emerald-50/60 border border-emerald-200 p-3 text-xs">
                                    <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    <div class="overflow-hidden">
                                        <p class="font-bold text-emerald-900 uppercase text-[10px]">{{ d.tipo }}</p>
                                        <p class="text-slate-600 truncate">{{ d.nombre }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ──────────────────────────────────────────────────────────── -->
                <!-- PASO 5: RESUMEN Y CONSENTIMIENTO                            -->
                <!-- ──────────────────────────────────────────────────────────── -->
                <div v-show="paso === 5" class="space-y-6">
                    <div>
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-[#00205B] mb-2 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#0036DC]"></span>
                            <span>Resumen de la Solicitud y Consentimiento</span>
                        </h3>
                        <p class="text-xs text-slate-500 mb-6">Verifica la información registrada antes de proceder a la creación o actualización.</p>

                        <!-- Resumen en Tarjetas -->
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="rounded-2xl bg-slate-50 p-4 border border-slate-200/60">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Postulante</p>
                                <p class="text-sm font-extrabold text-[#00205B] mt-1">{{ form.nombres }} {{ form.apellido_paterno }} {{ form.apellido_materno }}</p>
                                <p class="text-xs text-slate-600 mt-0.5">{{ form.sin_documento ? 'Documento Provisional' : `${form.tipo_documento}: ${form.numero_documento}` }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ form.email }}</p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4 border border-slate-200/60">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Procedencia</p>
                                <p class="text-sm font-extrabold text-[#00205B] mt-1">{{ institucionNombre }}</p>
                                <p class="text-xs text-slate-600 mt-0.5">{{ carreraOrigenNombre }}</p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4 border border-slate-200/60 sm:col-span-2 lg:col-span-1">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Destino USIL</p>
                                <p class="text-sm font-extrabold text-[#00205B] mt-1">Ciclo {{ form.ciclo_postulacion }}</p>
                                <p class="text-xs text-slate-600 mt-0.5">{{ carreraDestinoNombre }}</p>
                            </div>
                        </div>

                        <!-- Consentimiento Legal (Art. 15) -->
                        <div class="mt-6 rounded-2xl border border-blue-200 bg-blue-50/70 p-5">
                            <div class="flex items-start gap-3">
                                <input v-model="form.consentimiento_datos" type="checkbox" id="consentimiento"
                                       class="mt-1 h-4 w-4 rounded-md text-[#00205B] focus:ring-[#0036DC]" />
                                <label for="consentimiento" class="text-xs text-slate-700 leading-relaxed cursor-pointer">
                                    <strong class="text-[#00205B]">Autorización expresa de tratamiento de datos personales:</strong>
                                    Declaro que el postulante ha sido informado y autoriza el tratamiento de sus datos personales conforme a la Ley N° 29733 y el Art. 15 del Reglamento de Admisión USIL, a efectos de su evaluación académica y convalidación.
                                </label>
                            </div>
                            <p v-if="err('consentimiento_datos')" class="mt-2 text-xs font-bold text-red-600">{{ err('consentimiento_datos') }}</p>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- BARRA INFERIOR DE NAVEGACIÓN Y GUARDADO                         -->
                <!-- ═══════════════════════════════════════════════════════════════ -->
                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-6">
                    <div>
                        <button v-if="paso > 1" type="button" @click="anterior"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-5 py-2.5 text-xs font-bold text-slate-600 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                            <span>Anterior</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Guardar como borrador si es registrador -->
                        <button v-if="esRegistrador && !editando" type="button" @click="enviar(true)" :disabled="form.processing"
                                class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-5 py-2.5 text-xs font-bold text-slate-700 transition-colors">
                            Guardar como Borrador
                        </button>

                        <!-- Botón Siguiente -->
                        <button v-if="paso < ULTIMO_PASO" type="button" @click="siguiente"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] text-white hover:shadow-md px-6 py-2.5 text-xs font-bold transition-all">
                            <span>Siguiente</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>

                        <!-- Botón Final Registrar / Guardar -->
                        <button v-else type="submit" :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] text-white shadow-lg hover:shadow-xl px-7 py-2.5 text-xs font-bold transition-all disabled:opacity-60">
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ editando ? 'Guardar Cambios' : 'Registrar Postulante Definitivo' }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Diálogo modal de confirmación para revisión -->
        <ConfirmDialog :abierto="!!confirmacion"
                       :titulo="confirmacion?.titulo ?? ''"
                       :mensaje="confirmacion?.mensaje ?? ''"
                       :detalle="confirmacion?.detalle ?? null"
                       :texto-confirmar="confirmacion?.textoConfirmar ?? 'Aceptar'"
                       :tono="confirmacion?.tono ?? 'aviso'"
                       :procesando="revProcesando"
                       @confirmar="ejecutarConfirmacion"
                       @cancelar="cancelarConfirmacion" />

        <!-- Modal de Reenvío con Nota de Subsanación (Para el Asesor) -->
        <Teleport to="body">
            <div v-if="modalReenviarAbierto" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="modalReenviarAbierto = false"></div>
                <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 sm:p-8 shadow-2xl border border-slate-100 z-10 animate-in fade-in zoom-in-95 duration-200">
                    <div class="flex items-start gap-4">
                        <div class="h-11 w-11 rounded-2xl bg-blue-100 text-[#00205B] flex items-center justify-center shrink-0 shadow-xs">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="text-base sm:text-lg font-black text-slate-900 leading-snug">Reenviar Expediente a Revisión</h2>
                            <p class="mt-1 text-xs text-slate-600">El expediente volverá a la bandeja del Ejecutivo Comercial como Pendiente para su validación.</p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Nota de Subsanación / Corrección Realizada
                        </label>
                        <textarea v-model="notaSubsanacion" rows="3"
                                  placeholder="Describe qué se corrigió o regularizó (ej. «Se adjuntó nuevo certificado sellado y se corrigió el apellido paterno»)…"
                                  class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 p-3.5 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15 transition-all"></textarea>
                        <p class="mt-1 text-[11px] text-slate-500">Esta nota será leída por el Ejecutivo Comercial al reevaluar el expediente.</p>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="modalReenviarAbierto = false" :disabled="revProcesando"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 cursor-pointer">
                            Cancelar
                        </button>
                        <button type="button" @click="confirmarReenviar" :disabled="revProcesando"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] text-white px-6 py-2.5 text-xs font-extrabold shadow-md hover:shadow-lg transition-all disabled:opacity-60 cursor-pointer">
                            <svg v-if="revProcesando" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ revProcesando ? 'Enviando…' : 'Confirmar y Reenviar' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- MODAL PARA REGISTRAR NUEVA CARRERA DE ORIGEN -->
        <Teleport to="body">
            <div v-if="modalNuevaCarreraVisible" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
                <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity" @click="modalNuevaCarreraVisible = false"></div>
                <div class="relative w-full max-w-md rounded-2xl bg-white p-5 sm:p-6 shadow-xl border border-slate-200 z-10 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0 border border-slate-200/60">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Registrar Carrera de Origen</h2>
                                <p class="text-[11px] text-slate-500">Para {{ institucionNombre }}</p>
                            </div>
                        </div>
                        <button type="button" @click="modalNuevaCarreraVisible = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="guardarNuevaCarreraDesdeModal" class="mt-4 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-slate-700">
                                Nombre de la Carrera <span class="text-red-500">*</span>
                            </label>
                            <input v-model="nuevaCarreraNombre" type="text" placeholder="Ej. Ingeniería Mecatrónica Automotriz"
                                   autofocus required
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15 transition-all" />
                        </div>

                        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button type="button" @click="modalNuevaCarreraVisible = false" :disabled="guardandoNuevaCarrera"
                                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="guardandoNuevaCarrera || !nuevaCarreraNombre.trim()"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-[#00205B] hover:bg-[#0036DC] text-white px-4 py-2 text-xs font-semibold shadow-xs transition-colors disabled:opacity-60 cursor-pointer">
                                <svg v-if="guardandoNuevaCarrera" class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ guardandoNuevaCarrera ? 'Guardando…' : 'Guardar y Seleccionar' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
