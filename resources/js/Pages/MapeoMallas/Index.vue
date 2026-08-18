<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    mapeos: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            total_mapeos: 0,
            total_equivalencias: 0,
            instituciones_unicas: 0,
            carreras_usil_unicas: 0,
        }),
    },
});

// Filtros reactivos
const busqueda = ref('');
const institucionFiltro = ref('todas');
const carreraUsilFiltro = ref('todas');
const tipoFiltro = ref('todos');

// Listas únicas para los desplegables de filtro
const listaInstituciones = computed(() => {
    const set = new Set(props.mapeos.map((m) => m.institucion).filter(Boolean));
    return Array.from(set).sort();
});

const listaCarrerasUsil = computed(() => {
    const set = new Set(props.mapeos.map((m) => m.carrera_usil).filter(Boolean));
    return Array.from(set).sort();
});

const listaTipos = computed(() => {
    const set = new Set(props.mapeos.map((m) => m.tipo_institucion).filter(Boolean));
    return Array.from(set).sort();
});

const hayFiltrosActivos = computed(() =>
    Boolean(busqueda.value.trim() || institucionFiltro.value !== 'todas' || carreraUsilFiltro.value !== 'todas' || tipoFiltro.value !== 'todos'));

const limpiarFiltros = () => {
    busqueda.value = '';
    institucionFiltro.value = 'todas';
    carreraUsilFiltro.value = 'todas';
    tipoFiltro.value = 'todos';
};

// Mapeos filtrados
const mapeosFiltrados = computed(() => {
    const q = busqueda.value.trim().toLowerCase();
    return props.mapeos.filter((m) => {
        if (institucionFiltro.value !== 'todas' && m.institucion !== institucionFiltro.value) return false;
        if (carreraUsilFiltro.value !== 'todas' && m.carrera_usil !== carreraUsilFiltro.value) return false;
        if (tipoFiltro.value !== 'todos' && m.tipo_institucion !== tipoFiltro.value) return false;
        if (q) {
            const matchInst = (m.institucion || '').toLowerCase().includes(q);
            const matchExt = (m.carrera_externa || '').toLowerCase().includes(q);
            const matchUsil = (m.carrera_usil || '').toLowerCase().includes(q);
            const matchFac = (m.facultad_usil || '').toLowerCase().includes(q);
            const matchPlan = (m.plan_usil || '').toLowerCase().includes(q);
            if (!matchInst && !matchExt && !matchUsil && !matchFac && !matchPlan) return false;
        }
        return true;
    });
});

// Paginación del cliente
const porPagina = ref(8);
const paginaActual = ref(1);
const totalPaginas = computed(() => Math.max(1, Math.ceil(mapeosFiltrados.value.length / porPagina.value)));

const mapeosPaginados = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina.value;
    return mapeosFiltrados.value.slice(inicio, inicio + porPagina.value);
});

const irA = (p) => {
    if (p >= 1 && p <= totalPaginas.value) paginaActual.value = p;
};
</script>

<template>
    <div class="w-full pb-16">
        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <!-- Decorative blur background -->
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#0036DC] opacity-20 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Catálogo de Equivalencias</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Mapeo de equivalencias
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                            Criterios oficiales y reglas de equivalencia entre planes de estudio externos y carreras USIL para homologaciones directas.
                        </p>
                    </div>

                    <Link href="/equivalencias-catalogo/crear"
                          class="group inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-xs font-bold text-[#00205B] shadow-lg transition-all duration-300 hover:scale-105 hover:bg-blue-50 hover:shadow-xl shrink-0">
                        <svg class="w-4 h-4 text-[#0036DC] transition-transform duration-300 group-hover:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Nuevo mapeo</span>
                    </Link>
                </div>

                <!-- Tira de 4 Micro-KPIs -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-6">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Mapeos Activos</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ stats.total_mapeos }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Relaciones entre carreras</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Reglas Registradas</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ stats.total_equivalencias }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Pares curso ⇄ curso</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Instituciones</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ stats.instituciones_unicas }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Centros de procedencia</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Carreras USIL</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ stats.carreras_usil_unicas }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Planes con homologación</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= PANEL DE FILTROS ======================= -->
        <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-xs mb-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Buscador de texto -->
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Buscar por texto</label>
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input v-model="busqueda" type="text"
                               placeholder="Institución, carrera, plan…"
                               class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#0036DC] focus:ring-[#0036DC]" />
                    </div>
                </div>

                <!-- Filtro Institución -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Institución de Origen</label>
                    <select v-model="institucionFiltro"
                            class="w-full rounded-xl border-slate-200 py-2 text-xs font-medium text-slate-800 focus:border-[#0036DC] focus:ring-[#0036DC]">
                        <option value="todas">Todas las instituciones</option>
                        <option v-for="inst in listaInstituciones" :key="inst" :value="inst">{{ inst }}</option>
                    </select>
                </div>

                <!-- Filtro Carrera USIL -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Carrera USIL (Destino)</label>
                    <select v-model="carreraUsilFiltro"
                            class="w-full rounded-xl border-slate-200 py-2 text-xs font-medium text-slate-800 focus:border-[#0036DC] focus:ring-[#0036DC]">
                        <option value="todas">Todas las carreras USIL</option>
                        <option v-for="c in listaCarrerasUsil" :key="c" :value="c">{{ c }}</option>
                    </select>
                </div>

                <!-- Filtro Tipo de Institución -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Tipo de Institución</label>
                    <select v-model="tipoFiltro"
                            class="w-full rounded-xl border-slate-200 py-2 text-xs font-medium text-slate-800 focus:border-[#0036DC] focus:ring-[#0036DC]">
                        <option value="todos">Todos los tipos</option>
                        <option v-for="t in listaTipos" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
            </div>

            <!-- Chips de Filtros Activos -->
            <div v-if="hayFiltrosActivos" class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-400 font-medium">Mostrando {{ mapeosFiltrados.length }} de {{ mapeos.length }} mapeos</span>
                    <span v-if="busqueda" class="bg-blue-50 text-[#0036DC] px-2.5 py-0.5 rounded-lg font-semibold text-[11px]">
                        Texto: "{{ busqueda }}"
                    </span>
                    <span v-if="institucionFiltro !== 'todas'" class="bg-blue-50 text-[#0036DC] px-2.5 py-0.5 rounded-lg font-semibold text-[11px]">
                        {{ institucionFiltro }}
                    </span>
                    <span v-if="carreraUsilFiltro !== 'todas'" class="bg-blue-50 text-[#0036DC] px-2.5 py-0.5 rounded-lg font-semibold text-[11px]">
                        {{ carreraUsilFiltro }}
                    </span>
                    <span v-if="tipoFiltro !== 'todos'" class="bg-blue-50 text-[#0036DC] px-2.5 py-0.5 rounded-lg font-semibold text-[11px]">
                        {{ tipoFiltro }}
                    </span>
                </div>

                <button type="button" @click="limpiarFiltros"
                        class="text-xs font-bold text-slate-500 hover:text-red-600 transition-colors">
                    Limpiar filtros
                </button>
            </div>
        </div>

        <!-- ======================= BANDEJA DE MAPEOS ======================= -->
        <div class="space-y-4">
            <div v-for="(m, i) in mapeosPaginados" :key="i"
                 class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-xs hover:shadow-md hover:border-blue-200 transition-all duration-300 group">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <!-- Columna Izquierda: Institución de Origen y Carrera USIL -->
                    <div class="flex-1 grid gap-4 md:grid-cols-2">
                        <!-- Origen -->
                        <div class="flex items-start gap-3.5">
                            <div class="h-10 w-10 shrink-0 rounded-2xl bg-gradient-to-br from-indigo-50 to-blue-50 text-[#0036DC] flex items-center justify-center border border-blue-100/60 shadow-2xs mt-0.5">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.315 48.315 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">
                                        {{ m.tipo_institucion || 'Institución' }}
                                    </span>
                                    <span v-if="m.pais_origen" class="text-[10px] font-medium text-slate-500 flex items-center gap-1">
                                        <span>•</span> {{ m.pais_origen }}
                                    </span>
                                </div>
                                <h3 class="font-extrabold text-slate-800 text-sm leading-tight">{{ m.institucion }}</h3>
                                <p class="text-xs font-semibold text-[#0036DC] mt-0.5">{{ m.carrera_externa }}</p>
                            </div>
                        </div>

                        <!-- Destino USIL -->
                        <div class="flex items-start gap-3.5 border-t md:border-t-0 md:border-l border-slate-100 pt-3 md:pt-0 md:pl-4">
                            <div class="h-10 w-10 shrink-0 rounded-2xl bg-blue-50/70 text-[#00205B] flex items-center justify-center border border-blue-100/60 shadow-2xs mt-0.5">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5Zm0 0v-3.675A55.378 55.378 0 0112 8.443" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-blue-50 text-[#00205B]">
                                        USIL Destino
                                    </span>
                                    <span class="text-[10px] font-mono text-slate-500">
                                        {{ m.plan_usil }}
                                    </span>
                                </div>
                                <h3 class="font-extrabold text-[#00205B] text-sm leading-tight">{{ m.carrera_usil }}</h3>
                                <p v-if="m.facultad_usil" class="text-xs text-slate-500 mt-0.5">{{ m.facultad_usil }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Avance de Equivalencias y Acción -->
                    <div class="flex flex-wrap sm:flex-nowrap items-center justify-between lg:justify-end gap-5 pt-3 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                        <!-- Métrica de avance -->
                        <div class="min-w-[140px]">
                            <div class="flex items-center justify-between text-xs font-bold mb-1">
                                <span class="text-slate-700">{{ m.cursos_con_equivalencia }} cursos USIL</span>
                                <span class="text-[#0036DC] font-mono" v-if="m.total_cursos_malla">
                                    {{ Math.round((m.cursos_con_equivalencia / m.total_cursos_malla) * 100) }}%
                                </span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-gradient-to-r from-[#0036DC] to-emerald-400"
                                     :style="{ width: (m.total_cursos_malla ? Math.min(100, Math.round((m.cursos_con_equivalencia / m.total_cursos_malla) * 100)) : 10) + '%' }"></div>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1">
                                <span>{{ m.total_opciones }} reglas registradas</span>
                                <span v-if="m.ultima_actualizacion">Act. {{ m.ultima_actualizacion }}</span>
                            </div>
                        </div>

                        <!-- CTA Continuar Mapeo -->
                        <Link :href="`/equivalencias-catalogo/crear?carrera_externa_id=${m.carrera_externa_id}&carrera_usil_id=${m.carrera_usil_id}`"
                              class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-blue-50 text-xs font-bold text-[#0036DC] hover:bg-[#0036DC] hover:text-white transition-all shadow-2xs group-hover:bg-[#00205B] group-hover:text-white shrink-0">
                            <span>Gestionar</span>
                            <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Estado Vacío -->
            <div v-if="mapeosFiltrados.length === 0"
                 class="bg-white rounded-3xl border border-slate-200/80 p-12 text-center shadow-xs">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-[#0036DC] mx-auto">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">No se encontraron mapeos</h3>
                <p class="max-w-sm text-xs text-slate-400 mx-auto mt-1 mb-5">
                    {{ hayFiltrosActivos ? 'No hay resultados que coincidan con los filtros de búsqueda aplicados.' : 'Aún no se han configurado mapeos de equivalencias en el catálogo.' }}
                </p>
                <button v-if="hayFiltrosActivos" type="button" @click="limpiarFiltros"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                    Restablecer filtros
                </button>
                <Link v-else href="/equivalencias-catalogo/crear"
                      class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#00205B] text-xs font-bold text-white hover:bg-[#0036DC] transition-all shadow-md">
                    + Crear mi primer mapeo
                </Link>
            </div>

            <!-- Paginación -->
            <div v-if="totalPaginas > 1"
                 class="flex flex-wrap items-center justify-between gap-3 bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-xs">
                <span class="text-xs text-slate-500 font-medium">
                    Mostrando página <b>{{ paginaActual }}</b> de <b>{{ totalPaginas }}</b>
                </span>

                <nav class="flex items-center gap-1">
                    <button type="button" @click="irA(paginaActual - 1)" :disabled="paginaActual === 1"
                            class="h-8 w-8 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-xs font-bold text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">
                        ‹
                    </button>
                    <button v-for="p in totalPaginas" :key="p" type="button" @click="irA(p)"
                            :class="p === paginaActual ? 'bg-[#00205B] text-white shadow-2xs' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-100'"
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
    </div>
</template>

