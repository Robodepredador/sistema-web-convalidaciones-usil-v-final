<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    malla: Object,
    ciclos: Array,
    resumen: Object,
    cursosMalla: Array,
    // { institucionales: [...], propias: [...] } — materias de ORIGEN que no se convalidan.
    noConvalidables: { type: Object, default: () => ({ institucionales: [], propias: [] }) },
});

// ----- Panel (drawer): 'view' | 'edit' | 'new' | null -----
const panel = ref(null);
const cursoSel = ref(null);
const cicloDestino = ref(null);
const tab = ref('info');

const form = useForm({
    codigo: '', nombre: '', creditos: '', horas_teoria: '', horas_practica: '',
    es_electivo: false, convalidable: true, mencion: '', prerequisito_id: '', silabo_texto: '',
    tipo_curso: '', area: '', competencias: '', resultados_aprendizaje: '',
});

// ----- Materias de origen que esta carrera no convalida -----
// Son reglas de la carrera: no tocan la política institucional ni a las demás
// carreras. Una regla propia con la misma materia manda sobre la institucional,
// y desactivada sirve para levantarla (aquí sí se convalida).
const reglaNueva = reactive({ palabra_clave: '', motivo: '', activo: true });
const reglaError = ref('');
const verReglas = ref(false);

const propiaDe = (clave) => props.noConvalidables.propias.find(
    (r) => r.palabra_clave.toLowerCase() === clave.toLowerCase());

const guardarRegla = () => {
    if (!reglaNueva.palabra_clave.trim()) {
        reglaError.value = 'Escribe la materia (por ejemplo: Física).';

        return;
    }
    reglaError.value = '';
    router.post(`/mallas/${props.malla.id}/no-convalidables`, { ...reglaNueva }, {
        preserveScroll: true,
        onSuccess: () => { reglaNueva.palabra_clave = ''; reglaNueva.motivo = ''; reglaNueva.activo = true; },
    });
};

const alternarRegla = (r) => router.patch(
    `/mallas/${props.malla.id}/no-convalidables/${r.id}`, { activo: !r.activo }, { preserveScroll: true });

const quitarRegla = (r) => {
    if (!confirm(`¿Quitar la regla "${r.palabra_clave}"? La carrera volverá a seguir la política institucional.`)) return;
    router.delete(`/mallas/${props.malla.id}/no-convalidables/${r.id}`, { preserveScroll: true });
};

// Levantar una institucional = crear la misma materia como regla propia inactiva.
const levantarInstitucional = (r) => router.post(`/mallas/${props.malla.id}/no-convalidables`,
    { palabra_clave: r.palabra_clave, motivo: `Excepción de ${props.malla.carrera}`, activo: false },
    { preserveScroll: true });

const TIPO = { teorico: 'Teórico', practico: 'Práctico', teorico_practico: 'Teórico - Práctico' };
const tabs = [
    { id: 'info', label: 'Información' },
    { id: 'competencias', label: 'Competencias' },
    { id: 'convalidaciones', label: 'Convalidaciones' },
    { id: 'historial', label: 'Historial' },
];

const verCurso = (curso) => { cursoSel.value = curso; tab.value = 'info'; panel.value = 'view'; };

const nuevoCurso = (ciclo) => {
    cicloDestino.value = ciclo;
    cursoSel.value = null;
    form.reset();
    form.clearErrors();
    panel.value = 'new';
};

const editarCurso = () => {
    const c = cursoSel.value;
    form.clearErrors();
    Object.assign(form, {
        codigo: c.codigo, nombre: c.nombre, creditos: c.creditos,
        horas_teoria: c.horas_teoria ?? '', horas_practica: c.horas_practica ?? '',
        es_electivo: c.es_electivo, convalidable: c.convalidable ?? true, mencion: c.mencion ?? '', prerequisito_id: c.prerequisito_id ?? '', silabo_texto: c.silabo_texto ?? '',
        tipo_curso: c.tipo_curso ?? '', area: c.area ?? '',
        competencias: (c.competencias ?? []).join(', '), resultados_aprendizaje: c.resultados_aprendizaje ?? '',
    });
    panel.value = 'edit';
};

const cerrar = () => { panel.value = null; cursoSel.value = null; cicloDestino.value = null; };

const guardar = () => {
    const opts = { preserveScroll: true, onSuccess: cerrar };
    if (panel.value === 'new') form.post(`/mallas/${props.malla.id}/ciclos/${cicloDestino.value.id}/cursos`, opts);
    else form.put(`/mallas/${props.malla.id}/cursos/${cursoSel.value.id}`, opts);
};

const eliminarCurso = (curso) => {
    if (confirm(`¿Eliminar el curso "${curso.nombre}"? (se conserva el histórico de convalidaciones)`))
        router.delete(`/mallas/${props.malla.id}/cursos/${curso.id}`, { preserveScroll: true, onSuccess: cerrar });
};

// ----- Ciclos -----
const siguienteNumero = computed(() => (props.ciclos.length ? Math.max(...props.ciclos.map((c) => c.numero)) + 1 : 1));
const puedeAgregarCiclo = computed(() => siguienteNumero.value <= props.malla.max_ciclos);

const agregarCiclo = () => {
    if (!puedeAgregarCiclo.value) return;
    router.post(`/mallas/${props.malla.id}/ciclos`, { numero: siguienteNumero.value }, { preserveScroll: true });
};
const eliminarCiclo = (ciclo) => {
    if (confirm(`¿Eliminar el Ciclo ${ciclo.numero}?`))
        router.delete(`/mallas/${props.malla.id}/ciclos/${ciclo.id}`, { preserveScroll: true });
};

const eliminarMalla = () => {
    const aviso = props.malla.activa
        ? `⚠️ Esta es la malla ACTIVA de ${props.malla.carrera} (${props.malla.version}, ${props.resumen.cursos} cursos). ¿Eliminarla de todos modos?`
        : `¿Eliminar la malla ${props.malla.carrera} — ${props.malla.version} (${props.resumen.cursos} cursos)? Las convalidaciones ya registradas no se ven afectadas.`;
    if (confirm(aviso)) router.delete(`/mallas/${props.malla.id}`);
};

// ----- Importar -----
const importForm = useForm({ archivo: null });
const fileInput = ref(null);
const importar = (e) => {
    const f = e.target.files[0];
    if (!f) return;
    importForm.archivo = f;
    importForm.post(`/mallas/${props.malla.id}/importar-cursos`, {
        preserveScroll: true,
        onFinish: () => { importForm.reset(); if (fileInput.value) fileInput.value.value = ''; },
    });
};

const prereqOpciones = computed(() =>
    props.cursosMalla.filter((c) => !cursoSel.value || c.id !== cursoSel.value.id));

const MODALIDAD = { presencial: 'Presencial', hibrido: 'Híbrido', virtual: 'Virtual' };

// ----- Filtros (cliente) -----
const buscar = ref('');
const filtroTipo = ref('');
const filtroCiclo = ref('');
const filtroMencion = ref('');
const limpiarFiltros = () => { buscar.value = ''; filtroTipo.value = ''; filtroCiclo.value = ''; filtroMencion.value = ''; };

// Menciones presentes en la malla (para el filtro y el autocompletado del formulario).
const mencionesDisponibles = computed(() =>
    [...new Set(props.ciclos.flatMap((c) => c.cursos.map((cu) => cu.mencion).filter(Boolean)))].sort());

const coincideMencion = (cu) => {
    if (!filtroMencion.value) return true;
    if (filtroMencion.value === '__reg') return !cu.mencion;
    return cu.mencion === filtroMencion.value;
};

const ciclosVista = computed(() => props.ciclos
    .filter((c) => !filtroCiclo.value || c.numero === Number(filtroCiclo.value))
    .map((c) => ({
        ...c,
        cursos: c.cursos.filter((cu) =>
            (!buscar.value || cu.nombre.toLowerCase().includes(buscar.value.toLowerCase())) &&
            (!filtroTipo.value || (filtroTipo.value === 'electivo' ? cu.es_electivo : !cu.es_electivo)) &&
            coincideMencion(cu)),
    })));

// Paleta por ciclo: riel lateral + chip del número (clases literales para el JIT de Tailwind).
const PALETA = [
    { rail: 'border-l-blue-500', bg: 'bg-blue-50', tx: 'text-blue-700' },
    { rail: 'border-l-emerald-500', bg: 'bg-emerald-50', tx: 'text-emerald-700' },
    { rail: 'border-l-violet-500', bg: 'bg-violet-50', tx: 'text-violet-700' },
    { rail: 'border-l-amber-500', bg: 'bg-amber-50', tx: 'text-amber-700' },
    { rail: 'border-l-rose-500', bg: 'bg-rose-50', tx: 'text-rose-700' },
    { rail: 'border-l-teal-500', bg: 'bg-teal-50', tx: 'text-teal-700' },
    { rail: 'border-l-sky-500', bg: 'bg-sky-50', tx: 'text-sky-700' },
    { rail: 'border-l-indigo-500', bg: 'bg-indigo-50', tx: 'text-indigo-700' },
    { rail: 'border-l-orange-500', bg: 'bg-orange-50', tx: 'text-orange-700' },
    { rail: 'border-l-pink-500', bg: 'bg-pink-50', tx: 'text-pink-700' },
];
const colorCiclo = (n) => PALETA[(n - 1) % PALETA.length];
const creditosCiclo = (c) => c.cursos.reduce((a, cu) => a + (Number(cu.creditos) || 0), 0);

// Colapsar / expandir ciclos.
const colapsados = reactive(new Set());
const toggleCiclo = (id) => { colapsados.has(id) ? colapsados.delete(id) : colapsados.add(id); };
const hayFiltro = computed(() => !!(buscar.value || filtroTipo.value || filtroCiclo.value || filtroMencion.value));
const todoColapsado = computed(() => props.ciclos.length > 0 && props.ciclos.every((c) => colapsados.has(c.id)));
const alternarTodos = () => {
    if (todoColapsado.value) colapsados.clear();
    else props.ciclos.forEach((c) => colapsados.add(c.id));
};

const ICON = {
    book: 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25',
    cap: 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
    globe: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.949 8.949 0 0 0 12 21Zm3-11.25a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    star: 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z',
    users: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    tag: 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3ZM6 6h.008v.008H6V6Z',
};
const tarjetas = computed(() => [
    { label: 'Cursos totales', valor: props.resumen.cursos, icon: ICON.book, ibg: 'bg-blue-50', it: 'text-blue-600', num: 'text-blue-600' },
    { label: 'Créditos totales', valor: props.resumen.creditos, icon: ICON.cap, ibg: 'bg-emerald-50', it: 'text-emerald-600', num: 'text-emerald-600' },
    { label: 'Ciclos académicos', valor: props.resumen.ciclos, icon: ICON.globe, ibg: 'bg-violet-50', it: 'text-violet-600', num: 'text-violet-600' },
    { label: 'Obligatorios', valor: props.resumen.obligatorios, icon: ICON.star, ibg: 'bg-amber-50', it: 'text-amber-600', num: 'text-amber-600' },
    { label: 'Electivos', valor: props.resumen.electivos, icon: ICON.users, ibg: 'bg-rose-50', it: 'text-rose-600', num: 'text-rose-600' },
    { label: 'Menciones', valor: props.resumen.menciones, icon: ICON.tag, ibg: 'bg-indigo-50', it: 'text-indigo-600', num: 'text-indigo-600' },
]);
</script>

<template>
    <div>
        <!-- Cabecera -->
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link href="/mallas" class="inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-slate-400 hover:text-[#2E75B6]">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    Mallas Curriculares
                </Link>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-[#1F3864]">{{ malla.carrera }}</h1>
                    <span :class="malla.activa ? 'bg-green-50 text-green-700 ring-green-200' : 'bg-slate-100 text-slate-500 ring-slate-200'"
                          class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">{{ malla.activa ? 'Activa' : 'Inactiva' }}</span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                    <span class="rounded-md bg-slate-100 px-2 py-1">Versión: <b class="text-slate-700">{{ malla.version }}</b></span>
                    <span class="rounded-md bg-slate-100 px-2 py-1">Período: <b class="text-slate-700">{{ malla.periodo || '—' }}</b></span>
                    <span class="rounded-md bg-slate-100 px-2 py-1">Modalidad: <b class="text-slate-700">{{ MODALIDAD[malla.modalidad] || malla.modalidad }}</b></span>
                    <span class="rounded-md bg-slate-100 px-2 py-1"><b class="text-slate-700">{{ resumen.creditos }}</b> créditos</span>
                    <span class="rounded-md bg-slate-100 px-2 py-1"><b class="text-slate-700">{{ resumen.cursos }}</b> cursos</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="`/mallas/${malla.id}/editar`" class="inline-flex items-center gap-2 rounded-lg bg-[#1F3864] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#2E75B6]">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                    Editar malla
                </Link>
                <button @click="eliminarMalla" class="inline-flex items-center gap-2 rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:border-red-300 hover:bg-red-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                    Eliminar malla
                </button>
            </div>
        </div>

        <!-- Resumen -->
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div v-for="t in tarjetas" :key="t.label" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div :class="t.ibg" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl">
                    <svg :class="t.it" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path :d="t.icon" /></svg>
                </div>
                <div>
                    <p :class="t.num" class="text-2xl font-bold leading-none">{{ t.valor }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ t.label }}</p>
                </div>
            </div>
        </div>

        <!-- Barra de acciones -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <a :href="`/mallas/${malla.id}/exportar`" class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Exportar</a>
            <button type="button" @click="verReglas = !verReglas"
                    class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                Materias que no se convalidan
                <span v-if="noConvalidables.propias.length" class="ml-1 rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-800">
                    {{ noConvalidables.propias.length }}
                </span>
            </button>
        </div>

        <!-- Materias de ORIGEN que esta carrera no convalida -->
        <div v-if="verReglas" class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                Materias de procedencia que no se convalidan en {{ malla.carrera }}
            </h2>
            <p class="mb-4 mt-1 text-sm text-slate-500">
                Se comparan con el nombre del curso que trae el postulante de su universidad. Lo que definas
                aquí <strong>vale solo para esta carrera</strong>; la política general la administra el
                administrador del sistema.
            </p>

            <div class="grid gap-5 lg:grid-cols-2">
                <!-- Reglas propias -->
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">De esta carrera</h3>
                    <div class="mb-3 space-y-2">
                        <div v-for="r in noConvalidables.propias" :key="r.id"
                             class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <div>
                                <p class="text-sm font-medium text-slate-700">{{ r.palabra_clave }}</p>
                                <p class="text-xs" :class="r.activo ? 'text-slate-500' : 'text-emerald-700'">
                                    {{ r.activo ? (r.motivo || 'Sin motivo indicado') : 'Excepción: aquí SÍ se convalida' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <button type="button" @click="alternarRegla(r)" class="font-medium text-[#2E75B6] hover:underline">
                                    {{ r.activo ? 'Convertir en excepción' : 'Volver a excluir' }}
                                </button>
                                <button type="button" @click="quitarRegla(r)" class="font-medium text-red-600 hover:underline">Quitar</button>
                            </div>
                        </div>
                        <p v-if="!noConvalidables.propias.length" class="rounded-lg bg-slate-50 px-3 py-4 text-center text-sm text-slate-400">
                            Esta carrera sigue solo la política institucional.
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-3">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Materia</label>
                        <input v-model="reglaNueva.palabra_clave" maxlength="120" placeholder="Ej.: Física"
                               class="mb-2 w-full rounded-md border-slate-300 text-sm" />
                        <label class="mb-1 block text-xs font-medium text-slate-500">Motivo (opcional)</label>
                        <input v-model="reglaNueva.motivo" maxlength="150" placeholder="Ej.: Ciencia básica no aplicable al plan"
                               class="mb-2 w-full rounded-md border-slate-300 text-sm" />
                        <p v-if="reglaError" class="mb-2 text-xs text-red-600">{{ reglaError }}</p>
                        <button type="button" @click="guardarRegla"
                                class="rounded-md bg-[#1F3864] px-3.5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6]">
                            Agregar
                        </button>
                    </div>
                </div>

                <!-- Reglas institucionales -->
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Institucionales</h3>
                    <div class="max-h-80 space-y-1.5 overflow-y-auto pr-1">
                        <div v-for="r in noConvalidables.institucionales" :key="r.clave"
                             class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-1.5"
                             :class="r.anulada_aqui ? 'opacity-60' : ''">
                            <div>
                                <p class="text-sm text-slate-700">
                                    {{ r.palabra_clave }}
                                    <span v-if="r.anulada_aqui" class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                        aquí sí se convalida
                                    </span>
                                </p>
                                <p v-if="r.motivo" class="text-xs text-slate-400">{{ r.motivo }}</p>
                            </div>
                            <button v-if="!r.anulada_aqui" type="button" @click="levantarInstitucional(r)"
                                    class="text-xs font-medium text-[#2E75B6] hover:underline">
                                No aplicar aquí
                            </button>
                        </div>
                        <p v-if="!noConvalidables.institucionales.length" class="rounded-lg bg-slate-50 px-3 py-4 text-center text-sm text-slate-400">
                            Sin política institucional definida.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Buscador + filtros -->
        <div class="mb-5 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="relative min-w-[200px] flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                </span>
                <input v-model="buscar" type="text" placeholder="Buscar curso por nombre…"
                       class="w-full rounded-lg border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
            </div>
            <select v-model="filtroTipo" class="rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                <option value="">Todos los tipos</option>
                <option value="obligatorio">Obligatorios</option>
                <option value="electivo">Electivos</option>
            </select>
            <select v-model="filtroCiclo" class="rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                <option value="">Todos los ciclos</option>
                <option v-for="c in ciclos" :key="c.id" :value="c.numero">Ciclo {{ c.numero }}</option>
            </select>
            <select v-if="mencionesDisponibles.length" v-model="filtroMencion" class="rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                <option value="">Todas las menciones</option>
                <option value="__reg">Solo ciclos regulares</option>
                <option v-for="m in mencionesDisponibles" :key="m" :value="m">{{ m }}</option>
            </select>
            <button @click="limpiarFiltros" class="inline-flex items-center gap-1 text-sm font-medium text-[#2E75B6] hover:underline">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                Limpiar filtros
            </button>
        </div>

        <!-- Progresión de ciclos: secciones verticales apiladas y colapsables (el plan es una secuencia, no carriles paralelos) -->
        <div v-if="ciclos.length">
            <div class="mb-2 flex items-center justify-end">
                <button @click="alternarTodos" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 15 3.75 3.75L15.75 15m-7.5-6L12 5.25 15.75 9" /></svg>
                    {{ todoColapsado ? 'Expandir todo' : 'Contraer todo' }}
                </button>
            </div>

            <div class="space-y-3">
                <template v-for="ciclo in ciclosVista" :key="ciclo.id">
                    <div v-if="!hayFiltro || ciclo.cursos.length"
                         :class="colorCiclo(ciclo.numero).rail"
                         class="overflow-hidden rounded-2xl border border-l-4 border-slate-200 bg-white shadow-sm">
                        <!-- Encabezado del ciclo (clic = colapsar/expandir) -->
                        <div class="flex items-center gap-2 px-3 py-2.5 sm:px-4">
                            <button @click="toggleCiclo(ciclo.id)" class="flex flex-1 items-center gap-3 text-left">
                                <span :class="[colorCiclo(ciclo.numero).bg, colorCiclo(ciclo.numero).tx]"
                                      class="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-sm font-bold">{{ ciclo.numero }}</span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-700">Ciclo {{ ciclo.numero }}</p>
                                    <p class="text-xs text-slate-400">{{ ciclo.cursos.length }} {{ ciclo.cursos.length === 1 ? 'curso' : 'cursos' }} · {{ creditosCiclo(ciclo) }} créditos</p>
                                </div>
                                <svg class="ml-1 h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="colapsados.has(ciclo.id) ? '' : 'rotate-90'"
                                     fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                            </button>
                            <button v-if="!ciclo.cursos.length" @click="eliminarCiclo(ciclo)" class="shrink-0 rounded-md px-1.5 py-0.5 text-slate-300 hover:bg-red-50 hover:text-red-600" title="Eliminar ciclo">✕</button>
                        </div>

                        <!-- Cursos en grilla (usa el ancho; sin scroll horizontal) -->
                        <div v-show="!colapsados.has(ciclo.id)" class="grid gap-2.5 border-t border-slate-100 p-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <button v-for="curso in ciclo.cursos" :key="curso.id" @click="verCurso(curso)"
                                    :class="cursoSel && cursoSel.id === curso.id ? 'border-[#2E75B6] ring-1 ring-[#2E75B6]' : 'border-slate-200'"
                                    class="group flex flex-col rounded-xl border bg-white p-3 text-left transition hover:border-[#2E75B6] hover:shadow-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-semibold leading-snug text-slate-800">{{ curso.nombre }}</p>
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-300 group-hover:text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="text-xs font-medium text-slate-500">{{ curso.creditos }} créd.</span>
                                    <span :class="curso.es_electivo ? 'bg-rose-50 text-rose-600' : 'bg-violet-50 text-violet-600'"
                                          class="rounded-full px-2 py-0.5 text-[11px] font-medium">{{ curso.es_electivo ? 'Electivo' : 'Obligatorio' }}</span>
                                    <span v-if="curso.convalidable === false"
                                          class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700" title="Este curso no se ofrece como destino de convalidación">No convalidable</span>
                                    <span v-if="curso.mencion"
                                          class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-600" :title="`Mención: ${curso.mencion}`">Mención</span>
                                </div>
                            </button>
                            <button @click="nuevoCurso(ciclo)"
                                    class="flex items-center justify-center gap-1.5 rounded-xl border border-dashed border-slate-300 py-3 text-sm font-medium text-[#2E75B6] hover:border-[#2E75B6] hover:bg-slate-50">
                                <span class="text-base leading-none">+</span> Agregar curso
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-400">
            Esta malla aún no tiene ciclos. Usa <span class="font-medium text-slate-600">“+ Nuevo ciclo”</span> para empezar.
        </div>

        <!-- Drawer de detalle / formulario -->
        <div v-if="panel" class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-slate-900/30" @click="cerrar"></div>
            <div class="relative z-10 h-full w-full max-w-md overflow-y-auto bg-white p-5 shadow-2xl">
                <!-- Vista de detalle (con pestañas) -->
                <template v-if="panel === 'view' && cursoSel">
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">{{ cursoSel.nombre }}</h2>
                            <span :class="cursoSel.es_electivo ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-violet-50 text-violet-700 ring-violet-200'"
                                  class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset">
                                {{ cursoSel.es_electivo ? 'Electivo' : 'Obligatorio' }}
                            </span>
                        </div>
                        <button @click="cerrar" class="text-slate-400 hover:text-slate-600">✕</button>
                    </div>

                    <div class="mb-4 flex gap-4 overflow-x-auto border-b border-slate-200 text-sm">
                        <button v-for="t in tabs" :key="t.id" @click="tab = t.id"
                                :class="tab === t.id ? 'border-[#2E75B6] text-[#1F3864]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                                class="-mb-px shrink-0 border-b-2 pb-2 font-medium">
                            {{ t.label }}
                            <span v-if="t.id === 'convalidaciones' && cursoSel.convalidaciones.length" class="ml-1 rounded-full bg-slate-100 px-1.5 text-xs">{{ cursoSel.convalidaciones.length }}</span>
                        </button>
                    </div>

                    <dl v-if="tab === 'info'" class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div><dt class="text-xs text-slate-400">Créditos</dt><dd class="font-medium text-slate-700">{{ cursoSel.creditos }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Tipo de curso</dt><dd class="text-slate-700">{{ TIPO[cursoSel.tipo_curso] || '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Carácter</dt><dd class="text-slate-700">{{ cursoSel.es_electivo ? 'Electivo' : 'Obligatorio' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Área</dt><dd class="text-slate-700">{{ cursoSel.area || '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Horas teoría</dt><dd class="text-slate-700">{{ cursoSel.horas_teoria ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Horas práctica</dt><dd class="text-slate-700">{{ cursoSel.horas_practica ?? '—' }}</dd></div>
                        <div v-if="cursoSel.mencion" class="col-span-2"><dt class="text-xs text-slate-400">Mención / especialidad</dt><dd class="font-medium text-indigo-700">{{ cursoSel.mencion }}</dd></div>
                        <div class="col-span-2"><dt class="text-xs text-slate-400">Prerrequisito</dt><dd class="text-slate-700">{{ cursoSel.prerequisito || '—' }}</dd></div>
                        <div v-if="cursoSel.silabo_texto" class="col-span-2"><dt class="text-xs text-slate-400">Descripción / sílabo</dt><dd class="text-slate-600">{{ cursoSel.silabo_texto }}</dd></div>
                    </dl>

                    <div v-else-if="tab === 'competencias'" class="space-y-4 text-sm">
                        <div>
                            <p class="mb-2 text-xs font-medium text-slate-400">Competencias relacionadas</p>
                            <div v-if="cursoSel.competencias.length" class="flex flex-wrap gap-2">
                                <span v-for="(comp, i) in cursoSel.competencias" :key="i" class="rounded-full bg-[#2E75B6]/10 px-2.5 py-0.5 text-xs font-medium text-[#2E75B6]">{{ comp }}</span>
                            </div>
                            <p v-else class="text-slate-400">Sin competencias registradas.</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs font-medium text-slate-400">Resultados de aprendizaje</p>
                            <p class="whitespace-pre-line text-slate-600">{{ cursoSel.resultados_aprendizaje || '—' }}</p>
                        </div>
                    </div>

                    <div v-else-if="tab === 'convalidaciones'" class="space-y-2 text-sm">
                        <div v-for="(c, i) in cursoSel.convalidaciones" :key="i" class="flex items-center justify-between rounded-lg border border-slate-200 p-3">
                            <div>
                                <p class="font-medium text-slate-700">{{ c.estudiante || 'Estudiante' }}</p>
                                <p class="text-xs text-slate-500">{{ c.creditos }} créditos · <span class="capitalize">{{ c.estado }}</span></p>
                            </div>
                            <span :class="c.excluido ? 'bg-slate-100 text-slate-500' : 'bg-green-50 text-green-700'" class="rounded-full px-2 py-0.5 text-xs font-medium">{{ c.excluido ? 'Excluido' : 'Reconocido' }}</span>
                        </div>
                        <p v-if="!cursoSel.convalidaciones.length" class="py-4 text-center text-slate-400">Este curso aún no aparece en simulaciones/convalidaciones.</p>
                    </div>

                    <dl v-else-if="tab === 'historial'" class="space-y-3 text-sm">
                        <div><dt class="text-xs text-slate-400">Creado</dt><dd class="text-slate-700">{{ cursoSel.creado || '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Última actualización</dt><dd class="text-slate-700">{{ cursoSel.actualizado || '—' }}</dd></div>
                        <p class="text-xs text-slate-400">Las operaciones sobre la malla quedan registradas en la auditoría (RNF-08).</p>
                    </dl>

                    <div class="mt-5 flex gap-2 border-t border-slate-200 pt-4">
                        <button @click="editarCurso" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Editar</button>
                        <button @click="eliminarCurso(cursoSel)" class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:border-red-300 hover:bg-red-50">Eliminar</button>
                    </div>
                </template>

                <!-- Formulario nuevo / editar -->
                <template v-else>
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-800">{{ panel === 'new' ? `Nuevo curso · Ciclo ${cicloDestino?.numero}` : 'Editar curso' }}</h2>
                        <button @click="cerrar" class="text-slate-400 hover:text-slate-600">✕</button>
                    </div>
                    <form @submit.prevent="guardar" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Código</label>
                                <input v-model="form.codigo" type="text" class="w-full rounded-md border-slate-300 text-sm" />
                                <p v-if="form.errors.codigo" class="mt-1 text-xs text-red-600">{{ form.errors.codigo }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Créditos</label>
                                <input v-model="form.creditos" type="number" step="0.5" class="w-full rounded-md border-slate-300 text-sm" />
                                <p v-if="form.errors.creditos" class="mt-1 text-xs text-red-600">{{ form.errors.creditos }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Nombre del curso</label>
                            <input v-model="form.nombre" type="text" class="w-full rounded-md border-slate-300 text-sm" />
                            <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Horas teoría</label>
                                <input v-model="form.horas_teoria" type="number" step="0.5" class="w-full rounded-md border-slate-300 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Horas práctica</label>
                                <input v-model="form.horas_practica" type="number" step="0.5" class="w-full rounded-md border-slate-300 text-sm" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Tipo de curso</label>
                                <select v-model="form.tipo_curso" class="w-full rounded-md border-slate-300 text-sm">
                                    <option value="">—</option>
                                    <option value="teorico">Teórico</option>
                                    <option value="practico">Práctico</option>
                                    <option value="teorico_practico">Teórico - Práctico</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Área</label>
                                <input v-model="form.area" type="text" placeholder="Especialidad, General…" class="w-full rounded-md border-slate-300 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Mención / especialidad <span class="text-slate-400">(opcional)</span></label>
                            <input v-model="form.mencion" list="menciones-datalist" type="text" placeholder="Sin mención (curso del plan regular)" class="w-full rounded-md border-slate-300 text-sm" />
                            <datalist id="menciones-datalist"><option v-for="m in mencionesDisponibles" :key="m" :value="m" /></datalist>
                            <p v-if="form.errors.mencion" class="mt-1 text-xs text-red-600">{{ form.errors.mencion }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Competencias <span class="text-slate-400">(separadas por coma)</span></label>
                            <input v-model="form.competencias" type="text" placeholder="Análisis, Diseño, Resolución de problemas" class="w-full rounded-md border-slate-300 text-sm" />
                            <p v-if="form.errors.competencias" class="mt-1 text-xs text-red-600">{{ form.errors.competencias }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Resultados de aprendizaje</label>
                            <textarea v-model="form.resultados_aprendizaje" rows="2" class="w-full rounded-md border-slate-300 text-sm"></textarea>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Prerrequisito</label>
                            <select v-model="form.prerequisito_id" class="w-full rounded-md border-slate-300 text-sm">
                                <option value="">Ninguno</option>
                                <option v-for="c in prereqOpciones" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Descripción / sílabo</label>
                            <textarea v-model="form.silabo_texto" rows="3" class="w-full rounded-md border-slate-300 text-sm"></textarea>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.es_electivo" type="checkbox" class="rounded border-slate-300 text-[#2E75B6]" /> Curso electivo
                        </label>
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input v-model="form.convalidable" type="checkbox" class="mt-0.5 rounded border-slate-300 text-[#2E75B6]" />
                            <span>Convalidable
                                <span class="block text-xs text-slate-400">Desmárcalo para que este curso NO se ofrezca como destino de convalidación (p. ej. Inglés, Proyecto para computación).</span>
                            </span>
                        </label>
                        <div class="flex gap-2 border-t border-slate-200 pt-3">
                            <button type="submit" :disabled="form.processing"
                                    class="rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                                {{ panel === 'new' ? 'Agregar curso' : 'Guardar cambios' }}
                            </button>
                            <button type="button" @click="cerrar" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
</template>
