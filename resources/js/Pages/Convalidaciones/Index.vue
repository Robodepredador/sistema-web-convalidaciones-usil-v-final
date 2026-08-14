<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    simulaciones: Object,
    carreras: Array,
    filtros: Object,
    kpis: Object,
});

// ── Filtros ──
const filtro = reactive({
    q: props.filtros?.q ?? '',
    carrera_id: props.filtros?.carrera_id ?? '',
    estado: props.filtros?.estado ?? '',
    desde: props.filtros?.desde ?? '',
    hasta: props.filtros?.hasta ?? '',
});

const carrerasOpts = computed(() => (props.carreras || []).map((c) => ({ value: c.id, label: c.nombre })));

const hayFiltrosActivos = computed(() =>
    Boolean(filtro.q || filtro.carrera_id || filtro.estado || filtro.desde || filtro.hasta));

const aplicar = () => {
    router.get('/convalidaciones', filtro, { preserveState: true, preserveScroll: true, replace: true });
};

const limpiar = () => {
    Object.keys(filtro).forEach((k) => { filtro[k] = ''; });
    router.get('/convalidaciones', {}, { preserveScroll: true, replace: true });
};

// ── UI Auxiliar ──
const detalleAbierto = reactive({});
const toggleDetalle = (id) => { detalleAbierto[id] = !detalleAbierto[id]; };

const descargarArchivo = (url) => {
    const a = document.createElement('a');
    a.href = url;
    a.rel = 'noopener';
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    a.remove();
};
</script>

<template>
    <div class="max-w-7xl mx-auto pb-16">
        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-20 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Actas y Dictámenes Oficiales</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Pre-Convalidaciones Oficiales
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                            Visualiza, audita y descarga las actas formalizadas de convalidación académica y dictámenes oficiales de traslado.
                        </p>
                    </div>

                    <Link href="/simulaciones"
                          class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-xs font-bold text-[#1F3864] shadow-lg transition-all duration-300 hover:scale-105 hover:bg-blue-50 hover:shadow-xl shrink-0">
                        <svg class="h-4 w-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
                        </svg>
                        <span>Nueva Simulación</span>
                    </Link>
                </div>

                <!-- Tira de 4 Micro-KPIs -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 pt-6">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Simulaciones Totales</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ kpis?.total_simulaciones ?? 0 }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Expedientes procesados</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Dictámenes Validados</div>
                        <div class="text-2xl font-extrabold text-emerald-300 mt-1">{{ kpis?.total_validadas ?? 0 }}</div>
                        <div class="text-[10px] text-emerald-200/80 mt-0.5">Actas con conformidad</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Créditos Promedio</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ kpis?.creditos_promedio ?? 0 }} <span class="text-xs font-normal text-blue-200">cr.</span></div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Por expediente evaluado</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Carreras Atendidas</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ kpis?.carreras_atendidas ?? 0 }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Planes USIL receptores</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= PANEL DE FILTROS ======================= -->
        <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-xs mb-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Buscar -->
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Buscar estudiante / doc</label>
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input v-model="filtro.q" type="search" placeholder="Nombres, DNI o institución…" @keyup.enter="aplicar"
                               class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                </div>

                <!-- Carrera Destino -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Carrera destino (USIL)</label>
                    <Autocomplete v-model="filtro.carrera_id" :options="carrerasOpts" placeholder="Todas las carreras" />
                </div>

                <!-- Desde -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Fecha desde</label>
                    <input v-model="filtro.desde" type="date" :max="filtro.hasta || undefined"
                           class="w-full rounded-xl border border-slate-200 py-2 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>

                <!-- Hasta -->
                <div>
                    <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Fecha hasta</label>
                    <input v-model="filtro.hasta" type="date" :min="filtro.desde || undefined"
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

                <div class="text-xs text-slate-400 font-medium">
                    Mostrando <b class="text-slate-700">{{ simulaciones.total }}</b> dictámenes registrados
                </div>
            </div>
        </div>

        <!-- ======================= TABLA DE PRE-CONVALIDACIONES ======================= -->
        <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50/90 text-left text-[11px] uppercase tracking-wider text-slate-500 font-extrabold">
                        <tr>
                            <th class="px-6 py-4">Expediente ID</th>
                            <th class="px-6 py-4">Estudiante / Procedencia</th>
                            <th class="px-6 py-4">Carrera Destino</th>
                            <th class="px-6 py-4 text-center whitespace-nowrap">Asignaturas</th>
                            <th class="px-6 py-4 text-center whitespace-nowrap">Créditos</th>
                            <th class="px-6 py-4">Estado</th>
                            <th class="px-6 py-4 text-right">Descargas & Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="c in simulaciones.data" :key="c.id">
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- ID & Fecha -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-extrabold text-[#1F3864] text-xs sm:text-sm">#{{ c.id }}</div>
                                    <div class="text-[11px] text-slate-400 font-medium mt-0.5">{{ c.fecha }}</div>
                                </td>

                                <!-- Estudiante & Origen -->
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800 text-xs sm:text-sm">{{ c.estudiante }}</div>
                                    <div class="flex items-center gap-1.5 mt-1 text-[11px] text-slate-500">
                                        <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-[10px] font-bold text-slate-600">
                                            {{ c.documento }}
                                        </span>
                                        <span>·</span>
                                        <span class="truncate max-w-xs text-slate-600 font-medium">{{ c.origen || 'Sin institución de origen' }}</span>
                                    </div>
                                </td>

                                <!-- Carrera Destino USIL -->
                                <td class="px-6 py-4">
                                    <div class="font-bold text-[#1F3864]">{{ c.carrera || '—' }}</div>
                                    <div v-if="c.carrera_codigo" class="font-mono text-[10px] text-slate-400 mt-0.5">
                                        {{ c.carrera_codigo }}
                                    </div>
                                </td>

                                <!-- Asignaturas -->
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center justify-center min-w-[2rem] px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-[#1F3864] border border-blue-100">
                                        {{ c.convalidados }}
                                    </span>
                                </td>

                                <!-- Créditos -->
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ c.creditos.toFixed(1) }} cr.
                                    </span>
                                </td>

                                <!-- Estado -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold"
                                          :class="c.estado === 'aceptada' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-[#1F3864] border border-blue-100'">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="c.estado === 'aceptada' ? 'bg-emerald-500' : 'bg-[#2E75B6]'"></span>
                                        {{ c.estado === 'aceptada' ? 'Validada' : 'Generada' }}
                                    </span>
                                </td>

                                <!-- Acciones y Descargas -->
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- PDF Oficial -->
                                        <button v-if="c.pdf_preconv" @click="descargarArchivo(c.pdf_preconv)" title="Descargar Dictamen Oficial en PDF"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:text-[#1F3864] hover:bg-slate-50 transition-colors shadow-2xs font-bold text-[11px]">
                                            <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            <span>PDF</span>
                                        </button>

                                        <!-- Excel Oficial -->
                                        <button v-if="c.excel_oficial_preconv" @click="descargarArchivo(c.excel_oficial_preconv)" title="Descargar Acta en Excel Oficial"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 transition-colors shadow-2xs font-bold text-[11px]">
                                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span>Excel Oficial</span>
                                        </button>

                                        <!-- Ver Expediente Detalle -->
                                        <Link :href="`/simulaciones/${c.id}`" title="Ver Expediente de Convalidación"
                                              class="p-1.5 rounded-xl border border-slate-200 bg-white text-slate-600 hover:text-[#1F3864] hover:bg-slate-50 transition-colors shadow-2xs">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                        </Link>

                                        <!-- Toggle Acordeón de Asignaturas -->
                                        <button @click="toggleDetalle('c'+c.id)" title="Desplegar asignaturas convalidadas"
                                                class="p-1.5 rounded-xl border border-slate-200 bg-white text-slate-600 hover:text-[#1F3864] hover:bg-slate-50 transition-colors shadow-2xs">
                                            <svg class="h-4 w-4 transition-transform duration-200" :class="detalleAbierto['c'+c.id] ? 'rotate-180 text-[#2E75B6]' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Acordeón Detalle Cursos Convalidados -->
                            <tr v-if="detalleAbierto['c'+c.id]" class="bg-slate-50/50">
                                <td colspan="7" class="p-0">
                                    <div class="border-y border-slate-200 px-6 sm:px-10 py-5 bg-gradient-to-b from-slate-50/80 to-slate-100/40">
                                        <div class="flex items-center justify-between mb-3">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                                                </svg>
                                                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-700">
                                                    Matriz de Equivalencias Oficiales (#{{ c.id }})
                                                </span>
                                            </div>
                                            <span class="text-xs font-bold text-slate-500">
                                                {{ c.cursos?.length || 0 }} cursos homologados
                                            </span>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-2xs">
                                            <table class="min-w-full text-xs divide-y divide-slate-100">
                                                <thead class="bg-slate-50/90 text-left text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                                                    <tr>
                                                        <th class="px-5 py-2.5">Curso de Origen</th>
                                                        <th class="px-5 py-2.5">Convalida con (USIL)</th>
                                                        <th class="px-5 py-2.5 text-right">Créditos Reconocidos</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    <tr v-for="curso in c.cursos" :key="curso.usil" class="hover:bg-slate-50/70 transition-colors">
                                                        <td class="px-5 py-2.5 text-slate-800 font-bold">{{ curso.origen }}</td>
                                                        <td class="px-5 py-2.5">
                                                            <div class="font-bold text-[#1F3864] flex items-center gap-1.5">
                                                                <span class="text-emerald-500 font-bold">✓</span>
                                                                <span>{{ curso.usil }}</span>
                                                            </div>
                                                            <div v-if="curso.codigo_usil" class="font-mono text-[10px] text-slate-400 pl-4">
                                                                {{ curso.codigo_usil }}
                                                            </div>
                                                        </td>
                                                        <td class="px-5 py-2.5 text-right font-mono font-bold text-emerald-700">
                                                            {{ curso.creditos.toFixed(1) }} cr.
                                                        </td>
                                                    </tr>
                                                    <tr v-if="!c.cursos?.length">
                                                        <td colspan="3" class="px-5 py-6 text-center text-slate-400 font-medium">
                                                            No hay cursos asignados en este expediente.
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <!-- Estado vacío -->
                        <tr v-if="!simulaciones.data.length">
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-[#2E75B6] mx-auto">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                    </svg>
                                </div>
                                <p class="font-bold text-slate-700 text-xs uppercase tracking-wider">No se encontraron pre-convalidaciones oficiales</p>
                                <p class="mx-auto mt-1 max-w-md text-xs text-slate-400">
                                    No hay actas que coincidan con los criterios de búsqueda o el rango de fechas seleccionado.
                                </p>
                                <button v-if="hayFiltrosActivos" @click="limpiar" type="button"
                                        class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                                    Restablecer filtros
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="simulaciones.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-6 py-4 bg-slate-50/50">
                <p class="text-xs text-slate-500 font-medium">
                    Mostrando <span class="font-bold text-slate-700">{{ simulaciones.from }}</span>–<span class="font-bold text-slate-700">{{ simulaciones.to }}</span>
                    de <span class="font-bold text-slate-700">{{ simulaciones.total }}</span> expedientes
                </p>
                <nav v-if="simulaciones.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, idx) in simulaciones.links" :key="idx">
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

