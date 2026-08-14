<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';
import VolverA from '../../Components/VolverA.vue';

const props = defineProps({
    filas: Object,
    filtros: Object,
    carreras: Array,
    instituciones: Array,
});

const filtro = reactive({
    q: props.filtros?.q ?? '',
    institucion_id: props.filtros?.institucion_id ?? '',
    carrera_usil_id: props.filtros?.carrera_usil_id ?? '',
    solo_divergentes: !!props.filtros?.solo_divergentes,
});

const carrerasOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));
const institucionesOpts = computed(() => props.instituciones.map((i) => ({ value: i.id, label: i.nombre })));

const consulta = computed(() => {
    const p = { ...filtro };
    if (!p.solo_divergentes) delete p.solo_divergentes;
    return p;
});

const hayFiltrosActivos = computed(() =>
    Boolean(filtro.q || filtro.institucion_id || filtro.carrera_usil_id || filtro.solo_divergentes));

const aplicar = () => router.get('/simulaciones/historico', consulta.value, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => {
    Object.keys(filtro).forEach((k) => { filtro[k] = ''; });
    filtro.solo_divergentes = false;
    router.get('/simulaciones/historico', {}, { preserveScroll: true, replace: true });
};

const urlExcel = computed(() => {
    const p = new URLSearchParams(Object.entries(consulta.value).filter(([, v]) => v !== '' && v != null));
    const qs = p.toString();
    return '/simulaciones/historico/excel' + (qs ? `?${qs}` : '');
});

const divergentes = computed(() => !!props.filtros?.solo_divergentes);
const sinFiltros = computed(() => !filtro.q && !filtro.institucion_id && !filtro.carrera_usil_id && !filtro.solo_divergentes);
</script>

<template>
    <div class="max-w-6xl mx-auto pb-16">
        <VolverA href="/simulaciones" texto="Volver a Simulaciones" class="mb-4" />

        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-20 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Base de Conocimiento</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Histórico de equivalencias
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                            Consenso y precedentes de convalidación aplicados en expedientes anteriores. Sirve como material de referencia para homologaciones consistentes.
                        </p>
                    </div>

                    <a :href="urlExcel"
                       class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-xs font-bold text-[#1F3864] shadow-lg transition-all duration-300 hover:scale-105 hover:bg-blue-50 hover:shadow-xl shrink-0">
                        <svg class="h-4 w-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span>Descargar Excel</span>
                    </a>
                </div>

                <!-- Tira de 3 Micro-KPIs -->
                <div class="grid grid-cols-3 gap-4 pt-6">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Pares Registrados</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ filas.total || 0 }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Homologaciones previas</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Instituciones</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ instituciones.length || 0 }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Centros con precedentes</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Carreras USIL</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ carreras.length || 0 }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Planes vinculados</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= PANEL DE FILTROS ======================= -->
        <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-xs mb-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Buscar curso</label>
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input v-model="filtro.q" @keyup.enter="aplicar" type="search" placeholder="Nombre de origen o de la malla USIL…"
                               class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Institución de origen</label>
                    <Autocomplete v-model="filtro.institucion_id" :options="institucionesOpts" placeholder="Todas las instituciones" />
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Carrera destino (USIL)</label>
                    <Autocomplete v-model="filtro.carrera_usil_id" :options="carrerasOpts" placeholder="Todas las carreras" />
                </div>
            </div>

            <!-- Checkbox de Criterios Divergentes -->
            <div class="mt-4 pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <label class="flex cursor-pointer items-start gap-2.5">
                    <input v-model="filtro.solo_divergentes" @change="aplicar" type="checkbox"
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[#2E75B6] focus:ring-[#2E75B6] cursor-pointer" />
                    <div>
                        <span class="text-xs font-bold text-slate-800 block">Solo cursos con criterio dividido</span>
                        <span class="text-[11px] text-slate-400 block">
                            Muestra cursos que se han convalidado con más de un curso USIL en la misma carrera.
                        </span>
                    </div>
                </label>

                <div class="flex items-center gap-2 shrink-0">
                    <button @click="aplicar" type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-[#2E75B6] px-5 py-2 text-xs font-bold text-white hover:bg-[#1F3864] transition-colors shadow-2xs">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <span>Filtrar</span>
                    </button>
                    <button v-if="hayFiltrosActivos" @click="limpiar" type="button"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                        Limpiar filtros
                    </button>
                </div>
            </div>
        </div>

        <!-- ======================= TABLA DE HISTÓRICO ======================= -->
        <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50/90 text-left text-[11px] uppercase tracking-wider text-slate-500 font-extrabold">
                        <tr>
                            <th class="px-6 py-4">Curso de Origen</th>
                            <th class="px-6 py-4">Procedencia</th>
                            <th class="px-6 py-4">Convalidado con (USIL)</th>
                            <th class="px-6 py-4">Carrera Destino</th>
                            <th v-if="divergentes" class="px-6 py-4 text-center whitespace-nowrap">Criterios</th>
                            <th class="px-6 py-4 text-center whitespace-nowrap">Veces</th>
                            <th class="px-6 py-4 text-center whitespace-nowrap">Con Memorándum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(f, i) in filas.data" :key="i" class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4 font-bold text-slate-800 text-xs sm:text-sm">{{ f.origen_nombre }}</td>
                            <td class="px-6 py-4 text-slate-700">
                                <div class="font-bold">{{ f.institucion }}</div>
                                <div class="text-[11px] text-slate-400 font-medium">{{ f.carrera_externa }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-[#1F3864]">{{ f.curso_usil }}</div>
                                <div class="font-mono text-[10px] text-slate-400">{{ f.codigo_usil }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-600 font-medium">{{ f.carrera_usil }}</td>
                            <td v-if="divergentes" class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#1F3864] border border-blue-100">
                                    {{ f.criterios }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                                    {{ f.veces }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="f.confirmadas"
                                      class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {{ f.confirmadas }}
                                </span>
                                <span v-else class="text-xs text-slate-300">—</span>
                            </td>
                        </tr>
                        <tr v-if="!filas.data.length">
                            <td :colspan="divergentes ? 7 : 6" class="px-6 py-16 text-center">
                                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-[#2E75B6] mx-auto">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                    </svg>
                                </div>
                                <p v-if="sinFiltros" class="font-bold text-slate-700 text-xs uppercase tracking-wider">Todavía no hay equivalencias registradas</p>
                                <p v-else-if="divergentes" class="font-bold text-slate-700 text-xs uppercase tracking-wider">Ningún curso con criterio dividido</p>
                                <p v-else class="font-bold text-slate-700 text-xs uppercase tracking-wider">Ningún registro coincide con los filtros</p>
                                <p v-if="sinFiltros" class="mx-auto mt-1 max-w-md text-xs text-slate-400">
                                    Esta pantalla se alimenta automáticamente de las simulaciones validadas por el equipo.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="filas.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-6 py-4 bg-slate-50/50">
                <p class="text-xs text-slate-500 font-medium">
                    Mostrando <span class="font-bold text-slate-700">{{ filas.from }}</span>–<span class="font-bold text-slate-700">{{ filas.to }}</span>
                    de <span class="font-bold text-slate-700">{{ filas.total }}</span> equivalencias
                </p>
                <nav v-if="filas.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, idx) in filas.links" :key="idx">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#1F3864] text-white shadow-2xs font-bold' : 'text-slate-600 hover:bg-slate-100 font-semibold'"
                              class="min-w-[34px] rounded-xl px-2.5 py-1.5 text-center text-xs border border-slate-200 bg-white" v-html="link.label" />
                        <span v-else class="min-w-[34px] cursor-not-allowed rounded-xl px-2.5 py-1.5 text-center text-xs text-slate-300 border border-slate-100 bg-slate-50" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>

