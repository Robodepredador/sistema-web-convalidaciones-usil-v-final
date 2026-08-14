<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    postulantes: Object,
    carreras: Array,
    filtros: Object,
});

const filtro = reactive({
    q: props.filtros?.q ?? '',
    carrera_destino_id: props.filtros?.carrera_destino_id ?? '',
    desde: props.filtros?.desde ?? '',
    hasta: props.filtros?.hasta ?? '',
});
const carrerasOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));

const hayFiltrosActivos = computed(() =>
    Boolean(filtro.q || filtro.carrera_destino_id || filtro.desde || filtro.hasta));

const aplicar = () => router.get('/simulaciones', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => {
    Object.keys(filtro).forEach((k) => { filtro[k] = ''; });
    router.get('/simulaciones', {}, { preserveScroll: true, replace: true });
};
</script>

<template>
    <div class="max-w-6xl mx-auto pb-16">
        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] shadow-xl text-white">
            <!-- Decorative blur background -->
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-20 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
                            </svg>
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Evaluación de Convalidaciones</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Simulaciones de convalidación
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                            Evalúa expedientes de traslado externo, calcula los créditos reconocidos de manera manual o asistida por IA y genera dictámenes previos.
                        </p>
                    </div>

                    <!-- Botones de Acción y Estado IA en Header -->
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <Link href="/simulaciones/historico"
                              class="inline-flex items-center gap-2 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/20 px-4 py-2.5 text-xs font-bold text-white backdrop-blur-md shadow-sm transition-all duration-300">
                            <svg class="h-4 w-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            <span>Histórico de equivalencias</span>
                        </Link>

                        <span v-else
                              class="inline-flex items-center gap-1.5 rounded-2xl bg-white/10 border border-white/15 px-3.5 py-2.5 text-xs font-medium text-slate-200 backdrop-blur-md"
                              title="Configura GEMINI_API_KEY en .env para habilitar la IA">
                            <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                            <span>Modo similitud estándar</span>
                        </span>
                    </div>
                </div>

                <!-- Tira de Micro-KPIs -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-6">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Postulantes en Evaluación</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ postulantes.total || 0 }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Expedientes aptos para simulación</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Carreras Destino</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ carreras.length || 0 }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Planes curriculares disponibles</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10 col-span-2 sm:col-span-1">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Motor de Homologación</div>
                        <div class="text-sm font-bold text-white mt-2">Catálogo del especialista</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Solo se convalida lo que el especialista autorizó</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= PANEL DE FILTROS ======================= -->
        <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-xs mb-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Buscar postulante</label>
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input v-model="filtro.q" @keyup.enter="aplicar" type="search" placeholder="Nombre, apellido o documento…"
                               class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Carrera destino (USIL)</label>
                    <Autocomplete v-model="filtro.carrera_destino_id" :options="carrerasOpts" placeholder="Todas las carreras" />
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Solicitado desde</label>
                    <input v-model="filtro.desde" type="date" :max="filtro.hasta || undefined" @keyup.enter="aplicar"
                           class="w-full rounded-xl border border-slate-200 py-2 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Solicitado hasta</label>
                    <input v-model="filtro.hasta" type="date" :min="filtro.desde || undefined" @keyup.enter="aplicar"
                           class="w-full rounded-xl border border-slate-200 py-2 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
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

                <span class="text-xs text-slate-400 font-medium">
                    Mostrando <b>{{ postulantes.data?.length || 0 }}</b> registros
                </span>
            </div>
        </div>

        <!-- ======================= BANDEJA DE POSTULANTES ======================= -->
        <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50/90 text-left text-[11px] uppercase tracking-wider text-slate-500 font-extrabold">
                        <tr>
                            <th class="px-6 py-4">Postulante</th>
                            <th class="px-6 py-4">Documento</th>
                            <th class="px-6 py-4">Institución de Origen</th>
                            <th class="px-6 py-4">Carrera Destino (USIL)</th>
                            <th class="px-6 py-4 whitespace-nowrap">Solicitado</th>
                            <th class="px-6 py-4 text-center">Simulaciones</th>
                            <th class="px-6 py-4 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in postulantes.data" :key="p.destino_id" class="hover:bg-slate-50/70 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800 text-xs sm:text-sm group-hover:text-[#2E75B6] transition-colors">{{ p.nombre }}</div>
                                <div class="font-mono text-[11px] text-slate-400">{{ p.codigo }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-600 font-mono font-medium">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200">
                                    {{ p.documento }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-700">
                                <div class="font-bold">{{ p.institucion || '—' }}</div>
                                <div class="text-[11px] text-slate-400 font-medium">{{ p.carrera_externa }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-[#1F3864]">{{ p.carrera_destino || '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-slate-500 font-medium">{{ p.solicitado || '—' }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-full text-xs font-bold"
                                      :class="p.simulaciones_count > 0 ? 'bg-blue-50 text-[#2E75B6] border border-blue-100' : 'bg-slate-100 text-slate-500'">
                                    {{ p.simulaciones_count }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="`/simulaciones/simular/${p.id}?carrera=${p.carrera_destino_id}`"
                                      class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-[#1F3864] to-[#2E75B6] text-xs font-bold text-white shadow-2xs hover:shadow-md transition-all">
                                    <span>Simular</span>
                                    <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!postulantes.data.length">
                            <td colspan="7" class="px-6 py-16 text-center text-slate-400">
                                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-[#2E75B6] mx-auto">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                </div>
                                <p class="font-bold text-slate-700 text-xs uppercase tracking-wider">No se encontraron postulantes</p>
                                <p class="text-xs text-slate-400 mt-1">Verifica los filtros aplicados o registra nuevos postulantes en el módulo de Admisión.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="postulantes.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-6 py-4 bg-slate-50/50">
                <p class="text-xs text-slate-500 font-medium">
                    Mostrando <span class="font-bold text-slate-700">{{ postulantes.from }}</span>–<span class="font-bold text-slate-700">{{ postulantes.to }}</span>
                    de <span class="font-bold text-slate-700">{{ postulantes.total }}</span> postulantes
                </p>
                <nav v-if="postulantes.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, idx) in postulantes.links" :key="idx">
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

