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

// Paleta de íconos/colores para dar variedad a la lista (como en el diseño).
const ICONOS = [
    'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0',
    'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25',
    'M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V13.5Zm0 2.25h.008v.008H8.25v-.008Zm2.498-4.5h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V13.5Zm2.504-2.25h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5ZM8.25 6h7.5v2.25h-7.5V6ZM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.653 4.5 4.757V19.5a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.757c0-1.104-.806-2.057-1.907-2.185A48.507 48.507 0 0 0 12 2.25Z',
    'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125',
];
const COLORES = [
    'bg-sky-100 text-sky-600',
    'bg-emerald-100 text-emerald-600',
    'bg-violet-100 text-violet-600',
    'bg-amber-100 text-amber-600',
    'bg-pink-100 text-pink-600',
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
        paginaActual.value = totalPaginas.value; // salta a la página de la nueva carrera
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
    <div class="mx-auto max-w-5xl" @click="cerrarMenu">
        <!-- Encabezado -->
        <VolverA href="/instituciones" texto="Instituciones externas" />
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[#1F3864]">Institución</h1>
            <p class="mt-1 text-sm text-slate-500">Actualiza los datos generales y mantén sus carreras de procedencia.</p>
        </div>

        <div v-if="borradorRestaurado"
             class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <span>Se restauraron cambios sin guardar de una sesión anterior.</span>
            <button type="button" @click="descartarBorrador"
                    class="rounded-md border border-amber-300 px-3 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100">
                Descartar borrador
            </button>
        </div>

        <form @submit.prevent="enviar" class="space-y-6">
            <!-- ============================ DATOS GENERALES ============================ -->
            <section class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-[#1F3864]/10 text-[#1F3864]">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                        </div>
                        <h2 class="text-base font-bold tracking-wide text-[#2E75B6]">DATOS GENERALES</h2>
                    </div>
                    <button type="button" @click="editandoDatos ? cerrarEdicionDatos() : (editandoDatos = true)"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-[#2E75B6]/40 px-3.5 py-2 text-sm font-medium text-[#2E75B6] hover:bg-[#2E75B6]/5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                        </svg>
                        {{ editandoDatos ? 'Listo' : 'Editar información' }}
                    </button>
                </div>

                <div class="grid gap-x-8 gap-y-5 md:grid-cols-2 lg:pr-56">
                    <!-- Tipo -->
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Tipo</label>
                        <template v-if="editandoDatos">
                            <select v-model="form.tipo_id"
                                    class="w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                                <option value="" disabled>Seleccione</option>
                                <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                            </select>
                            <p v-if="erroresDatos.tipo_id || form.errors.tipo_id" class="mt-1 text-xs text-red-600">{{ erroresDatos.tipo_id || form.errors.tipo_id }}</p>
                        </template>
                        <p v-else class="rounded-lg bg-slate-100 px-3 py-2.5 text-sm text-slate-700">{{ nombreTipo }}</p>
                    </div>

                    <!-- País -->
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">País</label>
                        <template v-if="editandoDatos">
                            <input v-model="form.pais" type="text"
                                   class="w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                            <p v-if="erroresDatos.pais || form.errors.pais" class="mt-1 text-xs text-red-600">{{ erroresDatos.pais || form.errors.pais }}</p>
                        </template>
                        <p v-else class="rounded-lg bg-slate-100 px-3 py-2.5 text-sm text-slate-700">{{ form.pais || '—' }}</p>
                    </div>

                    <!-- Gestión -->
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Gestión</label>
                        <template v-if="editandoDatos">
                            <select v-model="form.gestion"
                                    class="w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                                <option value="">Sin especificar</option>
                                <option value="publica">Pública</option>
                                <option value="privada">Privada</option>
                            </select>
                            <p v-if="form.errors.gestion" class="mt-1 text-xs text-red-600">{{ form.errors.gestion }}</p>
                        </template>
                        <p v-else class="rounded-lg bg-slate-100 px-3 py-2.5 text-sm text-slate-700">{{ etiquetaGestion }}</p>
                    </div>

                    <!-- Licenciamiento SUNEDU -->
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Licenciamiento SUNEDU</label>
                        <template v-if="editandoDatos">
                            <select v-model="form.licenciamiento"
                                    class="w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                                <option value="desconocido">Sin verificar</option>
                                <option value="licenciada">Licenciada por SUNEDU</option>
                                <option value="no_licenciada">No licenciada</option>
                            </select>
                            <input v-model="form.licenciamiento_resolucion" maxlength="120"
                                   placeholder="N.º de resolución (opcional)"
                                   class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                            <p v-if="form.errors.licenciamiento" class="mt-1 text-xs text-red-600">{{ form.errors.licenciamiento }}</p>
                        </template>
                        <p v-else class="rounded-lg bg-slate-100 px-3 py-2.5 text-sm text-slate-700">
                            {{ etiquetaLicenciamiento }}
                            <span v-if="form.licenciamiento_resolucion" class="text-slate-500">· {{ form.licenciamiento_resolucion }}</span>
                        </p>
                    </div>

                    <!-- Institución activa -->
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Institución activa</label>
                        <label v-if="editandoDatos" class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                            <input v-model="form.activa" type="checkbox" class="rounded border-slate-300 text-[#2E75B6] focus:ring-[#2E75B6]" />
                            {{ form.activa ? 'Sí' : 'No' }}
                        </label>
                        <p v-else>
                            <span :class="form.activa ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-500'"
                                  class="inline-flex rounded-md px-2.5 py-1 text-sm font-medium">
                                {{ form.activa ? 'Sí' : 'No' }}
                            </span>
                        </p>
                    </div>

                    <!-- Nombre -->
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Nombre de la institución</label>
                        <template v-if="editandoDatos">
                            <input v-model="form.nombre" type="text"
                                   class="w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                            <p v-if="erroresDatos.nombre || form.errors.nombre" class="mt-1 text-xs text-red-600">{{ erroresDatos.nombre || form.errors.nombre }}</p>
                        </template>
                        <p v-else class="rounded-lg bg-slate-100 px-3 py-2.5 text-sm text-slate-700">{{ form.nombre || '—' }}</p>
                    </div>
                </div>

                <!-- Ilustración decorativa -->
                <svg class="pointer-events-none absolute -right-2 bottom-0 hidden h-44 w-52 text-[#2E75B6]/10 lg:block"
                     viewBox="0 0 200 160" fill="currentColor" aria-hidden="true">
                    <polygon points="100,20 180,60 20,60" />
                    <rect x="24" y="60" width="152" height="10" />
                    <rect x="34" y="74" width="14" height="60" />
                    <rect x="66" y="74" width="14" height="60" />
                    <rect x="98" y="74" width="14" height="60" />
                    <rect x="130" y="74" width="14" height="60" />
                    <rect x="152" y="74" width="14" height="60" />
                    <rect x="20" y="138" width="160" height="12" />
                </svg>
            </section>

            <!-- ======================= CARRERAS DE PROCEDENCIA ======================= -->
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-[#2E75B6]/10 text-[#2E75B6]">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold tracking-wide text-[#2E75B6]">CARRERAS DE PROCEDENCIA</h2>
                            <p class="mt-0.5 text-xs text-slate-500">{{ totalCarreras }} carrera(s) registrada(s).</p>
                        </div>
                    </div>
                    <button type="button" @click="abrirNuevaCarrera"
                            class="inline-flex items-center gap-2 rounded-lg bg-[#3B5BDB] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#1F3864]">
                        <span class="text-base leading-none">+</span> Agregar carrera
                    </button>
                </div>

                <p v-if="form.errors.carreras" class="mb-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-600 ring-1 ring-inset ring-red-200">
                    {{ form.errors.carreras }}
                </p>

                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Nombre de la carrera
                    </div>

                    <ul class="divide-y divide-slate-100">
                        <li v-for="c in carrerasPagina" :key="c._idx"
                            class="flex items-center justify-between gap-3 px-5 py-3.5 hover:bg-slate-50/70">
                            <div class="flex min-w-0 items-center gap-3">
                                <span :class="colorCarrera(c._idx)" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path :d="iconoCarrera(c._idx)" />
                                    </svg>
                                </span>
                                <span class="truncate text-sm font-medium text-slate-800">{{ c.nombre }}</span>
                                <span v-if="c.cursos_count > 0"
                                      class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                                    {{ c.cursos_count }} curso(s)
                                </span>
                            </div>

                            <div class="flex shrink-0 items-center gap-1.5">
                                <button type="button" @click="abrirEditarCarrera(c._idx)"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:border-[#2E75B6] hover:text-[#2E75B6]">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                    </svg>
                                    Editar
                                </button>

                                <div class="relative" @click.stop>
                                    <button type="button" @click="alternarMenu(c._idx)"
                                            class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                            aria-label="Más acciones">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="5" r="1.6" /><circle cx="12" cy="12" r="1.6" /><circle cx="12" cy="19" r="1.6" />
                                        </svg>
                                    </button>
                                    <div v-if="menuAbierto === c._idx"
                                         class="absolute right-0 z-10 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                                        <button type="button" @click="abrirEditarCarrera(c._idx)"
                                                class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                                            Editar
                                        </button>
                                        <button type="button" @click="quitarCarrera(c._idx)"
                                                :disabled="c.cursos_count > 0"
                                                :title="c.cursos_count > 0 ? 'No se puede eliminar: tiene cursos registrados' : 'Eliminar carrera'"
                                                class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent">
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li v-if="!totalCarreras" class="px-5 py-12 text-center text-slate-400">
                            Sin carreras registradas. Usa «Agregar carrera» para añadir una.
                        </li>
                    </ul>

                    <!-- Paginación -->
                    <div v-if="totalCarreras" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
                        <div class="flex items-center gap-2 text-sm text-slate-500">
                            <span>Mostrando</span>
                            <select v-model.number="porPagina"
                                    class="rounded-md border-slate-300 py-1 pl-2 pr-7 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                                <option :value="5">5</option>
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                            <span>de {{ totalCarreras }} carreras</span>
                        </div>

                        <nav v-if="totalPaginas > 1" class="flex items-center gap-1">
                            <button type="button" @click="irA(paginaActual - 1)" :disabled="paginaActual === 1"
                                    class="grid h-8 w-8 place-items-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">
                                ‹
                            </button>
                            <button v-for="p in totalPaginas" :key="p" type="button" @click="irA(p)"
                                    :class="p === paginaActual ? 'bg-[#3B5BDB] text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-100'"
                                    class="grid h-8 w-8 place-items-center rounded-md text-sm font-medium">
                                {{ p }}
                            </button>
                            <button type="button" @click="irA(paginaActual + 1)" :disabled="paginaActual === totalPaginas"
                                    class="grid h-8 w-8 place-items-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">
                                ›
                            </button>
                        </nav>
                    </div>
                </div>
            </section>

            <!-- Acciones -->
            <div class="flex gap-3">
                <button type="submit" :disabled="form.processing"
                        class="rounded-lg bg-[#1F3864] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    Guardar cambios
                </button>
                <Link href="/instituciones" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                    Cancelar
                </Link>
            </div>
        </form>
    </div>

    <!-- ============================== MODAL CARRERA ============================== -->
    <div v-if="modalAbierto" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/40" @click="cerrarModal"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-[#1F3864]">
                {{ modalIndice === null ? 'Agregar carrera' : 'Editar carrera' }}
            </h3>
            <p class="mt-1 text-sm text-slate-500">Carrera de procedencia de la institución externa.</p>

            <div class="mt-5">
                <label class="mb-1.5 block text-sm font-semibold text-slate-700">Nombre de la carrera</label>
                <input v-model="modalNombre" type="text" maxlength="200" autofocus
                       @keyup.enter="guardarCarrera" @input="modalError = ''"
                       placeholder="Ej. Administración de Empresas"
                       :class="modalError ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-slate-300 focus:border-[#2E75B6] focus:ring-[#2E75B6]'"
                       class="w-full rounded-lg text-sm" />
                <p v-if="modalError" class="mt-1 text-xs text-red-600">{{ modalError }}</p>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="cerrarModal"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    Cancelar
                </button>
                <button type="button" @click="guardarCarrera"
                        class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-semibold text-white hover:bg-[#2E75B6]">
                    {{ modalIndice === null ? 'Agregar' : 'Guardar' }}
                </button>
            </div>
        </div>
    </div>
</template>
