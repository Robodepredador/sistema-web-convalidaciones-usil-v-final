<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    malla: Object,
    ciclos: Array,
    resumen: Object,
    cursosMalla: Array,
});

// ----- Panel (drawer): 'view' | 'edit' | 'new' | null -----
const panel = ref(null);
const cursoSel = ref(null);
const cicloDestino = ref(null);
const tab = ref('info');

const form = useForm({
    codigo: '', nombre: '', creditos: '', horas_teoria: '', horas_practica: '',
    es_electivo: false, mencion: '', prerequisito_id: '', silabo_texto: '',
    tipo_curso: '', area: '', competencias: '', resultados_aprendizaje: '',
});

const tabs = [
    { id: 'info', label: 'Información' },
    { id: 'competencias', label: 'Competencias' },
    { id: 'convalidaciones', label: 'Convalidaciones' },
    { id: 'historial', label: 'Historial' },
];

const TIPO = {
    teorico: 'Teórico',
    practico: 'Práctico',
    teorico_practico: 'Teórico - Práctico',
};

const verCurso = (curso) => { cursoSel.value = curso; tab.value = 'info'; panel.value = 'view'; };

const nuevoCurso = (ciclo) => {
    cicloDestino.value = ciclo;
    cursoSel.value = null;
    form.reset();
    form.clearErrors();
    panel.value = 'new';
};

const editarCurso = (curso = null) => {
    const c = curso || cursoSel.value;
    cursoSel.value = c;
    form.clearErrors();
    Object.assign(form, {
        codigo: c.codigo, nombre: c.nombre, creditos: c.creditos,
        horas_teoria: c.horas_teoria ?? '', horas_practica: c.horas_practica ?? '',
        es_electivo: Boolean(c.es_electivo), mencion: c.mencion ?? '', prerequisito_id: c.prerequisito_id ?? '', silabo_texto: c.silabo_texto ?? '',
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
            (!buscar.value || cu.nombre.toLowerCase().includes(buscar.value.toLowerCase()) || (cu.codigo && cu.codigo.toLowerCase().includes(buscar.value.toLowerCase()))) &&
            (!filtroTipo.value || (filtroTipo.value === 'electivo' ? cu.es_electivo : !cu.es_electivo)) &&
            coincideMencion(cu)),
    })));

// Paleta visual suave para ciclos
const PALETA = [
    { badge: 'bg-[#1F3864] text-white', light: 'bg-blue-50/70 border-blue-100', tx: 'text-[#1F3864]' },
    { badge: 'bg-[#2E75B6] text-white', light: 'bg-sky-50/70 border-sky-100', tx: 'text-[#2E75B6]' },
    { badge: 'bg-indigo-600 text-white', light: 'bg-indigo-50/70 border-indigo-100', tx: 'text-indigo-700' },
    { badge: 'bg-teal-700 text-white', light: 'bg-teal-50/70 border-teal-100', tx: 'text-teal-800' },
    { badge: 'bg-emerald-700 text-white', light: 'bg-emerald-50/70 border-emerald-100', tx: 'text-emerald-800' },
    { badge: 'bg-amber-700 text-white', light: 'bg-amber-50/70 border-amber-100', tx: 'text-amber-800' },
    { badge: 'bg-violet-700 text-white', light: 'bg-violet-50/70 border-violet-100', tx: 'text-violet-800' },
    { badge: 'bg-slate-700 text-white', light: 'bg-slate-50/70 border-slate-200', tx: 'text-slate-700' },
    { badge: 'bg-rose-700 text-white', light: 'bg-rose-50/70 border-rose-100', tx: 'text-rose-800' },
    { badge: 'bg-cyan-700 text-white', light: 'bg-cyan-50/70 border-cyan-100', tx: 'text-cyan-800' },
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
</script>

<template>
    <div class="max-w-7xl mx-auto pb-16">
        <VolverA href="/mallas" texto="Mallas curriculares" />

        <!-- HERO HEADER INTEGRADO CON MICRO-KPIS COMPACTOS -->
        <div class="mb-6 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] p-6 sm:p-8 text-white shadow-xl">
            <!-- Decorative blur background -->
            <div class="absolute -top-24 -right-24 w-80 h-80 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5 mb-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-white/10 border border-white/20 backdrop-blur-md text-blue-100">
                                <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                </svg>
                                Plan de Estudios Oficial
                            </span>
                            <span v-if="malla.activa" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 border border-emerald-400/40 text-emerald-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Activa
                            </span>
                            <span v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/10 text-slate-200">
                                Inactiva
                            </span>
                        </div>

                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            {{ malla.carrera }}
                        </h1>

                        <!-- Metadata compacta del plan -->
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-blue-100/90 font-medium">
                            <span class="bg-white/10 px-2.5 py-1 rounded-lg border border-white/10">Año <b>{{ malla.anio }}</b></span>
                            <span class="bg-white/10 px-2.5 py-1 rounded-lg border border-white/10">Versión: <b class="font-mono">{{ malla.version }}</b></span>
                            <span class="bg-white/10 px-2.5 py-1 rounded-lg border border-white/10">Modalidad: <b>{{ MODALIDAD[malla.modalidad] || malla.modalidad }}</b></span>
                            <span v-if="malla.periodo" class="bg-white/10 px-2.5 py-1 rounded-lg border border-white/10 font-mono">{{ malla.periodo }}</span>
                        </div>
                    </div>

                    <!-- Botones de Acción de Cabecera -->
                    <div class="flex flex-wrap items-center gap-2.5 self-stretch lg:self-center">
                        <a :href="`/mallas/${malla.id}/exportar`"
                           class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-white/10 text-xs font-bold text-white border border-white/20 hover:bg-white/20 transition-all shadow-xs"
                           title="Exportar estructura en formato Excel">
                            <svg class="w-4 h-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                            Exportar Excel
                        </a>

                        <Link :href="`/mallas/${malla.id}/editar`"
                              class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-white text-xs font-bold text-[#1F3864] hover:bg-blue-50 transition-all shadow-md">
                            <svg class="w-4 h-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                            </svg>
                            Editar
                        </Link>

                        <button @click="eliminarMalla"
                                class="inline-flex items-center justify-center p-2.5 rounded-xl bg-red-500/20 text-red-200 border border-red-400/30 hover:bg-red-500 hover:text-white transition-all shadow-xs"
                                title="Eliminar plan curricular">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Tira Compacta de Métricas (Reemplaza los 6 bloques gigantes) -->
                <div class="mt-6 pt-5 border-t border-white/15 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="flex items-center gap-2.5 px-3 py-2 rounded-2xl bg-white/10 border border-white/10 backdrop-blur-xs">
                        <div class="w-8 h-8 rounded-xl bg-blue-500/30 flex items-center justify-center text-blue-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                        </div>
                        <div>
                            <div class="text-base font-black text-white leading-none">{{ resumen.cursos }}</div>
                            <div class="text-[11px] text-blue-200 font-medium mt-0.5">Cursos totales</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 px-3 py-2 rounded-2xl bg-white/10 border border-white/10 backdrop-blur-xs">
                        <div class="w-8 h-8 rounded-xl bg-emerald-500/30 flex items-center justify-center text-emerald-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342" /></svg>
                        </div>
                        <div>
                            <div class="text-base font-black text-white leading-none">{{ resumen.creditos }}</div>
                            <div class="text-[11px] text-blue-200 font-medium mt-0.5">Créditos totales</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 px-3 py-2 rounded-2xl bg-white/10 border border-white/10 backdrop-blur-xs">
                        <div class="w-8 h-8 rounded-xl bg-violet-500/30 flex items-center justify-center text-violet-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18z" /></svg>
                        </div>
                        <div>
                            <div class="text-base font-black text-white leading-none">{{ resumen.ciclos }}</div>
                            <div class="text-[11px] text-blue-200 font-medium mt-0.5">Ciclos académicos</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 px-3 py-2 rounded-2xl bg-white/10 border border-white/10 backdrop-blur-xs">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/30 flex items-center justify-center text-amber-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5Z" /></svg>
                        </div>
                        <div>
                            <div class="text-base font-black text-white leading-none">{{ resumen.obligatorios }}</div>
                            <div class="text-[11px] text-blue-200 font-medium mt-0.5">Obligatorios</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 px-3 py-2 rounded-2xl bg-white/10 border border-white/10 backdrop-blur-xs">
                        <div class="w-8 h-8 rounded-xl bg-rose-500/30 flex items-center justify-center text-rose-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0Z" /></svg>
                        </div>
                        <div>
                            <div class="text-base font-black text-white leading-none">{{ resumen.electivos }}</div>
                            <div class="text-[11px] text-blue-200 font-medium mt-0.5">Electivos</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 px-3 py-2 rounded-2xl bg-white/10 border border-white/10 backdrop-blur-xs">
                        <div class="w-8 h-8 rounded-xl bg-indigo-500/30 flex items-center justify-center text-indigo-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3ZM6 6h.008v.008H6V6Z" /></svg>
                        </div>
                        <div>
                            <div class="text-base font-black text-white leading-none">{{ resumen.menciones }}</div>
                            <div class="text-[11px] text-blue-200 font-medium mt-0.5">Menciones</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BARRA COMPACTA DE BÚSQUEDA Y FILTROS -->
        <div class="mb-6 bg-white rounded-2xl border border-slate-200/80 p-3 shadow-xs flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5 flex-1 min-w-[280px]">
                <!-- Input de búsqueda -->
                <div class="relative flex-1 min-w-[200px]">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607Z" /></svg>
                    </span>
                    <input v-model="buscar" type="text" placeholder="Buscar por código o nombre…"
                           class="w-full rounded-xl border-slate-200 py-1.5 pl-9 pr-3 text-xs focus:border-[#2E75B6] focus:ring-2 focus:ring-[#2E75B6]/20 transition-all" />
                </div>

                <!-- Filtros rápidos tipo select -->
                <select v-model="filtroTipo" class="rounded-xl border-slate-200 py-1.5 px-3 text-xs focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                    <option value="">Todos los tipos</option>
                    <option value="obligatorio">Obligatorios</option>
                    <option value="electivo">Electivos</option>
                </select>

                <select v-model="filtroCiclo" class="rounded-xl border-slate-200 py-1.5 px-3 text-xs focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                    <option value="">Todos los ciclos</option>
                    <option v-for="c in ciclos" :key="c.id" :value="c.numero">Ciclo {{ c.numero }}</option>
                </select>

                <select v-if="mencionesDisponibles.length" v-model="filtroMencion" class="rounded-xl border-slate-200 py-1.5 px-3 text-xs focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                    <option value="">Todas las menciones</option>
                    <option value="__reg">Solo plan regular</option>
                    <option v-for="m in mencionesDisponibles" :key="m" :value="m">{{ m }}</option>
                </select>

                <button v-if="hayFiltro" @click="limpiarFiltros"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-[#2E75B6] hover:bg-blue-50 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    Limpiar
                </button>
            </div>

            <!-- Botones de Acción Global de Ciclos -->
            <div class="flex items-center gap-2">
                <button @click="alternarTodos"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                    </svg>
                    {{ todoColapsado ? 'Expandir todos' : 'Contraer todos' }}
                </button>

                <button v-if="puedeAgregarCiclo" @click="agregarCiclo"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-[#1F3864] text-xs font-bold text-white hover:bg-[#2E75B6] transition-all shadow-xs">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    + Nuevo Ciclo
                </button>
            </div>
        </div>

        <!-- NUEVO PARADIGMA: TABLA COMPACTA Y MODULAR DE CICLOS -->
        <div v-if="ciclos.length" class="space-y-4">
            <template v-for="ciclo in ciclosVista" :key="ciclo.id">
                <div v-if="!hayFiltro || ciclo.cursos.length"
                     class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden transition-all duration-200 hover:border-slate-300">
                    
                    <!-- Barra de Título del Ciclo (Compacta y Clickable) -->
                    <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-slate-50 via-white to-slate-50 border-b border-slate-100">
                        <button @click="toggleCiclo(ciclo.id)" class="flex items-center gap-3 text-left focus:outline-none group">
                            <span :class="colorCiclo(ciclo.numero).badge"
                                  class="h-7 min-w-[2rem] px-2 rounded-lg flex items-center justify-center text-xs font-bold shadow-2xs">
                                {{ ciclo.numero }}
                            </span>
                            <div>
                                <span class="text-sm font-bold text-slate-800 group-hover:text-[#1F3864] transition-colors">
                                    Ciclo {{ ciclo.numero }}
                                </span>
                                <span class="text-xs text-slate-400 ml-2">
                                    ({{ ciclo.cursos.length }} {{ ciclo.cursos.length === 1 ? 'asignatura' : 'asignaturas' }} · {{ creditosCiclo(ciclo) }} créditos)
                                </span>
                            </div>
                            <svg class="h-4 w-4 text-slate-400 transition-transform duration-200 ml-1"
                                 :class="colapsados.has(ciclo.id) ? '' : 'rotate-180'"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div class="flex items-center gap-2">
                            <button @click="nuevoCurso(ciclo)"
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-blue-50 text-xs font-bold text-[#2E75B6] hover:bg-[#2E75B6] hover:text-white transition-all">
                                <span class="text-sm leading-none">+</span> Agregar curso
                            </button>
                            <button v-if="!ciclo.cursos.length" @click="eliminarCiclo(ciclo)"
                                    class="p-1 rounded-md text-slate-300 hover:text-red-600 hover:bg-red-50 transition-colors"
                                    title="Eliminar ciclo vacío">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Lista Compacta de Asignaturas en Fila de Alta Densidad -->
                    <div v-show="!colapsados.has(ciclo.id)" class="divide-y divide-slate-100">
                        <div v-if="!ciclo.cursos.length" class="px-4 py-6 text-center text-xs text-slate-400">
                            No hay cursos en este ciclo. Usa <button @click="nuevoCurso(ciclo)" class="font-bold text-[#2E75B6] hover:underline">+ Agregar curso</button> para comenzar.
                        </div>

                        <div v-for="curso in ciclo.cursos" :key="curso.id"
                             class="group px-4 py-2.5 flex items-center justify-between gap-3 hover:bg-slate-50/80 transition-colors">
                            
                            <!-- Código y Nombre del Curso (Clickable para abrir Drawer) -->
                            <div @click="verCurso(curso)" class="flex items-center gap-3 flex-1 min-w-0 cursor-pointer">
                                <span class="font-mono text-xs font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md shrink-0 group-hover:bg-blue-100 group-hover:text-[#1F3864] transition-colors">
                                    {{ curso.codigo || 'S/C' }}
                                </span>
                                <span class="text-xs sm:text-sm font-semibold text-slate-800 truncate group-hover:text-[#2E75B6] transition-colors">
                                    {{ curso.nombre }}
                                </span>
                                <span v-if="curso.mencion" class="hidden md:inline-flex px-2 py-0.2 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700">
                                    {{ curso.mencion }}
                                </span>
                            </div>

                            <!-- Metadata Compacta (Tipo, Prerrequisito, Créditos) -->
                            <div class="flex items-center gap-3 shrink-0">
                                <span :class="curso.es_electivo ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-slate-100 text-slate-600 ring-slate-200'"
                                      class="hidden sm:inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ring-1 ring-inset">
                                    {{ curso.es_electivo ? 'Electivo' : 'Obligatorio' }}
                                </span>

                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold bg-blue-50 text-[#2E75B6]">
                                    {{ curso.creditos }} cr
                                </span>

                                <!-- Botones de Acción Rápida -->
                                <div class="flex items-center gap-1 opacity-80 group-hover:opacity-100">
                                    <button @click.stop="verCurso(curso)"
                                            class="p-1 rounded-md text-slate-400 hover:text-[#2E75B6] hover:bg-blue-50 transition-colors"
                                            title="Ver ficha completa">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    </button>
                                    <button @click.stop="editarCurso(curso)"
                                            class="p-1 rounded-md text-slate-400 hover:text-[#2E75B6] hover:bg-blue-50 transition-colors"
                                            title="Editar curso">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                                    </button>
                                    <button @click.stop="eliminarCurso(curso)"
                                            class="p-1 rounded-md text-slate-300 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Eliminar curso">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div v-else class="rounded-3xl border-2 border-dashed border-slate-200 bg-white p-12 text-center text-sm text-slate-400">
            Esta malla aún no tiene ciclos configurados. Haz clic en <button @click="agregarCiclo" class="font-bold text-[#2E75B6] hover:underline">“+ Nuevo Ciclo”</button> para empezar.
        </div>

        <!-- DRAWER MODERNO Y ELEGANTE DE DETALLE / FORMULARIO -->
        <div v-if="panel" class="fixed inset-0 z-50 flex justify-end">
            <!-- Backdrop con desenfoque -->
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity" @click="cerrar"></div>

            <div class="relative z-10 h-full w-full max-w-lg overflow-y-auto bg-white p-6 sm:p-8 shadow-2xl flex flex-col justify-between">
                
                <!-- VISTA DE DETALLE DEL CURSO -->
                <template v-if="panel === 'view' && cursoSel">
                    <div>
                        <!-- Header del Drawer -->
                        <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-100">
                            <div>
                                <span class="font-mono text-xs font-bold text-[#2E75B6] bg-blue-50 px-2 py-0.5 rounded-md">
                                    {{ cursoSel.codigo || 'SIN CÓDIGO' }}
                                </span>
                                <h2 class="text-lg font-bold text-slate-900 mt-1 leading-snug">{{ cursoSel.nombre }}</h2>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span :class="cursoSel.es_electivo ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-violet-50 text-violet-700 ring-violet-200'"
                                          class="inline-block rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset">
                                        {{ cursoSel.es_electivo ? 'Electivo' : 'Obligatorio' }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-600">{{ cursoSel.creditos }} Créditos</span>
                                </div>
                            </div>
                            <button @click="cerrar" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <!-- Pestañas del Drawer -->
                        <div class="flex gap-4 border-b border-slate-100 text-xs font-bold uppercase tracking-wider mt-4">
                            <button v-for="t in tabs" :key="t.id" @click="tab = t.id"
                                    :class="tab === t.id ? 'border-[#2E75B6] text-[#1F3864]' : 'border-transparent text-slate-400 hover:text-slate-600'"
                                    class="-mb-px pb-2.5 border-b-2 transition-colors">
                                {{ t.label }}
                                <span v-if="t.id === 'convalidaciones' && cursoSel.convalidaciones?.length" class="ml-1 rounded-full bg-blue-100 text-[#2E75B6] px-1.5 py-0.2 text-[10px]">
                                    {{ cursoSel.convalidaciones.length }}
                                </span>
                            </button>
                        </div>

                        <!-- Contenido Pestaña Info -->
                        <div v-if="tab === 'info'" class="py-4 space-y-4 text-xs">
                            <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-slate-50 border border-slate-100">
                                <div><span class="text-slate-400 block font-medium">Créditos</span><span class="font-bold text-slate-800 text-sm">{{ cursoSel.creditos }}</span></div>
                                <div><span class="text-slate-400 block font-medium">Tipo de curso</span><span class="font-bold text-slate-800 text-sm">{{ TIPO[cursoSel.tipo_curso] || '—' }}</span></div>
                                <div><span class="text-slate-400 block font-medium">Horas teoría</span><span class="font-bold text-slate-800 text-sm">{{ cursoSel.horas_teoria ?? '—' }}</span></div>
                                <div><span class="text-slate-400 block font-medium">Horas práctica</span><span class="font-bold text-slate-800 text-sm">{{ cursoSel.horas_practica ?? '—' }}</span></div>
                            </div>

                            <div v-if="cursoSel.area" class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                <span class="text-slate-400 block font-medium">Área formativa</span>
                                <span class="font-bold text-slate-800">{{ cursoSel.area }}</span>
                            </div>

                            <div v-if="cursoSel.mencion" class="p-3 rounded-xl bg-indigo-50/50 border border-indigo-100">
                                <span class="text-indigo-500 block font-medium">Mención o Especialidad</span>
                                <span class="font-bold text-indigo-900">{{ cursoSel.mencion }}</span>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                <span class="text-slate-400 block font-medium">Prerrequisito</span>
                                <span class="font-bold text-slate-800">{{ cursoSel.prerequisito || 'Ninguno' }}</span>
                            </div>

                            <div v-if="cursoSel.silabo_texto" class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                <span class="text-slate-400 block font-medium mb-1">Descripción / Sílabo</span>
                                <p class="text-slate-600 leading-relaxed">{{ cursoSel.silabo_texto }}</p>
                            </div>
                        </div>

                        <!-- Contenido Pestaña Competencias -->
                        <div v-else-if="tab === 'competencias'" class="py-4 space-y-4 text-xs">
                            <div>
                                <span class="text-slate-400 block font-medium mb-2 uppercase tracking-wider">Competencias Desarrolladas</span>
                                <div v-if="cursoSel.competencias?.length" class="flex flex-wrap gap-1.5">
                                    <span v-for="(comp, i) in cursoSel.competencias" :key="i"
                                          class="rounded-xl bg-blue-50 border border-blue-100 px-3 py-1 font-semibold text-[#2E75B6]">
                                        {{ comp }}
                                    </span>
                                </div>
                                <p v-else class="text-slate-400 italic">No se han registrado competencias para esta asignatura.</p>
                            </div>
                            <div class="pt-3 border-t border-slate-100">
                                <span class="text-slate-400 block font-medium mb-1 uppercase tracking-wider">Resultados de Aprendizaje</span>
                                <p class="text-slate-700 whitespace-pre-line leading-relaxed">{{ cursoSel.resultados_aprendizaje || '—' }}</p>
                            </div>
                        </div>

                        <!-- Contenido Pestaña Convalidaciones -->
                        <div v-else-if="tab === 'convalidaciones'" class="py-4 space-y-2 text-xs">
                            <div v-for="(c, i) in (cursoSel.convalidaciones || [])" :key="i"
                                 class="p-3 rounded-xl border border-slate-200 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-slate-800">{{ c.estudiante || 'Estudiante' }}</div>
                                    <div class="text-slate-400 text-[11px]">{{ c.creditos }} créditos · {{ c.estado }}</div>
                                </div>
                                <span :class="c.excluido ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-700'"
                                      class="px-2 py-0.5 rounded-md font-bold text-[10px]">
                                    {{ c.excluido ? 'Excluido' : 'Reconocido' }}
                                </span>
                            </div>
                            <p v-if="!cursoSel.convalidaciones?.length" class="text-center text-slate-400 py-6 italic">
                                Sin registros de convalidación activos para esta asignatura.
                            </p>
                        </div>

                        <!-- Contenido Pestaña Historial -->
                        <div v-else-if="tab === 'historial'" class="py-4 space-y-3 text-xs">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                <span class="text-slate-400 block font-medium">Fecha de Creación</span>
                                <span class="font-bold text-slate-800">{{ cursoSel.creado || '—' }}</span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                <span class="text-slate-400 block font-medium">Última Actualización</span>
                                <span class="font-bold text-slate-800">{{ cursoSel.actualizado || '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer de Acciones del Drawer -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
                        <button @click="eliminarCurso(cursoSel)"
                                class="px-4 py-2 rounded-xl border border-red-200 text-red-600 text-xs font-bold hover:bg-red-50 transition-colors">
                            Eliminar asignatura
                        </button>
                        <button @click="editarCurso(cursoSel)"
                                class="px-5 py-2 rounded-xl bg-[#1F3864] text-white text-xs font-bold hover:bg-[#2E75B6] transition-all shadow-xs">
                            Editar asignatura
                        </button>
                    </div>
                </template>

                <!-- FORMULARIO DE NUEVO / EDITAR CURSO -->
                <template v-else>
                    <div>
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                            <h2 class="text-base font-bold text-slate-900">
                                {{ panel === 'new' ? `Nueva Asignatura · Ciclo ${cicloDestino?.numero}` : 'Editar Asignatura' }}
                            </h2>
                            <button @click="cerrar" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="guardar" class="py-4 space-y-4 text-xs">
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Código</label>
                                    <input v-model="form.codigo" type="text" placeholder="INF101"
                                           class="w-full rounded-xl border-slate-300 text-xs font-mono uppercase focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                                    <p v-if="form.errors.codigo" class="text-red-600 text-[10px] mt-1">{{ form.errors.codigo }}</p>
                                </div>
                                <div class="col-span-2">
                                    <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Créditos</label>
                                    <input v-model="form.creditos" type="number" step="0.5" min="0" placeholder="4.0"
                                           class="w-full rounded-xl border-slate-300 text-xs font-bold focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                                    <p v-if="form.errors.creditos" class="text-red-600 text-[10px] mt-1">{{ form.errors.creditos }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Nombre de la Asignatura</label>
                                <input v-model="form.nombre" type="text" placeholder="Ej. Algoritmos y Estructuras de Datos"
                                       class="w-full rounded-xl border-slate-300 text-xs focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                                <p v-if="form.errors.nombre" class="text-red-600 text-[10px] mt-1">{{ form.errors.nombre }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Horas Teoría</label>
                                    <input v-model="form.horas_teoria" type="number" step="0.5" class="w-full rounded-xl border-slate-300 text-xs" />
                                </div>
                                <div>
                                    <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Horas Práctica</label>
                                    <input v-model="form.horas_practica" type="number" step="0.5" class="w-full rounded-xl border-slate-300 text-xs" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Tipo de Curso</label>
                                    <select v-model="form.tipo_curso" class="w-full rounded-xl border-slate-300 text-xs">
                                        <option value="">—</option>
                                        <option value="teorico">Teórico</option>
                                        <option value="practico">Práctico</option>
                                        <option value="teorico_practico">Teórico - Práctico</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Área Formativa</label>
                                    <input v-model="form.area" type="text" placeholder="General, Especialidad…" class="w-full rounded-xl border-slate-300 text-xs" />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Mención (Opcional)</label>
                                <input v-model="form.mencion" list="menciones-datalist" type="text" placeholder="Plan regular (sin mención)" class="w-full rounded-xl border-slate-300 text-xs" />
                                <datalist id="menciones-datalist"><option v-for="m in mencionesDisponibles" :key="m" :value="m" /></datalist>
                            </div>

                            <div>
                                <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Prerrequisito</label>
                                <select v-model="form.prerequisito_id" class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="">Ninguno</option>
                                    <option v-for="c in prereqOpciones" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Competencias (separadas por coma)</label>
                                <input v-model="form.competencias" type="text" placeholder="Diseño de software, Trabajo en equipo" class="w-full rounded-xl border-slate-300 text-xs" />
                            </div>

                            <div>
                                <label class="mb-1 block font-bold text-slate-600 uppercase tracking-wider text-[11px]">Resultados de Aprendizaje</label>
                                <textarea v-model="form.resultados_aprendizaje" rows="2" class="w-full rounded-xl border-slate-300 text-xs"></textarea>
                            </div>

                            <label class="flex items-center gap-2 text-xs font-bold text-slate-700 p-2 rounded-xl bg-slate-50 cursor-pointer">
                                <input v-model="form.es_electivo" type="checkbox" class="rounded border-slate-300 text-[#2E75B6] focus:ring-[#2E75B6]" />
                                Asignatura de carácter electivo
                            </label>

                            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                                <button type="button" @click="cerrar" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-600">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="form.processing"
                                        class="px-6 py-2 rounded-xl bg-gradient-to-r from-[#1F3864] to-[#2E75B6] text-white text-xs font-bold shadow-md hover:shadow transition-all disabled:opacity-60">
                                    {{ panel === 'new' ? 'Agregar asignatura' : 'Guardar cambios' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

