<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    dashboard: {
        type: Object,
        default: () => ({}),
    },
});

const fechaHoy = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('es-PE', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const COLOR_MAP = {
    blue: {
        text: 'text-[#1F3864]',
        bg: 'bg-blue-50/80',
        border: 'border-blue-100',
        iconBg: 'bg-[#1F3864] text-white',
        ring: 'ring-blue-400',
    },
    amber: {
        text: 'text-amber-700',
        bg: 'bg-amber-50/80',
        border: 'border-amber-200/80',
        iconBg: 'bg-amber-500 text-white',
        ring: 'ring-amber-400',
    },
    rose: {
        text: 'text-rose-700',
        bg: 'bg-rose-50/80',
        border: 'border-rose-200/80',
        iconBg: 'bg-rose-500 text-white',
        ring: 'ring-rose-400',
    },
    emerald: {
        text: 'text-emerald-700',
        bg: 'bg-emerald-50/80',
        border: 'border-emerald-200/80',
        iconBg: 'bg-emerald-600 text-white',
        ring: 'ring-emerald-400',
    },
    violet: {
        text: 'text-violet-700',
        bg: 'bg-violet-50/80',
        border: 'border-violet-200/80',
        iconBg: 'bg-violet-600 text-white',
        ring: 'ring-violet-400',
    },
    indigo: {
        text: 'text-indigo-700',
        bg: 'bg-indigo-50/80',
        border: 'border-indigo-200/80',
        iconBg: 'bg-indigo-600 text-white',
        ring: 'ring-indigo-400',
    },
    teal: {
        text: 'text-teal-700',
        bg: 'bg-teal-50/80',
        border: 'border-teal-200/80',
        iconBg: 'bg-teal-600 text-white',
        ring: 'ring-teal-400',
    },
    slate: {
        text: 'text-slate-700',
        bg: 'bg-slate-50/80',
        border: 'border-slate-200/80',
        iconBg: 'bg-slate-600 text-white',
        ring: 'ring-slate-400',
    },
};

const ESTADO_BADGE = {
    pendiente: 'bg-amber-50 text-amber-700 border border-amber-200',
    observada: 'bg-rose-50 text-rose-700 border border-rose-200 font-bold',
    aprobada: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    en_evaluacion: 'bg-blue-50 text-blue-700 border border-blue-200',
    borrador: 'bg-slate-100 text-slate-600 border border-slate-200',
};

const getBadge = (estado) => ESTADO_BADGE[estado] ?? 'bg-slate-100 text-slate-600 border border-slate-200';
</script>

<template>
    <div class="w-full pb-12">
        <!-- ======================= HERO HEADER USIL ======================= -->
        <div class="mb-6 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-7 flex flex-col md:flex-row md:items-center md:justify-between gap-5">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 mb-2 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                        <span class="text-[11px] font-bold tracking-wider text-blue-100 uppercase">
                            {{ dashboard.rol || 'Portal de Gestión' }}
                        </span>
                        <span class="text-blue-300">•</span>
                        <span class="text-[11px] text-blue-200 capitalize font-medium">{{ fechaHoy }}</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                        {{ dashboard.saludo || 'Bienvenido al Sistema' }}
                    </h1>
                    <p class="mt-1 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-2xl">
                        {{ dashboard.subtitulo || 'Sistema Integrado de Convalidaciones y Homologación de Mallas USIL' }}
                    </p>
                </div>

                <div class="shrink-0 flex items-center gap-3">
                    <div class="hidden sm:flex flex-col text-right">
                        <span class="text-xs font-bold text-white uppercase tracking-wider">Módulo Académico</span>
                        <span class="text-[11px] text-blue-200">USIL San Ignacio de Loyola</span>
                    </div>
                    <div class="h-10 w-10 rounded-2xl bg-white/15 border border-white/20 flex items-center justify-center text-white font-bold text-sm shadow-inner">
                        U
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= KPIS GRID ======================= -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div v-for="(k, i) in dashboard.kpis || []" :key="i"
                 :class="[
                     'relative overflow-hidden rounded-3xl border bg-white p-5 shadow-xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5',
                     k.destacado && k.valor > 0 ? (COLOR_MAP[k.color]?.border || 'border-amber-300') + ' ring-2 ' + (COLOR_MAP[k.color]?.ring || 'ring-amber-300') : 'border-slate-200/80'
                 ]">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        {{ k.label }}
                    </span>
                    <span v-if="k.destacado && k.valor > 0" class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                    </span>
                </div>
                
                <div class="mt-3 flex items-baseline justify-between">
                    <p class="text-3xl font-black tabular-nums tracking-tight" :class="COLOR_MAP[k.color]?.text || 'text-[#1F3864]'">
                        {{ k.valor }}
                    </p>
                </div>
                <p class="mt-1 text-[11px] text-slate-400 font-medium leading-snug">
                    {{ k.detalle }}
                </p>
            </div>
        </div>

        <!-- ======================= BANDEJA DE TRABAJO PRIORITARIA ======================= -->
        <div class="w-full rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xs">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-[#2E75B6]"></span>
                    <h2 class="text-xs sm:text-sm font-extrabold uppercase tracking-wider text-slate-700">
                        {{ dashboard.bandeja?.titulo_seccion || 'Bandeja de Trabajo' }}
                    </h2>
                </div>
                <span class="text-[11px] font-bold text-slate-400">
                    {{ (dashboard.bandeja?.items || []).length }} registros
                </span>
            </div>

            <!-- Items de la bandeja -->
            <div v-if="(dashboard.bandeja?.items || []).length" class="divide-y divide-slate-100">
                <div v-for="item in dashboard.bandeja.items" :key="item.id"
                     class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/80 p-2.5 rounded-2xl transition-colors">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-xs sm:text-sm font-bold text-slate-900 leading-tight">
                                {{ item.titulo }}
                            </p>
                            <span v-if="item.estado" :class="getBadge(item.estado)" class="rounded-full px-2.5 py-0.5 text-[10px] font-bold capitalize whitespace-nowrap">
                                {{ (item.estado || '').replace('_', ' ') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-1.5">
                            <span class="font-medium text-[#1F3864]">{{ item.subtitulo }}</span>
                            <template v-if="item.origen">
                                <span class="text-slate-300">•</span>
                                <span class="text-slate-500">{{ item.origen }}</span>
                            </template>
                            <template v-if="item.asesor">
                                <span class="text-slate-300">•</span>
                                <span class="text-slate-400">Asesor: {{ item.asesor }}</span>
                            </template>
                            <template v-if="item.fecha">
                                <span class="text-slate-300">•</span>
                                <span class="text-slate-400">{{ item.fecha }}</span>
                            </template>
                        </p>

                        <!-- Observación destacada si aplica -->
                        <div v-if="item.observacion" class="mt-2 rounded-xl bg-rose-50 border border-rose-100 p-2.5 text-xs text-rose-800">
                            <span class="font-bold">Observación:</span> {{ item.observacion }}
                        </div>
                    </div>

                    <!-- Botón de acción directa -->
                    <div v-if="item.accion_url" class="shrink-0 flex items-center sm:self-center self-end">
                        <Link :href="item.accion_url"
                              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-[#1F3864] to-[#2E75B6] hover:from-[#214378] hover:to-[#1F3864] text-xs font-bold text-white shadow-2xs hover:shadow-md transition-all">
                            <span>{{ item.accion_texto || 'Gestionar' }}</span>
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Estado vacío -->
            <div v-else class="py-12 px-4 text-center">
                <div class="mx-auto h-12 w-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <h3 class="text-xs sm:text-sm font-bold text-slate-700">Todo al día</h3>
                <p class="text-xs text-slate-400 mt-0.5">No hay tareas pendientes en tu bandeja en este momento.</p>
            </div>
        </div>
    </div>
</template>
