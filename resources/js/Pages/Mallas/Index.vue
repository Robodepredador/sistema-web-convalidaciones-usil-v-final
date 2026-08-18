<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    mallas: Object,
    mallasActivas: { type: Number, default: 0 },
    unidades: { type: Array, default: () => [] },
    facultades: { type: Array, default: () => [] },
    carreras: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
});

const filtro = reactive({
    unidad_negocio_id: props.filtros?.unidad_negocio_id ?? '',
    facultad_id: props.filtros?.facultad_id ?? '',
    carrera_id: props.filtros?.carrera_id ?? '',
    anio: props.filtros?.anio ?? '',
});

// Filtros en cascada: facultad depende de la unidad; carrera depende de la facultad.
const facultadesFiltradas = computed(() =>
    filtro.unidad_negocio_id
        ? props.facultades.filter((f) => String(f.unidad_negocio_id) === String(filtro.unidad_negocio_id))
        : props.facultades,
);

const carrerasFiltradas = computed(() => {
    if (filtro.facultad_id) {
        return props.carreras.filter((c) => String(c.facultad_id) === String(filtro.facultad_id));
    }
    if (filtro.unidad_negocio_id) {
        const ids = facultadesFiltradas.value.map((f) => String(f.id));
        return props.carreras.filter((c) => ids.includes(String(c.facultad_id)));
    }
    return props.carreras;
});

const onUnidadChange = () => {
    filtro.facultad_id = '';
    filtro.carrera_id = '';
};
const onFacultadChange = () => {
    filtro.carrera_id = '';
};

const unidadesOpts = computed(() => props.unidades.map((u) => ({ value: u.id, label: u.nombre })));
const facultadesOpts = computed(() => facultadesFiltradas.value.map((f) => ({ value: f.id, label: f.nombre })));
const carrerasOpts = computed(() => carrerasFiltradas.value.map((c) => ({ value: c.id, label: c.nombre })));

const tieneFiltrosActivos = computed(() => {
    return Boolean(filtro.unidad_negocio_id || filtro.facultad_id || filtro.carrera_id || filtro.anio);
});

const aplicar = () => router.get('/mallas', filtro, { preserveState: true, preserveScroll: true, replace: true });

const limpiar = () => {
    filtro.unidad_negocio_id = '';
    filtro.facultad_id = '';
    filtro.carrera_id = '';
    filtro.anio = '';
    router.get('/mallas', {}, { preserveScroll: true, replace: true });
};

const origenConfig = (o) => {
    if (o === 'excel') {
        return { label: 'Excel', icon: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5', clase: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/70 border-emerald-100' };
    }
    return { label: 'Manual', icon: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125', clase: 'bg-slate-50 text-slate-600 ring-1 ring-slate-200/70 border-slate-100' };
};

const MODALIDADES = {
    presencial: { label: 'Presencial', clase: 'bg-sky-50 text-sky-700 ring-sky-200/80', dot: 'bg-sky-500' },
    hibrido: { label: 'Híbrido', clase: 'bg-indigo-50 text-indigo-700 ring-indigo-200/80', dot: 'bg-indigo-500' },
    virtual: { label: 'Virtual', clase: 'bg-teal-50 text-teal-700 ring-teal-200/80', dot: 'bg-teal-500' },
};
const modalidad = (m) => MODALIDADES[m] ?? { label: m ?? '—', clase: 'bg-slate-50 text-slate-600 ring-slate-200', dot: 'bg-slate-400' };

const eliminar = (m) => {
    const aviso = m.activa
        ? `⚠️ Esta es la malla ACTIVA de ${m.carrera} (${m.version}). ¿Eliminarla de todos modos?`
        : `¿Eliminar la malla ${m.carrera} — ${m.version}?`;
    if (confirm(aviso)) router.delete(`/mallas/${m.id}`, { preserveScroll: true });
};
</script>

<template>
    <div class="w-full pb-12">
        <!-- Banner Header Hero -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <!-- Decorative background elements -->
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#0036DC] opacity-25 rounded-full blur-2xl"></div>
            <div class="absolute -bottom-10 -left-10 w-48 h-48 bg-white opacity-5 rounded-full blur-xl"></div>
            
            <div class="relative z-10 px-8 py-10 sm:p-12 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-8">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 mb-4 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                        <svg class="w-4 h-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Gestión Académica · USIL</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight leading-tight">
                        Gestión de Mallas Curriculares
                    </h1>
                    <p class="mt-3 text-base text-blue-100/90 leading-relaxed max-w-xl">
                        Administración, control de versiones y seguimiento de los planes de estudio académicos vigentes e históricos de la institución.
                    </p>
                </div>

                <div class="flex items-center gap-3 w-full lg:w-auto">
                    <Link href="/mallas/crear"
                          class="group relative inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-bold text-[#00205B] shadow-lg transition-all duration-300 hover:scale-105 hover:bg-blue-50 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-white/30">
                        <svg class="w-5 h-5 transition-transform duration-300 group-hover:rotate-90 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Nueva malla
                    </Link>
                </div>
            </div>
        </div>

        <!-- KPIs y Panel de Filtros -->
        <div class="mb-8 grid gap-5 lg:grid-cols-12 items-stretch">
            <!-- Filtros Modernos (9 cols, z-20 y overflow visible para no recortar el autocompletado) -->
            <div class="lg:col-span-9 bg-white rounded-3xl border border-slate-200/70 p-6 shadow-sm relative z-20">
                <div class="flex items-center justify-between gap-4 mb-4 pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#0036DC] flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                            </svg>
                        </div>
                        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Filtros de Búsqueda</h2>
                    </div>
                    <div v-if="tieneFiltrosActivos" class="flex items-center gap-1.5">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-[#0036DC]">
                            Filtros aplicados
                        </span>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="relative z-50">
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            Unidad de Negocio
                        </label>
                        <Autocomplete v-model="filtro.unidad_negocio_id" :options="unidadesOpts"
                                      placeholder="Todas las unidades" @update:modelValue="onUnidadChange" />
                    </div>
                    <div class="relative z-40">
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /></svg>
                            Facultad
                        </label>
                        <Autocomplete v-model="filtro.facultad_id" :options="facultadesOpts"
                                      placeholder="Todas las facultades" @update:modelValue="onFacultadChange" />
                    </div>
                    <div class="relative z-30">
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                            Carrera
                        </label>
                        <Autocomplete v-model="filtro.carrera_id" :options="carrerasOpts" placeholder="Todas las carreras" />
                    </div>
                    <div class="relative z-20">
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            Año
                        </label>
                        <input v-model="filtro.anio" type="number" placeholder="Ej. 2026" min="2000" max="2100"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-[#0036DC] focus:ring-[#0036DC] transition-colors" />
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button v-if="tieneFiltrosActivos" @click="limpiar"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Limpiar
                    </button>
                    <button @click="aplicar"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#0036DC] transition-all duration-200 hover:shadow">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        Filtrar mallas
                    </button>
                </div>
            </div>

            <!-- Tarjeta Resumen KPI Mallas Activas (3 cols) -->
            <div class="lg:col-span-3 flex flex-col">
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] p-6 text-white shadow-md h-full flex flex-col justify-between">
                    <div class="absolute -right-6 -bottom-6 w-28 h-28 bg-white/10 rounded-full blur-xl"></div>
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-200">Mallas Activas</span>
                            <span class="flex h-3 w-3 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl sm:text-5xl font-black tracking-tight text-white">{{ mallasActivas }}</span>
                            <span class="text-xs text-blue-200 font-medium">vigentes</span>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-blue-100/80 leading-relaxed border-t border-white/10 pt-3">
                        Planes activos oficiales disponibles para simulaciones y convalidaciones.
                    </p>
                </div>
            </div>
        </div>


        <!-- Tabla / Listado de Mallas en Card -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200/70 overflow-hidden relative">
            <!-- Top subtle highlight line -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-[#0036DC]/30 to-transparent"></div>

            <div class="p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4 px-2">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Planes de Estudio Registrados</h2>
                        <p class="text-xs text-slate-500">Mallas curriculares organizadas por programa académico</p>
                    </div>
                    <div v-if="mallas.data?.length" class="text-xs font-semibold text-slate-500 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200/60">
                        Mostrando <span class="text-[#00205B]">{{ mallas.from }}</span>–<span class="text-[#00205B]">{{ mallas.to }}</span> de <span class="text-[#00205B]">{{ mallas.total }}</span> registros
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2.5">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 font-bold px-4">
                                <th class="px-5 py-2">Programa Académico</th>
                                <th class="px-5 py-2">Plan & Versión</th>
                                <th class="px-5 py-2 text-center">Modalidad</th>
                                <th class="px-5 py-2 text-center">Origen</th>
                                <th class="px-5 py-2 text-center">Estado</th>
                                <th class="px-5 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in mallas.data" :key="m.id"
                                class="group relative bg-white transition-all duration-200 hover:shadow-md hover:bg-slate-50/60 rounded-2xl">
                                
                                <!-- Programa Académico & Facultad -->
                                <td class="px-5 py-4 rounded-l-2xl border-y border-l border-slate-100 group-hover:border-blue-100">
                                    <div class="flex items-start gap-3.5">
                                        <div class="mt-0.5 h-10 w-10 shrink-0 flex items-center justify-center rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 text-[#00205B] ring-1 ring-inset ring-blue-100">
                                            <svg class="h-5 w-5 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm sm:text-base group-hover:text-[#00205B] transition-colors">
                                                {{ m.carrera }}
                                            </div>
                                            <div class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500 mt-0.5">
                                                <span class="font-medium text-slate-600">{{ m.facultad }}</span>
                                                <span class="text-slate-300">•</span>
                                                <span class="text-slate-400">{{ m.unidad }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Plan & Versión -->
                                <td class="px-5 py-4 border-y border-slate-100 group-hover:border-blue-100">
                                    <div class="font-bold text-slate-700 text-sm">
                                        Plan {{ m.anio }}
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-mono font-medium bg-slate-100 text-slate-600">
                                            v{{ m.version }}
                                        </span>
                                        <span v-if="m.periodo" class="text-xs text-slate-400 font-mono">
                                            {{ m.periodo }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Modalidad -->
                                <td class="px-5 py-4 border-y border-slate-100 group-hover:border-blue-100 text-center">
                                    <span :class="modalidad(m.modalidad).clase"
                                          class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset shadow-xs">
                                        <span :class="modalidad(m.modalidad).dot" class="h-1.5 w-1.5 rounded-full"></span>
                                        {{ modalidad(m.modalidad).label }}
                                    </span>
                                </td>

                                <!-- Origen -->
                                <td class="px-5 py-4 border-y border-slate-100 group-hover:border-blue-100 text-center">
                                    <span :class="origenConfig(m.origen).clase"
                                          class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium border">
                                        <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="origenConfig(m.origen).icon" />
                                        </svg>
                                        {{ origenConfig(m.origen).label }}
                                    </span>
                                </td>

                                <!-- Estado -->
                                <td class="px-5 py-4 border-y border-slate-100 group-hover:border-blue-100 text-center">
                                    <span v-if="m.activa"
                                          class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200/80 shadow-xs">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Activa
                                    </span>
                                    <span v-else
                                          class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-200/80">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        Inactiva
                                    </span>
                                </td>

                                <!-- Acciones -->
                                <td class="px-5 py-4 rounded-r-2xl border-y border-r border-slate-100 group-hover:border-blue-100 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Link :href="`/mallas/${m.id}`"
                                              class="inline-flex items-center gap-1.5 rounded-xl bg-[#00205B] px-3.5 py-1.5 text-xs font-bold text-white shadow-xs transition-all duration-200 hover:bg-[#0036DC] hover:scale-105"
                                              title="Gestionar ciclos y cursos">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                            </svg>
                                            Gestionar
                                        </Link>

                                        <Link :href="`/mallas/${m.id}/editar`"
                                              class="inline-flex items-center justify-center p-1.5 rounded-xl border border-slate-200 text-slate-600 hover:text-[#0036DC] hover:border-[#0036DC] hover:bg-blue-50/50 transition-colors"
                                              title="Editar parámetros del plan">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                            </svg>
                                        </Link>

                                        <button @click="eliminar(m)"
                                                class="inline-flex items-center justify-center p-1.5 rounded-xl border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50/50 transition-colors"
                                                title="Eliminar malla">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="!mallas.data?.length">
                                <td colspan="6" class="px-6 py-16 text-center rounded-2xl border border-slate-100 bg-slate-50/50">
                                    <div class="flex flex-col items-center justify-center max-w-md mx-auto">
                                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100/60 text-[#0036DC]">
                                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                            </svg>
                                        </div>
                                        <h3 class="mb-1 text-lg font-bold text-slate-800">No se encontraron mallas curriculares</h3>
                                        <p class="text-sm text-slate-500 mb-6 leading-relaxed">
                                            {{ tieneFiltrosActivos ? 'No hay resultados que coincidan con los filtros seleccionados. Intenta ajustarlos o limpiarlos.' : 'Aún no se han registrado planes de estudio en esta sección.' }}
                                        </p>
                                        <div class="flex items-center gap-3">
                                            <button v-if="tieneFiltrosActivos" @click="limpiar" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50">
                                                Limpiar filtros
                                            </button>
                                            <Link href="/mallas/crear" class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] px-5 py-2.5 text-sm font-bold text-white shadow-md transition-all hover:bg-[#0036DC]">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                                Registrar nueva malla
                                            </Link>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Paginación -->
                <div v-if="mallas.data?.length && mallas.last_page > 1"
                     class="flex flex-wrap items-center justify-between gap-4 mt-6 pt-5 border-t border-slate-100">
                    <p class="text-xs font-medium text-slate-500">
                        Página <span class="font-bold text-slate-800">{{ mallas.current_page }}</span> de <span class="font-bold text-slate-800">{{ mallas.last_page }}</span>
                    </p>
                    <nav class="flex flex-wrap items-center gap-1.5">
                        <template v-for="(link, i) in mallas.links" :key="i">
                            <Link v-if="link.url" :href="link.url" preserve-scroll
                                  :class="link.active ? 'bg-[#00205B] text-white font-bold shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                                  class="min-w-[36px] h-9 flex items-center justify-center rounded-xl px-3 text-xs transition-colors" v-html="link.label" />
                            <span v-else class="min-w-[36px] h-9 flex items-center justify-center rounded-xl px-3 text-xs text-slate-300 bg-slate-50 border border-slate-100 cursor-not-allowed"
                                  v-html="link.label" />
                        </template>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</template>

