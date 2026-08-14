<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({ institucion: Object, tipos: Array });

/* ------------------------------------------------------------------ *
 * Estado del formulario
 * ------------------------------------------------------------------ */
// Valores originales (del servidor) para poder descartar el borrador.
const originales = () => ({
    tipo_id: props.institucion.tipo_id,
    nombre: props.institucion.nombre,
    pais: props.institucion.pais ?? '',
    gestion: props.institucion.gestion ?? '',
    licenciamiento: props.institucion.licenciamiento ?? 'desconocido',
    licenciamiento_resolucion: props.institucion.licenciamiento_resolucion ?? '',
    activa: props.institucion.activa,
    carreras: (props.institucion.carreras ?? []).map((c) => ({
        id: c.id,
        nombre: c.nombre,
        cursos_count: c.cursos_count ?? 0,
    })),
});

const form = useForm(originales());

// Borrador local: sobrevive a un refresco de la ventana.
const BORRADOR_KEY = `institucion:editar:${props.institucion.id}`;
const borradorRestaurado = ref(false);

const camposBorrador = () => ({
    tipo_id: form.tipo_id,
    nombre: form.nombre,
    pais: form.pais,
    gestion: form.gestion,
    licenciamiento: form.licenciamiento,
    licenciamiento_resolucion: form.licenciamiento_resolucion,
    activa: form.activa,
    carreras: form.carreras,
});

onMounted(() => {
    const guardado = localStorage.getItem(BORRADOR_KEY);
    if (guardado) {
        try {
            Object.assign(form, JSON.parse(guardado));
            borradorRestaurado.value = true;
        } catch {
            localStorage.removeItem(BORRADOR_KEY);
        }
    }
    watch(camposBorrador, (val) => localStorage.setItem(BORRADOR_KEY, JSON.stringify(val)), { deep: true });
});

const descartarBorrador = () => {
    localStorage.removeItem(BORRADOR_KEY);
    Object.assign(form, originales());
    form.clearErrors();
    erroresDatos.value = {};
    borradorRestaurado.value = false;
    editandoDatos.value = false;
};

/* ------------------------------------------------------------------ *
 * Datos generales (modo lectura / edición)
 * ------------------------------------------------------------------ */
const editandoDatos = ref(false);
const erroresDatos = ref({});

const nombreTipo = computed(() => props.tipos.find((t) => t.id === form.tipo_id)?.nombre ?? '—');
const etiquetaGestion = computed(() =>
    form.gestion === 'publica' ? 'Pública' : form.gestion === 'privada' ? 'Privada' : 'Sin especificar');

// Licenciamiento SUNEDU: condiciona los requisitos del traslado externo.
const LICENCIAMIENTO = {
    licenciada: 'Licenciada por SUNEDU',
    no_licenciada: 'No licenciada',
    desconocido: 'Sin verificar',
};
const etiquetaLicenciamiento = computed(() => LICENCIAMIENTO[form.licenciamiento] ?? LICENCIAMIENTO.desconocido);

const validarDatos = () => {
    const e = {};
    if (!form.tipo_id) e.tipo_id = 'Seleccione el tipo de institución.';
    if (!form.nombre?.trim()) e.nombre = 'El nombre de la institución es obligatorio.';
    else if (form.nombre.trim().length > 200) e.nombre = 'Máximo 200 caracteres.';
    if (form.pais && form.pais.length > 100) e.pais = 'Máximo 100 caracteres.';
    erroresDatos.value = e;
    return Object.keys(e).length === 0;
};

const cerrarEdicionDatos = () => {
    if (validarDatos()) editandoDatos.value = false;
};

/* ------------------------------------------------------------------ *
 * Carreras de procedencia
 * ------------------------------------------------------------------ */
const totalCarreras = computed(() => form.carreras.length);

const ICONOS = [
    'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0',
    'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25',
    'M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V13.5Zm0 2.25h.008v.008H8.25v-.008Zm2.498-4.5h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V13.5Zm2.504-2.25h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5ZM8.25 6h7.5v2.25h-7.5V6ZM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.653 4.5 4.757V19.5a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.757c0-1.104-.806-2.057-1.907-2.185A48.507 48.507 0 0 0 12 2.25Z',
    'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125',
];
const COLORES = [
    'bg-sky-50 text-[#2E75B6] border-sky-100',
    'bg-emerald-50 text-emerald-700 border-emerald-100',
    'bg-violet-50 text-violet-700 border-violet-100',
    'bg-amber-50 text-amber-700 border-amber-100',
    'bg-indigo-50 text-indigo-700 border-indigo-100',
];
const iconoCarrera = (i) => ICONOS[i % ICONOS.length];
const colorCarrera = (i) => COLORES[i % COLORES.length];

// Paginación en cliente.
const porPagina = ref(10);
const paginaActual = ref(1);
const totalPaginas = computed(() => Math.max(1, Math.ceil(totalCarreras.value / porPagina.value)));

watch([porPagina, totalCarreras], () => {
    if (paginaActual.value > totalPaginas.value) paginaActual.value = totalPaginas.value;
});

const desde = computed(() => (totalCarreras.value === 0 ? 0 : (paginaActual.value - 1) * porPagina.value));
const carrerasPagina = computed(() =>
    form.carreras
        .map((c, idx) => ({ ...c, _idx: idx }))
        .slice(desde.value, desde.value + porPagina.value));

const irA = (p) => {
    if (p >= 1 && p <= totalPaginas.value) paginaActual.value = p;
};

// Menú contextual (kebab).
const menuAbierto = ref(null);
const alternarMenu = (idx) => (menuAbierto.value = menuAbierto.value === idx ? null : idx);
const cerrarMenu = () => (menuAbierto.value = null);

/* ------------------------------------------------------------------ *
 * Modal de carrera (agregar / editar) con validación
 * ------------------------------------------------------------------ */
const modalAbierto = ref(false);
const modalIndice = ref(null); // null = nueva carrera
const modalNombre = ref('');
const modalError = ref('');

const nombreCarreraDuplicado = (nombre, ignorar) =>
    form.carreras.some((c, i) => i !== ignorar && c.nombre.trim().toLowerCase() === nombre.trim().toLowerCase());

const validarNombreCarrera = () => {
    const nombre = modalNombre.value.trim();
    if (!nombre) { modalError.value = 'El nombre de la carrera es obligatorio.'; return false; }
    if (nombre.length > 200) { modalError.value = 'Máximo 200 caracteres.'; return false; }
    if (nombreCarreraDuplicado(nombre, modalIndice.value)) {
        modalError.value = 'Ya existe una carrera con ese nombre.';
        return false;
    }
    modalError.value = '';
    return true;
};

const abrirNuevaCarrera = () => {
    modalIndice.value = null;
    modalNombre.value = '';
    modalError.value = '';
    modalAbierto.value = true;
};

const abrirEditarCarrera = (idx) => {
    cerrarMenu();
    modalIndice.value = idx;
    modalNombre.value = form.carreras[idx].nombre;
    modalError.value = '';
    modalAbierto.value = true;
};

const cerrarModal = () => {
    modalAbierto.value = false;
};

const guardarCarrera = () => {
    if (!validarNombreCarrera()) return;
    const nombre = modalNombre.value.trim();
    if (modalIndice.value === null) {
        form.carreras.push({ id: null, nombre, cursos_count: 0 });
        paginaActual.value = totalPaginas.value;
    } else {
        form.carreras[modalIndice.value].nombre = nombre;
    }
    modalAbierto.value = false;
};

const quitarCarrera = (idx) => {
    cerrarMenu();
    const c = form.carreras[idx];
    if (c.cursos_count > 0) {
        alert(`No se puede eliminar "${c.nombre}": tiene ${c.cursos_count} curso(s) registrado(s).`);
        return;
    }
    if (!confirm(`¿Eliminar la carrera "${c.nombre}"?`)) return;
    form.carreras.splice(idx, 1);
};

/* ------------------------------------------------------------------ *
 * Guardar cambios
 * ------------------------------------------------------------------ */
const enviar = () => {
    if (!validarDatos()) {
        editandoDatos.value = true;
        return;
    }
    form
        .transform((d) => ({ ...d, carreras: d.carreras.map(({ id, nombre }) => ({ id, nombre: nombre.trim() })) }))
        .put(`/instituciones/${props.institucion.id}`, {
            preserveScroll: true,
            onSuccess: () => localStorage.removeItem(BORRADOR_KEY),
        });
};
</script>

<template>
    <div class="mx-auto max-w-5xl pb-16" @click="cerrarMenu">
        <VolverA href="/instituciones" texto="Instituciones externas" />

        <!-- HERO HEADER INTEGRADO CON METADATOS -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] p-6 sm:p-10 text-white shadow-xl">
            <!-- Decorative blur background -->
            <div class="absolute -top-24 -right-24 w-80 h-80 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5 mb-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-white/10 border border-white/20 backdrop-blur-md text-blue-100">
                                <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                Ficha Institucional
                            </span>
                            <span v-if="form.activa" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 border border-emerald-400/40 text-emerald-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Activa
                            </span>
                            <span v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/10 text-slate-200">
                                Inactiva
                            </span>
                        </div>

                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            {{ form.nombre || 'Institución' }}
                        </h1>

                        <!-- Metadata compacta -->
                        <div class="mt-2.5 flex flex-wrap items-center gap-2 text-xs text-blue-100/90 font-medium">
                            <span class="bg-white/10 px-2.5 py-1 rounded-lg border border-white/10 font-mono">ID #{{ institucion.id }}</span>
                            <span class="bg-white/10 px-2.5 py-1 rounded-lg border border-white/10">Tipo: <b>{{ nombreTipo }}</b></span>
                            <span v-if="form.pais" class="bg-white/10 px-2.5 py-1 rounded-lg border border-white/10">País: <b>{{ form.pais }}</b></span>
                            <span class="bg-white/10 px-2.5 py-1 rounded-lg border border-white/10">Gestión: <b>{{ etiquetaGestion }}</b></span>
                            <span class="bg-white/10 px-2.5 py-1 rounded-lg border border-white/10"><b>{{ totalCarreras }}</b> carreras registradas</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerta de Borrador Restaurado -->
        <div v-if="borradorRestaurado"
             class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50/90 p-4 text-xs font-medium text-amber-900 shadow-xs backdrop-blur-sm">
            <div class="flex items-center gap-2.5">
                <span class="grid h-6 w-6 place-items-center rounded-full bg-amber-200 text-amber-900 font-bold">!</span>
                <span>Se han restaurado cambios sin guardar de una sesión anterior en este navegador.</span>
            </div>
            <button type="button" @click="descartarBorrador"
                    class="rounded-xl border border-amber-300 bg-white px-3.5 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-100 transition-colors shadow-2xs">
                Descartar borrador
            </button>
        </div>

        <form @submit.prevent="enviar" class="space-y-6">
            <!-- ============================ DATOS GENERALES ============================ -->
            <section class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs relative">
                <div class="mb-6 flex items-center justify-between gap-3 pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-xl bg-blue-50 text-[#1F3864] font-bold text-xs flex items-center justify-center border border-blue-100">
                            1
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Información Institucional y Licenciamiento</h2>
                            <p class="text-xs text-slate-400">Parámetros oficiales y acreditación ante organismos educativos.</p>
                        </div>
                    </div>
                    
                    <button type="button" @click="editandoDatos ? cerrarEdicionDatos() : (editandoDatos = true)"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-[#2E75B6] hover:bg-blue-50 hover:border-[#2E75B6] transition-all shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                        </svg>
                        {{ editandoDatos ? 'Listo' : 'Editar información' }}
                    </button>
                </div>

                <div class="grid gap-x-8 gap-y-5 md:grid-cols-2">
                    <!-- Nombre -->
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Nombre de la institución</label>
                        <template v-if="editandoDatos">
                            <input v-model="form.nombre" type="text"
                                   class="w-full rounded-xl border-slate-300 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                            <p v-if="erroresDatos.nombre || form.errors.nombre" class="mt-1 text-xs text-red-600">{{ erroresDatos.nombre || form.errors.nombre }}</p>
                        </template>
                        <div v-else class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-2.5 text-xs font-bold text-slate-800">
                            {{ form.nombre || '—' }}
                        </div>
                    </div>

                    <!-- Tipo -->
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Tipo de Institución</label>
                        <template v-if="editandoDatos">
                            <select v-model="form.tipo_id"
                                    class="w-full rounded-xl border-slate-300 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                                <option value="" disabled>Seleccione</option>
                                <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                            </select>
                            <p v-if="erroresDatos.tipo_id || form.errors.tipo_id" class="mt-1 text-xs text-red-600">{{ erroresDatos.tipo_id || form.errors.tipo_id }}</p>
                        </template>
                        <div v-else class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-700">
                            {{ nombreTipo }}
                        </div>
                    </div>

                    <!-- País -->
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">País de Origen</label>
                        <template v-if="editandoDatos">
                            <input v-model="form.pais" type="text"
                                   class="w-full rounded-xl border-slate-300 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                            <p v-if="erroresDatos.pais || form.errors.pais" class="mt-1 text-xs text-red-600">{{ erroresDatos.pais || form.errors.pais }}</p>
                        </template>
                        <div v-else class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-700">
                            {{ form.pais || '—' }}
                        </div>
                    </div>

                    <!-- Gestión -->
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Tipo de Gestión</label>
                        <template v-if="editandoDatos">
                            <select v-model="form.gestion"
                                    class="w-full rounded-xl border-slate-300 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                                <option value="">Sin especificar</option>
                                <option value="publica">Pública</option>
                                <option value="privada">Privada</option>
                            </select>
                            <p v-if="form.errors.gestion" class="mt-1 text-xs text-red-600">{{ form.errors.gestion }}</p>
                        </template>
                        <div v-else class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-700">
                            {{ etiquetaGestion }}
                        </div>
                    </div>

                    <!-- Licenciamiento SUNEDU -->
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Licenciamiento SUNEDU</label>
                        <template v-if="editandoDatos">
                            <select v-model="form.licenciamiento"
                                    class="w-full rounded-xl border-slate-300 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                                <option value="desconocido">Sin verificar</option>
                                <option value="licenciada">Licenciada por SUNEDU</option>
                                <option value="no_licenciada">No licenciada</option>
                            </select>
                            <input v-model="form.licenciamiento_resolucion" maxlength="120"
                                   placeholder="N.º de resolución (opcional)"
                                   class="mt-2 w-full rounded-xl border-slate-300 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                            <p v-if="form.errors.licenciamiento" class="mt-1 text-xs text-red-600">{{ form.errors.licenciamiento }}</p>
                        </template>
                        <div v-else class="rounded-xl bg-slate-50 border border-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-700">
                            {{ etiquetaLicenciamiento }}
                            <span v-if="form.licenciamiento_resolucion" class="text-slate-400 font-mono ml-1">· {{ form.licenciamiento_resolucion }}</span>
                        </div>
                    </div>

                    <!-- Institución activa -->
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Estado de Operatividad</label>
                        <label v-if="editandoDatos" class="flex items-center gap-2.5 rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs font-bold text-slate-700 cursor-pointer">
                            <input v-model="form.activa" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-[#2E75B6] focus:ring-[#2E75B6]" />
                            {{ form.activa ? 'Institución Activa (Habilitada para convalidaciones)' : 'Institución Inactiva' }}
                        </label>
                        <div v-else>
                            <span :class="form.activa ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-slate-200'"
                                  class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset">
                                <span :class="form.activa ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'" class="w-1.5 h-1.5 rounded-full"></span>
                                {{ form.activa ? 'Activa' : 'Inactiva' }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ======================= CARRERAS DE PROCEDENCIA ======================= -->
            <section class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-xl bg-blue-50 text-[#1F3864] font-bold text-xs flex items-center justify-center border border-blue-100">
                            2
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Carreras de Procedencia</h2>
                            <p class="text-xs text-slate-400">{{ totalCarreras }} programa(s) registrados en catálogo.</p>
                        </div>
                    </div>
                    <button type="button" @click="abrirNuevaCarrera"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-[#1F3864] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[#2E75B6] transition-all">
                        <span class="text-sm leading-none">+</span> Agregar carrera
                    </button>
                </div>

                <p v-if="form.errors.carreras" class="mb-4 rounded-xl bg-rose-50 border border-rose-200 p-3 text-xs text-rose-700 font-medium">
                    {{ form.errors.carreras }}
                </p>

                <div class="overflow-hidden rounded-2xl border border-slate-200/80">
                    <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Nombre del Programa Académico
                    </div>

                    <ul class="divide-y divide-slate-100">
                        <li v-for="c in carrerasPagina" :key="c._idx"
                            class="flex items-center justify-between gap-3 px-5 py-3.5 hover:bg-slate-50/80 transition-colors group">
                            <div class="flex min-w-0 items-center gap-3">
                                <span :class="colorCarrera(c._idx)" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path :d="iconoCarrera(c._idx)" />
                                    </svg>
                                </span>
                                <span class="truncate text-xs sm:text-sm font-semibold text-slate-800 group-hover:text-[#2E75B6] transition-colors">{{ c.nombre }}</span>
                                <span v-if="c.cursos_count > 0"
                                      class="shrink-0 rounded-full bg-blue-50 text-[#2E75B6] px-2.5 py-0.5 text-[11px] font-bold">
                                    {{ c.cursos_count }} curso(s)
                                </span>
                            </div>

                            <div class="flex shrink-0 items-center gap-1.5">
                                <button type="button" @click="abrirEditarCarrera(c._idx)"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:border-[#2E75B6] hover:text-[#2E75B6] transition-colors shadow-2xs">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                    </svg>
                                    Editar
                                </button>

                                <div class="relative" @click.stop>
                                    <button type="button" @click="alternarMenu(c._idx)"
                                            class="grid h-8 w-8 place-items-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                                            aria-label="Más acciones">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="5" r="1.6" /><circle cx="12" cy="12" r="1.6" /><circle cx="12" cy="19" r="1.6" />
                                        </svg>
                                    </button>
                                    <div v-if="menuAbierto === c._idx"
                                         class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-xl">
                                        <button type="button" @click="abrirEditarCarrera(c._idx)"
                                                class="block w-full px-4 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Editar nombre
                                        </button>
                                        <button type="button" @click="quitarCarrera(c._idx)"
                                                :disabled="c.cursos_count > 0"
                                                :title="c.cursos_count > 0 ? 'No se puede eliminar: tiene cursos registrados' : 'Eliminar carrera'"
                                                class="block w-full px-4 py-2 text-left text-xs font-semibold text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent">
                                            Eliminar carrera
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li v-if="!totalCarreras" class="px-5 py-12 text-center text-xs text-slate-400">
                            Sin carreras registradas. Haz clic en <button type="button" @click="abrirNuevaCarrera" class="font-bold text-[#2E75B6] hover:underline">«Agregar carrera»</button> para añadir una.
                        </li>
                    </ul>

                    <!-- Paginación -->
                    <div v-if="totalCarreras" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 bg-slate-50/50">
                        <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
                            <span>Mostrar</span>
                            <select v-model.number="porPagina"
                                    class="rounded-xl border-slate-200 py-1 pl-2 pr-7 text-xs font-bold text-slate-700 focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                                <option :value="5">5</option>
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                            <span>de {{ totalCarreras }} carreras</span>
                        </div>

                        <nav v-if="totalPaginas > 1" class="flex items-center gap-1">
                            <button type="button" @click="irA(paginaActual - 1)" :disabled="paginaActual === 1"
                                    class="h-8 w-8 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-xs font-bold text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">
                                ‹
                            </button>
                            <button v-for="p in totalPaginas" :key="p" type="button" @click="irA(p)"
                                    :class="p === paginaActual ? 'bg-[#1F3864] text-white shadow-2xs' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-100'"
                                    class="h-8 min-w-[2rem] px-2 rounded-xl text-xs font-bold">
                                {{ p }}
                            </button>
                            <button type="button" @click="irA(paginaActual + 1)" :disabled="paginaActual === totalPaginas"
                                    class="h-8 w-8 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-xs font-bold text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">
                                ›
                            </button>
                        </nav>
                    </div>
                </div>
            </section>

            <!-- Acciones Finales -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <Link href="/instituciones" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                    Cancelar
                </Link>
                <button type="submit" :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-7 py-2.5 rounded-xl bg-gradient-to-r from-[#1F3864] to-[#2E75B6] text-xs font-bold text-white shadow-md hover:shadow-lg transition-all disabled:opacity-60">
                    <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>

    <!-- ============================== MODAL CARRERA ============================== -->
    <div v-if="modalAbierto" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity" @click="cerrarModal"></div>
        <div class="relative w-full max-w-md rounded-3xl bg-white p-6 sm:p-8 shadow-2xl">
            <h3 class="text-base font-bold text-slate-900">
                {{ modalIndice === null ? 'Nueva Carrera Externa' : 'Editar Carrera Externa' }}
            </h3>
            <p class="mt-1 text-xs text-slate-400">Programa académico de procedencia para convalidaciones.</p>

            <div class="mt-5">
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Nombre de la carrera</label>
                <input v-model="modalNombre" type="text" maxlength="200" autofocus
                       @keyup.enter="guardarCarrera" @input="modalError = ''"
                       placeholder="Ej. Administración de Empresas"
                       :class="modalError ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-slate-300 focus:border-[#2E75B6] focus:ring-[#2E75B6]'"
                       class="w-full rounded-xl text-xs font-medium text-slate-800" />
                <p v-if="modalError" class="mt-1.5 text-xs text-red-600 font-medium">{{ modalError }}</p>
            </div>

            <div class="mt-6 flex justify-end gap-2.5">
                <button type="button" @click="cerrarModal"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    Cancelar
                </button>
                <button type="button" @click="guardarCarrera"
                        class="rounded-xl bg-[#1F3864] px-5 py-2 text-xs font-bold text-white hover:bg-[#2E75B6] transition-all shadow-xs">
                    {{ modalIndice === null ? 'Agregar carrera' : 'Guardar cambios' }}
                </button>
            </div>
        </div>
    </div>
</template>

