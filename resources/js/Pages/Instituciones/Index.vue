<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';

const props = defineProps({
    instituciones: Object,
    institucionesActivas: { type: Number, default: 0 },
    tipos: { type: Array, default: () => [] },
    paises: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
});

const filtro = reactive({
    buscar: props.filtros?.buscar ?? '',
    tipo_id: props.filtros?.tipo_id ?? '',
    gestion: props.filtros?.gestion ?? '',
    licenciamiento: props.filtros?.licenciamiento ?? '',
    pais: props.filtros?.pais ?? '',
    estado: props.filtros?.estado ?? '',
});

const tieneFiltrosActivos = computed(() =>
    Boolean(filtro.buscar || filtro.tipo_id || filtro.gestion || filtro.licenciamiento || filtro.pais || filtro.estado));

const aplicar = () => router.get('/instituciones', filtro, { preserveState: true, preserveScroll: true, replace: true });

const limpiar = () => {
    filtro.buscar = '';
    filtro.tipo_id = '';
    filtro.gestion = '';
    filtro.licenciamiento = '';
    filtro.pais = '';
    filtro.estado = '';
    router.get('/instituciones', {}, { preserveScroll: true, replace: true });
};

const licenciaBadge = (l) =>
    l === 'licenciada'
        ? { label: 'SUNEDU Licenciada', clase: 'bg-emerald-50 text-emerald-700 ring-emerald-200' }
        : l === 'no_licenciada'
            ? { label: 'No licenciada', clase: 'bg-rose-50 text-rose-700 ring-rose-200' }
            : { label: 'Sin verificar', clase: 'bg-slate-100 text-slate-500 ring-slate-200' };

const gestionBadge = (g) =>
    g === 'publica'
        ? { label: 'Pública', clase: 'bg-indigo-50 text-indigo-700 ring-indigo-200' }
        : g === 'privada'
            ? { label: 'Privada', clase: 'bg-amber-50 text-amber-700 ring-amber-200' }
            : { label: '—', clase: 'bg-slate-100 text-slate-500 ring-slate-200' };

const desactivar = (i) => {
    if (confirm(`¿Desactivar la institución "${i.nombre}"?`)) {
        router.delete(`/instituciones/${i.id}`, { preserveScroll: true });
    }
};
const activar = (i) => router.patch(`/instituciones/${i.id}/activar`, {}, { preserveScroll: true });
</script>

<template>
    <div class="max-w-7xl mx-auto pb-12">
        <!-- Banner Header Hero -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] shadow-xl">
            <!-- Decorative background elements -->
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-25 rounded-full blur-2xl"></div>
            <div class="absolute -bottom-10 -left-10 w-48 h-48 bg-white opacity-5 rounded-full blur-xl"></div>
            
            <div class="relative z-10 px-8 py-10 sm:p-12 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-8">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 mb-4 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-sm">
                        <svg class="w-4 h-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Gestión Académica · USIL</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight leading-tight">
                        Instituciones Externas
                    </h1>
                    <p class="mt-3 text-base text-blue-100/90 leading-relaxed max-w-xl">
                        Registro y control de universidades e institutos de procedencia, licenciamiento SUNEDU y sus carreras oficiales para convalidaciones.
                    </p>
                </div>

                <div class="flex items-center gap-3 w-full lg:w-auto">
                    <Link href="/instituciones/crear"
                          class="group relative inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-bold text-[#1F3864] shadow-lg transition-all duration-300 hover:scale-105 hover:bg-blue-50 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-white/30">
                        <svg class="w-5 h-5 transition-transform duration-300 group-hover:rotate-90 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Nueva institución
                    </Link>
                </div>
            </div>
        </div>

        <!-- KPIs y Panel de Filtros -->
        <div class="mb-8 grid gap-5 lg:grid-cols-12 items-stretch">
            <!-- Filtros Modernos (9 cols) -->
            <div class="lg:col-span-9 bg-white rounded-3xl border border-slate-200/70 p-6 shadow-sm relative z-20">
                <div class="flex items-center justify-between gap-4 mb-4 pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#2E75B6] flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                            </svg>
                        </div>
                        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Filtros de Búsqueda</h2>
                    </div>
                    <div v-if="tieneFiltrosActivos" class="flex items-center gap-1.5">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-[#2E75B6]">
                            Filtros aplicados
                        </span>
                    </div>
                </div>

                <!-- Búsqueda principal por texto -->
                <div class="mb-4">
                    <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        Nombre de la institución
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </span>
                        <input v-model="filtro.buscar" @keyup.enter="aplicar" type="search"
                               placeholder="Ej. Pontificia Universidad Católica, Senati, UPC…"
                               class="w-full rounded-xl border-slate-300 pl-9 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6] transition-colors" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            Tipo
                        </label>
                        <select v-model="filtro.tipo_id"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6] transition-colors">
                            <option value="">Todos los tipos</option>
                            <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-.778.099-1.533.284-2.253" /></svg>
                            Gestión
                        </label>
                        <select v-model="filtro.gestion"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6] transition-colors">
                            <option value="">Todas</option>
                            <option value="publica">Pública</option>
                            <option value="privada">Privada</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            País
                        </label>
                        <select v-model="filtro.pais"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6] transition-colors">
                            <option value="">Todos los países</option>
                            <option v-for="p in paises" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Estado
                        </label>
                        <select v-model="filtro.estado"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6] transition-colors">
                            <option value="">Todos</option>
                            <option value="activa">Activas</option>
                            <option value="inactiva">Inactivas</option>
                        </select>
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
                            class="inline-flex items-center gap-2 rounded-xl bg-[#2E75B6] px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#1F3864] transition-all duration-200 hover:shadow">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        Filtrar instituciones
                    </button>
                </div>
            </div>

            <!-- Tarjeta Resumen KPI (3 cols) -->
            <div class="lg:col-span-3 flex flex-col">
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] p-6 text-white shadow-md h-full flex flex-col justify-between">
                    <div class="absolute -right-6 -bottom-6 w-28 h-28 bg-white/10 rounded-full blur-xl"></div>
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-200">Instituciones Activas</span>
                            <span class="flex h-3 w-3 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl sm:text-5xl font-black tracking-tight text-white">{{ institucionesActivas }}</span>
                            <span class="text-xs text-blue-200 font-medium">registradas</span>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-blue-100/80 leading-relaxed border-t border-white/10 pt-3">
                        Centros de educación superior habilitados para trámites de convalidación académica.
                    </p>
                </div>
            </div>
        </div>

        <!-- Tabla / Listado de Instituciones en Card -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200/70 overflow-hidden relative">
            <div class="px-6 py-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Instituciones Registradas</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Catálogo maestro de procedencia para traslados y homologaciones.</p>
                </div>
                <div class="text-xs text-slate-400 font-medium">
                    Mostrando {{ instituciones.from || 0 }}–{{ instituciones.to || 0 }} de {{ instituciones.total || 0 }} registros
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th scope="col" class="px-6 py-3.5">Institución Externa</th>
                            <th scope="col" class="px-6 py-3.5">Tipo</th>
                            <th scope="col" class="px-6 py-3.5">Gestión & SUNEDU</th>
                            <th scope="col" class="px-6 py-3.5">País</th>
                            <th scope="col" class="px-6 py-3.5 text-center">Carreras</th>
                            <th scope="col" class="px-6 py-3.5">Estado</th>
                            <th scope="col" class="px-6 py-3.5 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="i in instituciones.data" :key="i.id" class="hover:bg-slate-50/80 transition-colors group">
                            <!-- Nombre -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-2xl bg-blue-50/80 border border-blue-100 flex items-center justify-center text-[#2E75B6] shrink-0 group-hover:scale-105 transition-transform">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <Link :href="`/instituciones/${i.id}/editar`" class="font-bold text-slate-800 hover:text-[#2E75B6] transition-colors leading-snug block">
                                            {{ i.nombre }}
                                        </Link>
                                        <span class="text-xs text-slate-400 font-mono">ID #{{ i.id }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Tipo -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-200">
                                    {{ i.tipo || 'Sin tipo' }}
                                </span>
                            </td>

                            <!-- Gestión & Licenciamiento -->
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span :class="gestionBadge(i.gestion).clase"
                                          class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset">
                                        {{ gestionBadge(i.gestion).label }}
                                    </span>
                                    <span :class="licenciaBadge(i.licenciamiento).clase"
                                          class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset">
                                        {{ licenciaBadge(i.licenciamiento).label }}
                                    </span>
                                </div>
                            </td>

                            <!-- País -->
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600 text-xs font-medium">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {{ i.pais ?? '—' }}
                                </div>
                            </td>

                            <!-- Carreras -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold bg-slate-100 text-slate-700">
                                    {{ i.carreras_count }}
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="i.activa ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200'"
                                      class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold ring-1 ring-inset">
                                    <span :class="i.activa ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'" class="w-1.5 h-1.5 rounded-full"></span>
                                    {{ i.activa ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/instituciones/${i.id}/editar`"
                                          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-[#2E75B6] hover:text-white font-bold transition-all shadow-2xs"
                                          title="Editar institución y carreras">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                        </svg>
                                        Editar
                                    </Link>

                                    <button v-if="i.activa" @click="desactivar(i)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-red-200 text-red-600 hover:bg-red-50 hover:border-red-300 font-bold transition-colors shadow-2xs"
                                            title="Desactivar">
                                        Desactivar
                                    </button>
                                    <button v-else @click="activar(i)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-emerald-200 text-emerald-700 hover:bg-emerald-50 hover:border-emerald-300 font-bold transition-colors shadow-2xs"
                                            title="Activar">
                                        Activar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!instituciones.data?.length">
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="w-16 h-16 rounded-full bg-blue-50 text-[#2E75B6] flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-slate-800">No se encontraron instituciones</h3>
                                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">No hay registros que coincidan con los filtros aplicados. Intenta restablecer los filtros de búsqueda.</p>
                                <button v-if="tieneFiltrosActivos" @click="limpiar"
                                        class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                                    Restablecer filtros
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación Moderna -->
            <div v-if="instituciones.data?.length" class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 px-6 py-4 bg-slate-50/50">
                <p class="text-xs text-slate-500 font-medium">
                    Mostrando <span class="font-bold text-slate-800">{{ instituciones.from }}</span> a <span class="font-bold text-slate-800">{{ instituciones.to }}</span> de <span class="font-bold text-slate-800">{{ instituciones.total }}</span> instituciones
                </p>

                <nav v-if="instituciones.last_page > 1" class="flex flex-wrap items-center gap-1.5">
                    <template v-for="(link, idx) in instituciones.links" :key="idx">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#1F3864] text-white font-bold shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 hover:border-slate-300 font-medium'"
                              class="min-w-[36px] h-9 rounded-xl px-3 flex items-center justify-center text-xs transition-all"
                              v-html="link.label" />
                        <span v-else class="min-w-[36px] h-9 rounded-xl px-3 flex items-center justify-center text-xs text-slate-300 bg-white border border-slate-100 cursor-not-allowed select-none"
                              v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>

