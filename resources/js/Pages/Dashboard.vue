<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({ dashboard: { type: Object, default: () => ({}) } });

const COLOR = {
    blue: 'text-[#1F3864]', indigo: 'text-indigo-600', amber: 'text-amber-600',
    green: 'text-green-600', violet: 'text-violet-600', orange: 'text-orange-600',
    teal: 'text-teal-600', slate: 'text-slate-700',
};
const col = (c) => COLOR[c] ?? 'text-[#1F3864]';

const ESTADO = {
    pendiente: 'bg-amber-100 text-amber-700', asignada: 'bg-indigo-100 text-indigo-700',
    en_revision: 'bg-blue-100 text-blue-700', observada: 'bg-orange-100 text-orange-700',
    devuelta: 'bg-red-100 text-red-700', aprobada: 'bg-green-100 text-green-700',
};
const badge = (e) => ESTADO[e] ?? 'bg-slate-100 text-slate-600';
const kpis = () => props.dashboard.kpis ?? [];
const bandeja = () => props.dashboard.bandeja ?? [];
const acciones = () => props.dashboard.acciones ?? [];
</script>

<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-[#1F3864]">{{ dashboard.saludo || 'Panel principal' }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                Sistema de Convalidaciones USIL<span v-if="dashboard.rol"> · <span class="font-medium text-slate-600">{{ dashboard.rol }}</span></span>
            </p>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div v-for="(k, i) in kpis()" :key="i" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ k.label }}</p>
                <p class="mt-1 text-3xl font-bold" :class="col(k.color)">{{ k.valor }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <!-- Bandeja de pendientes -->
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Bandeja de pendientes</h2>
                <ul v-if="bandeja().length" class="divide-y divide-slate-100">
                    <li v-for="(b, i) in bandeja()" :key="i" class="flex items-center justify-between py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-700">{{ b.titulo }}</p>
                            <p class="truncate text-xs text-slate-400">{{ b.subtitulo }}</p>
                            <p v-if="b.fecha" class="mt-0.5 text-xs text-slate-400">
                                <span class="text-slate-300">Ingresó:</span> {{ b.fecha }}
                            </p>
                        </div>
                        <span :class="badge(b.estado)" class="ml-3 shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                            {{ (b.estado || '').replace('_', ' ') }}
                        </span>
                    </li>
                </ul>
                <p v-else class="py-6 text-center text-sm text-slate-400">No hay pendientes en tu bandeja.</p>
            </div>

            <!-- Acciones rápidas -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Acciones</h2>
                <div class="flex flex-col gap-2">
                    <Link v-for="a in acciones()" :key="a.href" :href="a.href"
                          class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:border-[#2E75B6] hover:bg-slate-50">
                        {{ a.label }}
                        <span class="text-slate-300">→</span>
                    </Link>
                    <p v-if="!acciones().length" class="py-4 text-center text-sm text-slate-400">Sin acciones disponibles.</p>
                </div>
            </div>
        </div>
    </div>
</template>
