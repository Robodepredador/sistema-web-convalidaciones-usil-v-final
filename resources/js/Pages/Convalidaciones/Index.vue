<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({ convalidaciones: Object, preconvalidaciones: Object, filtros: Object, kpis: Object });

// ── Filtros ──
const filtro = reactive({
    q: props.filtros?.q ?? '',
    estado: props.filtros?.estado ?? '',
    desde: props.filtros?.desde ?? '',
    hasta: props.filtros?.hasta ?? '',
});

const aplicar = () => {
    router.get('/convalidaciones', filtro, { preserveState: true, preserveScroll: true, replace: true });
};

const limpiar = () => {
    Object.keys(filtro).forEach((k) => { filtro[k] = ''; });
    router.get('/convalidaciones', {}, { preserveScroll: true, replace: true });
};

// ── Modales ──
const modalConfirmarAbierto = ref(false);
const modalAnularAbierto = ref(false);
const preconvalSeleccionada = ref(null);
const convalSeleccionada = ref(null);
const motivoAnulacion = ref('');

const abrirConfirmar = (p) => {
    preconvalSeleccionada.value = p;
    modalConfirmarAbierto.value = true;
};
const cerrarConfirmar = () => {
    modalConfirmarAbierto.value = false;
    preconvalSeleccionada.value = null;
};
const confirmar = () => {
    if (!preconvalSeleccionada.value) return;
    router.post(`/simulaciones/${preconvalSeleccionada.value.id}/confirmar`, {}, {
        preserveScroll: true,
        onSuccess: () => cerrarConfirmar()
    });
};

const abrirAnular = (c) => {
    convalSeleccionada.value = c;
    motivoAnulacion.value = '';
    modalAnularAbierto.value = true;
};
const cerrarAnular = () => {
    modalAnularAbierto.value = false;
    convalSeleccionada.value = null;
};
const anular = () => {
    if (!convalSeleccionada.value || !motivoAnulacion.value.trim()) return;
    router.post(`/convalidaciones/${convalSeleccionada.value.id}/anular`, { motivo: motivoAnulacion.value }, {
        preserveScroll: true,
        onSuccess: () => cerrarAnular()
    });
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
const memorandum = (id) => descargarArchivo(`/convalidaciones/${id}/memorandum`);

const estados = [
    { value: 'pendiente', label: 'Pendientes' },
    { value: 'confirmada', label: 'Confirmadas' },
    { value: 'anulada', label: 'Anuladas' }
];
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">Convalidaciones</h1>
                <p class="mt-1 text-sm text-slate-500">Gestión de expedientes y resoluciones de convalidación.</p>
            </div>
        </div>

        <!-- ── KPIs ── -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-[#2E75B6]">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800">{{ kpis.pendientes }}</p>
                        <p class="text-xs font-medium text-slate-500">Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800">{{ kpis.confirmadas }}</p>
                        <p class="text-xs font-medium text-slate-500">Confirmadas</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800">{{ kpis.anuladas }}</p>
                        <p class="text-xs font-medium text-slate-500">Anuladas</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800">{{ kpis.creditos_promedio }}</p>
                        <p class="text-xs font-medium text-slate-500">Créditos prom.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Filtros ── -->
        <div class="mb-8 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
                    <input v-model="filtro.q" type="text" placeholder="Estudiante, documento o memo…" @keyup.enter="aplicar"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Estado</label>
                    <select v-model="filtro.estado" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                        <option value="">Todos</option>
                        <option v-for="e in estados" :key="e.value" :value="e.value">{{ e.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Desde</label>
                    <input v-model="filtro.desde" type="date" :max="filtro.hasta || undefined" @keyup.enter="aplicar"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Hasta</label>
                    <input v-model="filtro.hasta" type="date" :min="filtro.desde || undefined" @keyup.enter="aplicar"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button @click="aplicar" class="rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864]">Filtrar</button>
                <button @click="limpiar" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Limpiar</button>
            </div>
        </div>

        <!-- ── Preconvalidaciones Pendientes ── -->
        <section v-if="preconvalidaciones.data.length" class="mb-10">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-widest text-slate-400">Preconvalidaciones pendientes</h2>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Expediente</th>
                                <th class="px-4 py-3 font-semibold">Estudiante</th>
                                <th class="px-4 py-3 font-semibold">Carrera destino</th>
                                <th class="px-4 py-3 font-semibold text-center">Convalidados</th>
                                <th class="px-4 py-3 font-semibold text-center">Créditos</th>
                                <th class="px-4 py-3 font-semibold">Estado</th>
                                <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="p in preconvalidaciones.data" :key="p.id">
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-4 py-3">
                                        <span class="font-medium text-slate-700">#{{ p.id }}</span>
                                        <p class="text-xs text-slate-400">{{ p.fecha }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-800">{{ p.estudiante }}</p>
                                        <p class="text-xs text-slate-400">{{ p.documento }} · {{ p.origen || 'Sin origen' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ p.carrera || '—' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ p.convalidados }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold text-[#2E75B6]">{{ p.creditos.toFixed(1) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Pendiente
                                            </span>
                                            <span class="rounded bg-slate-50 px-1.5 py-0.5 text-[10px] uppercase text-slate-400 border border-slate-200">
                                                {{ p.metodo === 'ia' ? 'IA' : 'Manual' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="abrirConfirmar(p)" class="rounded-md bg-green-600 px-2.5 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-green-700">Confirmar</button>
                                            <div class="flex items-center gap-1 border-l border-slate-200 pl-2">
                                                <button @click="descargarArchivo(p.pdf)" title="Descargar PDF" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-[#2E75B6]">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                                </button>
                                                <button @click="descargarArchivo(p.excel)" title="Descargar Excel" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-green-600">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M12 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5" /></svg>
                                                </button>
                                                <button @click="toggleDetalle('p'+p.id)" class="ml-1 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                                    <svg class="h-4 w-4 transition-transform" :class="detalleAbierto['p'+p.id] ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Detalle cursos -->
                                <tr v-if="detalleAbierto['p'+p.id]" class="bg-slate-50/50">
                                    <td colspan="7" class="p-0">
                                        <div class="border-y border-slate-200 px-6 py-4 shadow-inner">
                                            <p class="mb-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">Cursos convalidados</p>
                                            <div class="rounded-lg border border-slate-200 bg-white">
                                                <table class="min-w-full text-xs">
                                                    <thead class="border-b border-slate-100 bg-slate-50">
                                                        <tr>
                                                            <th class="px-4 py-2 text-left font-medium text-slate-500">Curso origen</th>
                                                            <th class="px-4 py-2 text-left font-medium text-slate-500">Convalida con (USIL)</th>
                                                            <th class="px-4 py-2 text-right font-medium text-slate-500">Créditos</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-50">
                                                        <tr v-for="c in p.cursos" :key="c.usil">
                                                            <td class="px-4 py-2 text-slate-600">{{ c.origen }}</td>
                                                            <td class="px-4 py-2 font-medium text-slate-700"><span class="text-green-500 mr-1">✓</span>{{ c.usil }}</td>
                                                            <td class="px-4 py-2 text-right font-medium text-slate-600">{{ c.creditos.toFixed(1) }}</td>
                                                        </tr>
                                                        <tr v-if="!p.cursos.length"><td colspan="3" class="px-4 py-4 text-center text-slate-400">Sin cursos.</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div v-if="preconvalidaciones.last_page > 1" class="border-t border-slate-200 bg-slate-50 px-4 py-3">
                    <nav class="flex flex-wrap items-center gap-1">
                        <template v-for="(link, i) in preconvalidaciones.links" :key="i">
                            <Link v-if="link.url" :href="link.url" preserve-scroll :class="link.active ? 'bg-[#1F3864] text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50'" class="min-w-[32px] rounded px-2 py-1 text-center text-xs font-medium" v-html="link.label" />
                            <span v-else class="min-w-[32px] rounded border border-slate-200 bg-slate-50 px-2 py-1 text-center text-xs font-medium text-slate-400" v-html="link.label" />
                        </template>
                    </nav>
                </div>
            </div>
        </section>

        <!-- ── Convalidaciones ── -->
        <section>
            <h2 class="mb-3 text-sm font-bold uppercase tracking-widest text-slate-400">Convalidaciones e Historial</h2>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Resolución / Memo</th>
                                <th class="px-4 py-3 font-semibold">Estudiante</th>
                                <th class="px-4 py-3 font-semibold">Carrera destino</th>
                                <th class="px-4 py-3 font-semibold text-center">Cursos</th>
                                <th class="px-4 py-3 font-semibold text-center">Créditos</th>
                                <th class="px-4 py-3 font-semibold">Estado</th>
                                <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="c in convalidaciones.data" :key="c.id">
                                <tr class="hover:bg-slate-50/70" :class="{'bg-red-50/30': c.estado === 'anulada'}">
                                    <td class="px-4 py-3">
                                        <span class="font-medium text-slate-700">{{ c.memorandum || `ID: ${c.id}` }}</span>
                                        <p class="text-xs text-slate-400">{{ c.fecha }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-800" :class="{'line-through opacity-70': c.estado === 'anulada'}">{{ c.estudiante }}</p>
                                        <p class="text-xs text-slate-400">{{ c.documento }} · {{ c.origen || 'Sin origen' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ c.carrera || '—' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ c.convalidados }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold text-[#2E75B6]">{{ c.creditos.toFixed(1) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span v-if="c.estado === 'confirmada'" class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Confirmada
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span> Anulada
                                            </span>
                                            <span v-if="c.estado === 'anulada' && c.motivo_anulacion" class="text-[10px] text-red-500 max-w-[120px] truncate" :title="c.motivo_anulacion">
                                                {{ c.motivo_anulacion }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <button v-if="c.estado === 'confirmada'" @click="abrirAnular(c)" class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 hover:border-red-200">Anular</button>
                                            <div class="flex items-center gap-1 border-l border-slate-200 pl-2">
                                                <button v-if="c.estado === 'confirmada'" @click="memorandum(c.id)" title="Descargar Memorándum Oficial" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-indigo-600">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 1 13.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375M9 15l2.25 2.25L15 12" /></svg>
                                                </button>
                                                <button v-if="c.pdf_preconv" @click="descargarArchivo(c.pdf_preconv)" title="PDF Preconvalidación" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-[#2E75B6]">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                                </button>
                                                <button v-if="c.excel_preconv" @click="descargarArchivo(c.excel_preconv)" title="Excel Preconvalidación" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-green-600">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M12 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5" /></svg>
                                                </button>
                                                <button @click="toggleDetalle('c'+c.id)" class="ml-1 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                                    <svg class="h-4 w-4 transition-transform" :class="detalleAbierto['c'+c.id] ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Detalle cursos -->
                                <tr v-if="detalleAbierto['c'+c.id]" class="bg-slate-50/50">
                                    <td colspan="7" class="p-0">
                                        <div class="border-y border-slate-200 px-6 py-4 shadow-inner">
                                            <p class="mb-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">Cursos convalidados</p>
                                            <div class="rounded-lg border border-slate-200 bg-white">
                                                <table class="min-w-full text-xs">
                                                    <thead class="border-b border-slate-100 bg-slate-50">
                                                        <tr>
                                                            <th class="px-4 py-2 text-left font-medium text-slate-500">Curso origen</th>
                                                            <th class="px-4 py-2 text-left font-medium text-slate-500">Convalida con (USIL)</th>
                                                            <th class="px-4 py-2 text-right font-medium text-slate-500">Créditos</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-50">
                                                        <tr v-for="curso in c.cursos" :key="curso.usil">
                                                            <td class="px-4 py-2 text-slate-600">{{ curso.origen }}</td>
                                                            <td class="px-4 py-2 font-medium text-slate-700"><span class="text-green-500 mr-1">✓</span>{{ curso.usil }}</td>
                                                            <td class="px-4 py-2 text-right font-medium text-slate-600">{{ curso.creditos.toFixed(1) }}</td>
                                                        </tr>
                                                        <tr v-if="!c.cursos.length"><td colspan="3" class="px-4 py-4 text-center text-slate-400">Sin cursos.</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!convalidaciones.data.length">
                                <td colspan="7" class="px-4 py-10 text-center text-slate-400">No se encontraron convalidaciones.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="convalidaciones.last_page > 1" class="border-t border-slate-200 bg-slate-50 px-4 py-3">
                    <nav class="flex flex-wrap items-center gap-1">
                        <template v-for="(link, i) in convalidaciones.links" :key="i">
                            <Link v-if="link.url" :href="link.url" preserve-scroll :class="link.active ? 'bg-[#1F3864] text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50'" class="min-w-[32px] rounded px-2 py-1 text-center text-xs font-medium" v-html="link.label" />
                            <span v-else class="min-w-[32px] rounded border border-slate-200 bg-slate-50 px-2 py-1 text-center text-xs font-medium text-slate-400" v-html="link.label" />
                        </template>
                    </nav>
                </div>
            </div>
        </section>

        <!-- ── Modal Confirmar ── -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="modalConfirmarAbierto" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="cerrarConfirmar"></div>
                    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl text-center">
                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-7 w-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">Confirmar convalidación</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Se emitirá el memorándum oficial para el estudiante <strong>{{ preconvalSeleccionada?.estudiante }}</strong>
                            con un total de <strong>{{ preconvalSeleccionada?.creditos?.toFixed(1) }} créditos</strong> reconocidos.
                        </p>
                        <p class="mt-1 text-xs text-slate-400">Esta acción no se puede deshacer de forma simple.</p>
                        
                        <div class="mt-6 flex gap-3">
                            <button @click="cerrarConfirmar" class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                            <button @click="confirmar" class="flex-1 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700">Sí, confirmar</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal Anular ── -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="modalAnularAbierto" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="cerrarAnular"></div>
                    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl">
                        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                            <h3 class="text-lg font-bold text-slate-800">Anular convalidación</h3>
                            <button @click="cerrarAnular" class="text-slate-400 hover:text-slate-600"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
                        </div>
                        <div class="p-6">
                            <p class="mb-4 text-sm text-slate-600">
                                Estás a punto de anular la convalidación de <strong>{{ convalSeleccionada?.estudiante }}</strong> (Memo: {{ convalSeleccionada?.memorandum }}).
                                Por favor, indica el motivo de la anulación para el registro de auditoría.
                            </p>
                            <textarea v-model="motivoAnulacion" rows="3" placeholder="Ej: Error en el cálculo de créditos, solicitud del estudiante..." 
                                      class="w-full rounded-xl border-slate-300 text-sm focus:border-red-500 focus:ring-red-500"></textarea>
                            
                            <div class="mt-6 flex justify-end gap-3">
                                <button @click="cerrarAnular" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                                <button @click="anular" :disabled="!motivoAnulacion.trim()" 
                                        class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Anular expediente
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.fade-enter-active > div:last-child, .fade-leave-active > div:last-child { transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
.fade-enter-from > div:last-child, .fade-leave-to > div:last-child { transform: scale(0.95); opacity: 0; }
</style>
