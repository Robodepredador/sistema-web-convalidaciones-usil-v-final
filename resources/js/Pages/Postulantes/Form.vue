<script setup>
import { useForm, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import VolverA from '../../Components/VolverA.vue';

const props = defineProps({
    postulante: Object, instituciones: Array, carreras: Array, estados: Array,
    preconvalidaciones: { type: Array, default: () => [] }, preconvalidacion_estado: String,
    revision: { type: Object, default: null },
    // { clave: etiqueta } — llega del servidor para no duplicar aquí la lista
    // de documentos que se valida y se exige al aprobar.
    documentos_tipos: { type: Object, default: () => ({}) },
});
const editando = !!props.postulante;

// RBAC: distingue el flujo del Asesor (registro) del Ejecutivo (revisión).
const permisos = computed(() => usePage().props.auth?.user?.permisos ?? []);
const puede = (clave) => permisos.value.includes('*') || permisos.value.includes(clave);
const esRegistrador = computed(() => puede('solicitudes.crear'));
const esRevisor = computed(() => puede('solicitudes.validar') && !esRegistrador.value);

// Revisión de admisión (aprobar / observar / reenviar).
const obs = reactive({ observaciones: '' });
const revProcesando = ref(false);
const errorRevision = ref('');
const REV = {
    pendiente: { label: 'Pendiente de revisión', clase: 'border-amber-200 bg-amber-50 text-amber-800' },
    aprobada:  { label: 'Aprobada',              clase: 'border-green-200 bg-green-50 text-green-800' },
    observada: { label: 'Observada',             clase: 'border-orange-200 bg-orange-50 text-orange-800' },
};
// Las decisiones de revisión no se deshacen desde la interfaz, así que se confirman
// antes de ejecutarse y mostrando sobre qué expediente se está actuando.
const confirmacion = ref(null);

const postRevision = (url, payload) => router.post(url, payload, {
    preserveScroll: true,
    onStart: () => { revProcesando.value = true; },
    // El diálogo se cierra al terminar, no al pulsar: mientras tanto muestra el avance.
    onFinish: () => { revProcesando.value = false; confirmacion.value = null; },
});

const cancelarConfirmacion = () => { confirmacion.value = null; };
const ejecutarConfirmacion = () => confirmacion.value?.accion?.();

const nombreCompleto = computed(() => {
    const apellidos = [props.postulante?.apellido_paterno, props.postulante?.apellido_materno].filter(Boolean).join(' ');
    return [apellidos, props.postulante?.nombres].filter(Boolean).join(', ') || '—';
});

// Art. 24 del Reglamento de Admisión: apto solo con todos los documentos. Si
// falta alguno, la aprobación tiene que declararse provisional y justificarse.
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
    if (!obs.observaciones.trim()) return;
    confirmacion.value = {
        titulo: '¿Observar este expediente?',
        mensaje: 'El asesor y el postulante leerán la observación. El expediente no avanzará hasta que se corrija y se reenvíe.',
        textoConfirmar: 'Sí, observar',
        tono: 'aviso',
        detalle: obs.observaciones.trim(),
        accion: () => postRevision(`/postulantes/${props.postulante.id}/revisar`, { accion: 'observar', observaciones: obs.observaciones }),
    };
};
const reenviar = () => {
    confirmacion.value = {
        titulo: '¿Reenviar a revisión?',
        mensaje: 'El expediente vuelve a la cola del Ejecutivo Comercial como pendiente y se borra la observación actual.',
        textoConfirmar: 'Sí, reenviar',
        accion: () => postRevision(`/postulantes/${props.postulante.id}/reenviar-revision`, {}),
    };
};

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

// Formato del número de documento por tipo. Espejo de PostulanteController::REGLAS_DOCUMENTO.
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

// Edad cumplida a hoy. Se parsea el ISO a mano: new Date('2000-05-04') es UTC y en Lima
// (UTC-5) retrocedería un día, corriendo el cumpleaños.
const edad = (iso) => {
    const [y, m, d] = String(iso).split('-').map(Number);
    if (!y || !m || !d) return NaN;
    const hoy = new Date();
    const cumplido = hoy.getMonth() + 1 > m || (hoy.getMonth() + 1 === m && hoy.getDate() >= d);
    return hoy.getFullYear() - y - (cumplido ? 0 : 1);
};
// Tope nativo del selector de fecha (el chequeo de edad igual corre: se puede teclear).
const maxNacimiento = (() => {
    const h = new Date();
    return `${h.getFullYear() - EDAD_MINIMA}-${String(h.getMonth() + 1).padStart(2, '0')}-${String(h.getDate()).padStart(2, '0')}`;
})();

// Ciclo vigente por calendario: enero–junio = 1, julio–diciembre = 2.
// Es solo el valor por defecto; el asesor puede escribir otro.
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

// Iconos de las subdivisiones del paso 1 (identificación, persona, contacto).
const SUB = {
    identificacion: 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z',
    persona: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
    contacto: 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z',
};

const PASOS = [
    { n: 1, label: 'Datos generales', icon: SUB.persona },
    { n: 2, label: 'Procedencia académica', icon: 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z' },
    { n: 3, label: 'Destino en USIL', icon: 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342' },
    { n: 4, label: 'Documentos', icon: 'M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776' },
    { n: 5, label: 'Confirmación', icon: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' },
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

// Pasos que exigen campos completos antes de continuar (4 = documentos y 5 = confirmación son opcionales).
const PASOS_OBLIGATORIOS = [1, 2, 3];

function validarPaso(n) {
    Object.keys(errores).forEach((k) => delete errores[k]);
    if (n === 1) {
        if (!form.sin_documento) {
            const num = form.numero_documento.trim();
            if (!num) errores.numero_documento = 'Ingresa el número de documento.';
            else if (!reglaDoc.value.re.test(num)) errores.numero_documento = reglaDoc.value.msg;
        }
        for (const [campo, etiqueta, obligatorio] of [
            ['nombres', 'los nombres', true],
            ['apellido_paterno', 'el apellido paterno', true],
            ['apellido_materno', 'el apellido materno', false],
        ]) {
            const v = form[campo].trim();
            if (!v) { if (obligatorio) errores[campo] = `Ingresa ${etiqueta}.`; continue; }
            if (v.length < 2) errores[campo] = `Ingresa ${etiqueta} completo (mínimo 2 letras).`;
            else if (!RE_NOMBRE.test(v)) errores[campo] = 'Solo se admiten letras, espacios, apóstrofos y guiones.';
        }
        if (form.nacionalidad.trim() && !RE_NOMBRE.test(form.nacionalidad.trim())) {
            errores.nacionalidad = 'La nacionalidad solo admite letras y espacios.';
        }
        if (form.fecha_nacimiento) {
            const a = edad(form.fecha_nacimiento);
            if (!(a >= EDAD_MINIMA)) errores.fecha_nacimiento = `El postulante debe tener al menos ${EDAD_MINIMA} años.`;
            else if (a > 100) errores.fecha_nacimiento = 'Revisa la fecha: la edad no es plausible.';
        }
        // Contacto (subdivisión del mismo paso).
        if (!form.email.trim()) errores.email = 'El correo es obligatorio.';
        else if (!RE_EMAIL.test(form.email.trim())) errores.email = 'Correo no válido.';
        if (!form.telefono.trim()) errores.telefono = 'Ingresa el teléfono.';
        else if (!RE_TELEFONO.test(form.telefono.trim())) errores.telefono = 'Teléfono no válido (6 a 20 caracteres: dígitos, espacios y + ( ) -).';
    }
    if (n === 2) {
        if (!form.institucion_origen_id) errores.institucion_origen_id = 'Selecciona la institución de origen.';
        if (!form.carrera_externa_id) errores.carrera_externa_id = 'Selecciona la carrera de origen.';
    }
    if (n === 3) {
        if (!form.carrera_destino_ids.length) errores.carrera_destino_ids = 'Selecciona al menos una carrera destino.';
        if (!form.ciclo_postulacion.trim()) errores.ciclo_postulacion = 'Ingresa el ciclo (AAAA-N).';
        else if (!/^20\d{2}-[12]$/.test(form.ciclo_postulacion.trim())) errores.ciclo_postulacion = 'Formato AAAA-N con N igual a 1 o 2 (ej. 2026-1).';
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

const siguiente = () => { if (validarPaso(paso.value)) paso.value = Math.min(ULTIMO_PASO, paso.value + 1); };
const anterior = () => { paso.value = Math.max(1, paso.value - 1); };

const enviar = (borrador) => {
    form.borrador = borrador;
    // En registro completo, valida todos los pasos obligatorios antes de enviar.
    if (!borrador) {
        const invalido = primerPasoInvalido(ULTIMO_PASO + 1);
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
        <!-- Confirmación de las decisiones de revisión -->
        <ConfirmDialog :open="!!confirmacion"
                       :titulo="confirmacion?.titulo ?? ''"
                       :mensaje="confirmacion?.mensaje ?? ''"
                       :texto-confirmar="confirmacion?.textoConfirmar ?? 'Confirmar'"
                       :tono="confirmacion?.tono ?? 'primario'"
                       :procesando="revProcesando"
                       @confirmar="ejecutarConfirmacion" @cancelar="cancelarConfirmacion">
            <p v-if="confirmacion?.detalle" class="whitespace-pre-line text-slate-600">
                <span class="font-medium text-slate-700">Observación:</span> {{ confirmacion.detalle }}
            </p>
            <dl v-else class="space-y-1">
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Expediente</dt><dd class="font-medium text-slate-700">{{ postulante?.codigo }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Postulante</dt><dd class="font-medium text-slate-700">{{ nombreCompleto }}</dd></div>
                <div v-if="revision?.documentos_total" class="flex justify-between gap-4">
                    <dt class="text-slate-400">Documentos</dt>
                    <dd class="font-medium" :class="revision.documentos < revision.documentos_total ? 'text-amber-700' : 'text-slate-700'">
                        {{ revision.documentos }}/{{ revision.documentos_total }}
                    </dd>
                </div>
            </dl>
        </ConfirmDialog>

        <!-- Encabezado -->
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <VolverA href="/postulantes" texto="Postulantes" />
                <h1 class="text-2xl font-semibold text-[#1F3864]">
                    {{ !editando ? 'Nuevo postulante' : (esRevisor ? 'Revisar expediente' : 'Editar postulante') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ esRevisor ? 'Verifica que los datos y documentos estén completos, luego aprueba u observa.' : 'Registra la información del postulante para su proceso de convalidación.' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Link href="/postulantes" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancelar y salir</Link>
                <button v-if="esRegistrador" @click="enviar(true)" :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864] disabled:opacity-60">
                    Guardar borrador
                </button>
            </div>
        </div>

        <!-- Revisión de admisión: aprobar/observar (Ejecutivo) o reenviar (Asesor) -->
        <div v-if="editando && revision" class="mb-5 rounded-2xl border p-4 shadow-sm" :class="REV[revision.estado]?.clase">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Revisión de admisión: {{ REV[revision.estado]?.label ?? revision.estado }}</h2>
                <div class="flex items-center gap-3 text-xs opacity-80">
                    <span v-if="revision.documentos_total">Documentos: {{ revision.documentos }}/{{ revision.documentos_total }}</span>
                    <span v-if="revision.revisado_por">{{ revision.revisado_por }} · {{ revision.revisado_en }}</span>
                </div>
            </div>

            <!-- Observación visible para el asesor -->
            <p v-if="revision.estado === 'aprobada' && revision.provisional" class="mt-2 text-sm">
                <span class="font-medium">Aprobación provisional:</span> {{ revision.observaciones || 'documentación pendiente de regularizar.' }}
            </p>
            <p v-if="revision.estado === 'observada' && revision.observaciones" class="mt-2 text-sm">
                <span class="font-medium">Observación:</span> {{ revision.observaciones }}
            </p>

            <!-- Ya convalidado: la revisión queda cerrada -->
            <p v-if="revision.convalidada" class="mt-2 text-sm">
                El expediente ya tiene una convalidación confirmada; la revisión de admisión está cerrada.
            </p>

            <!-- Borrador: incompleto, todavía no hay nada que aprobar -->
            <p v-else-if="revision.es_borrador" class="mt-2 text-sm">
                El expediente aún es un borrador. El asesor debe completarlo y guardarlo como registro definitivo antes de que pueda revisarse.
            </p>

            <!-- Ejecutivo Comercial: aprobar u observar -->
            <div v-if="revision.puede_revisar" class="mt-3 space-y-2">
                <div v-if="faltantes.length" class="rounded-lg border border-amber-300 bg-amber-50/70 px-3 py-2 text-xs text-amber-900">
                    <p class="font-medium">Expediente incompleto — falta:</p>
                    <ul class="mt-1 list-inside list-disc">
                        <li v-for="f in faltantes" :key="f">{{ f }}</li>
                    </ul>
                    <p class="mt-1">Aprobar ahora lo hará de forma <strong>provisional</strong>, con la regularización pendiente.</p>
                </div>
                <textarea v-model="obs.observaciones" rows="2"
                          :placeholder="faltantes.length
                              ? 'Detalle de la observación, o justificación de la aprobación provisional (obligatorio en ambos casos)'
                              : 'Detalle de la observación (obligatorio para observar)'"
                          class="w-full rounded-md border-slate-300 text-sm"></textarea>
                <p v-if="errorRevision" class="text-xs text-red-600">{{ errorRevision }}</p>
                <div class="flex gap-2">
                    <button type="button" @click="aprobar" :disabled="revProcesando"
                            class="rounded-md px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            :class="faltantes.length ? 'bg-amber-600 hover:bg-amber-700' : 'bg-green-700 hover:bg-green-800'">
                        {{ faltantes.length ? 'Aprobar provisionalmente' : 'Aprobar' }}
                    </button>
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
        <div class="mb-5 grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-3 lg:grid-cols-5">
            <button v-for="p in PASOS" :key="p.n" @click="irPaso(p.n)"
                    :class="paso === p.n ? 'bg-[#2E75B6]/10 text-[#1F3864]' : 'text-slate-500 hover:bg-slate-50'"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium">
                <svg class="h-5 w-5 shrink-0" :class="paso === p.n ? 'text-[#2E75B6]' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path :d="p.icon" /></svg>
                <span class="truncate">{{ p.n }}. {{ p.label }}</span>
            </button>
        </div>

        <!-- Contenido del paso -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <!-- Paso 1: Datos generales (identificación + datos personales + contacto) -->
            <div v-show="paso === 1">
                <h2 class="text-lg font-semibold text-[#1F3864]">Datos generales</h2>
                <p class="mb-6 text-sm text-slate-500">Identificación, datos personales y contacto del postulante.</p>

                <h3 class="mb-3 flex items-center gap-2 border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                    <svg class="h-4 w-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path :d="SUB.identificacion" /></svg>
                    Identificación
                </h3>
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
                        <input v-model="form.numero_documento" :disabled="form.sin_documento" type="text"
                               :maxlength="reglaDoc.max" :inputmode="reglaDoc.numerico ? 'numeric' : 'text'"
                               placeholder="Ingresa el número de documento" :class="inputCls" />
                        <p v-if="err('numero_documento')" class="mt-1 text-xs text-red-600">{{ err('numero_documento') }}</p>
                        <p v-else-if="!form.sin_documento" class="mt-1 text-xs text-slate-400">{{ reglaDoc.hint }}</p>
                    </div>
                    <label class="flex items-start gap-2 pt-7 text-sm text-slate-600">
                        <input v-model="form.sin_documento" type="checkbox" class="mt-0.5 rounded border-slate-300 text-[#2E75B6]" />
                        El postulante no presenta documento de identidad
                    </label>
                </div>

                <div v-if="form.sin_documento" class="mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    <span>ℹ️</span> Si el postulante no cuenta con documento, se generará un identificador temporal único.
                </div>

                <h3 class="mb-3 mt-7 flex items-center gap-2 border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                    <svg class="h-4 w-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path :d="SUB.persona" /></svg>
                    Datos personales
                </h3>
                <div class="grid gap-4 sm:grid-cols-3">
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
                        <input v-model="form.fecha_nacimiento" type="date" :max="maxNacimiento" :class="inputCls" />
                        <p v-if="err('fecha_nacimiento')" class="mt-1 text-xs text-red-600">{{ err('fecha_nacimiento') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nacionalidad <span class="text-slate-400">(opcional)</span></label>
                        <Autocomplete v-model="form.nacionalidad" :options="NACIONALIDADES" :allow-free="true" placeholder="Escribe la nacionalidad…" />
                        <p v-if="err('nacionalidad')" class="mt-1 text-xs text-red-600">{{ err('nacionalidad') }}</p>
                    </div>
                </div>

                <h3 class="mb-3 mt-7 flex items-center gap-2 border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                    <svg class="h-4 w-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path :d="SUB.contacto" /></svg>
                    Contacto
                </h3>
                <p class="mb-4 text-xs text-slate-400">El correo se usará para su acceso al portal del postulante.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Correo electrónico</label>
                        <input v-model="form.email" type="email" placeholder="correo@example.com" :class="inputCls" />
                        <p v-if="err('email')" class="mt-1 text-xs text-red-600">{{ err('email') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Teléfono</label>
                        <input v-model="form.telefono" type="tel" inputmode="tel" maxlength="20" placeholder="+51 999 888 777" :class="inputCls" />
                        <p v-if="err('telefono')" class="mt-1 text-xs text-red-600">{{ err('telefono') }}</p>
                    </div>
                </div>
            </div>

            <!-- Paso 2: Procedencia académica -->
            <div v-show="paso === 2">
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

            <!-- Paso 3: Destino en USIL -->
            <div v-show="paso === 3">
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
                        <input v-model="form.ciclo_postulacion" type="text" maxlength="6" :placeholder="cicloActual" :class="inputCls" />
                        <p v-if="err('ciclo_postulacion')" class="mt-1 text-xs text-red-600">{{ err('ciclo_postulacion') }}</p>
                        <p v-else class="mt-1 text-xs text-slate-400">Ciclo vigente ({{ cicloActual }}) según la fecha. Puedes cambiarlo.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                        <textarea v-model="form.observaciones" rows="2" :class="inputCls"></textarea>
                    </div>
                </div>
            </div>

            <!-- Paso 4: Documentos -->
            <div v-show="paso === 4">
                <h2 class="text-lg font-semibold text-[#1F3864]">Documentos</h2>
                <p class="mb-5 text-sm text-slate-500">
                    Expediente de traslado externo. Se puede guardar sin completarlo, pero
                    <strong>Admisión no puede aprobarlo con documentos faltantes</strong> salvo de forma provisional,
                    con declaración jurada. PDF o imagen, máx. 5–10 MB.
                </p>
                <div class="space-y-3">
                    <div v-for="(etiqueta, clave) in documentos_tipos" :key="clave"
                         class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-3">
                        <div>
                            <p class="text-sm font-medium text-slate-700">{{ etiqueta }}</p>
                            <p class="text-xs text-slate-400">{{ form[clave]?.name || 'Ningún archivo seleccionado' }}</p>
                            <p v-if="err(clave)" class="mt-1 text-xs text-red-600">{{ err(clave) }}</p>
                        </div>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.zip" @change="(e) => archivo(clave, e)" class="text-sm text-slate-600" />
                    </div>
                    <p v-if="editando && postulante.documentos?.length" class="text-xs text-slate-400">
                        Documentos ya cargados: {{ postulante.documentos.map(d => d.nombre).join(', ') }}
                    </p>
                </div>
            </div>

            <!-- Paso 5: Confirmación -->
            <div v-show="paso === 5">
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
                <!-- Art. 15 del Reglamento de Admisión / Ley 29733: consentimiento
                     expreso e inequívoco, incluidos los datos de carácter sensible. -->
                <div class="mt-4 rounded-lg border px-4 py-3"
                     :class="err('consentimiento_datos') ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-white'">
                    <label class="flex cursor-pointer items-start gap-3 text-sm">
                        <input type="checkbox" v-model="form.consentimiento_datos"
                               class="mt-0.5 rounded border-slate-300 text-[#2E75B6]" />
                        <span class="text-slate-700">
                            El postulante declara conocer el proceso de admisión y <strong>autoriza expresamente</strong>
                            el tratamiento de sus datos personales —incluidos los de carácter sensible— para los fines
                            del proceso, entre ellos la lectura automatizada de su récord académico.
                            <span class="mt-1 block text-xs text-slate-500">
                                Art. 15 del Reglamento de Admisión USIL · Ley N.º 29733.
                                Sin esta autorización no se puede usar la extracción automática de cursos.
                            </span>
                        </span>
                    </label>
                    <p v-if="err('consentimiento_datos')" class="mt-1 text-xs text-red-600">{{ err('consentimiento_datos') }}</p>
                    <p v-else-if="postulante?.consentimiento_datos_en" class="mt-1 text-xs text-slate-400">
                        Otorgado el {{ postulante.consentimiento_datos_en }}.
                    </p>
                </div>

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
                    <span class="ml-2 text-xs text-slate-400">Paso {{ paso }} de {{ ULTIMO_PASO }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="paso > 1" @click="anterior" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Anterior</button>
                    <button v-if="paso < ULTIMO_PASO" @click="siguiente" class="inline-flex items-center gap-1 rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6]">
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
