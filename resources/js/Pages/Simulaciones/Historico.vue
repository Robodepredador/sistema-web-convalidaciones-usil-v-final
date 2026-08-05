<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

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
});

const carrerasOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));
const institucionesOpts = computed(() => props.instituciones.map((i) => ({ value: i.id, label: i.nombre })));

const aplicar = () => router.get('/simulaciones/historico', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => {
    Object.keys(filtro).forEach((k) => { filtro[k] = ''; });
    router.get('/simulaciones/historico', {}, { preserveScroll: true, replace: true });
};

// La descarga respeta los filtros aplicados: se lleva impreso lo que se está viendo.
const urlExcel = computed(() => {
    const p = new URLSearchParams(Object.entries(filtro).filter(([, v]) => v !== '' && v != null));
    const qs = p.toString();
    return '/simulaciones/historico/excel' + (qs ? `?${qs}` : '');
});

// Sin filtros y sin filas es la base recién estrenada, no una búsqueda sin resultados.
const sinFiltros = computed(() => !filtro.q && !filtro.institucion_id && !filtro.carrera_usil_id);
</script>

<template>
    <div>
        <!-- Encabezado -->
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link href="/simulaciones" class="text-xs font-medium uppercase tracking-wide text-slate-400 hover:text-[#2E75B6]">← Simulaciones</Link>
                <h1 class="mt-2 text-2xl font-semibold text-[#1F3864]">Histórico de equivalencias</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">
                    Qué curso externo se convalidó con qué curso USIL en expedientes anteriores, y cuántas veces.
                    Es material de referencia para decidir: el sistema no propone nada.
                </p>
            </div>
            <a :href="urlExcel"
               class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Descargar Excel
            </a>
        </div>

        <!-- Filtros -->
        <div class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Buscar curso</label>
                    <input v-model="filtro.q" @keyup.enter="aplicar" type="search" placeholder="Nombre de origen o de la malla USIL"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Institución de origen</label>
                    <Autocomplete v-model="filtro.institucion_id" :options="institucionesOpts" placeholder="Todas las instituciones" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Carrera destino (USIL)</label>
                    <Autocomplete v-model="filtro.carrera_usil_id" :options="carrerasOpts" placeholder="Todas las carreras" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button @click="aplicar" class="rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864]">Filtrar</button>
                <button @click="limpiar" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Limpiar</button>
            </div>
        </div>

        <!-- Tabla -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Curso de origen</th>
                            <th class="px-4 py-3 font-semibold">Procedencia</th>
                            <th class="px-4 py-3 font-semibold">Convalidado con (USIL)</th>
                            <th class="px-4 py-3 font-semibold">Carrera destino</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Veces</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Con memorándum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(f, i) in filas.data" :key="i" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ f.origen_nombre }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ f.institucion }}
                                <div class="text-xs text-slate-400">{{ f.carrera_externa }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ f.curso_usil }}
                                <div class="font-mono text-[11px] text-slate-400">{{ f.codigo_usil }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ f.carrera_usil }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ f.veces }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="f.confirmadas"
                                      class="inline-block rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                    {{ f.confirmadas }}
                                </span>
                                <span v-else class="text-xs text-slate-300">—</span>
                            </td>
                        </tr>
                        <tr v-if="!filas.data.length">
                            <td colspan="6" class="px-4 py-12 text-center">
                                <p v-if="sinFiltros" class="text-slate-500">Todavía no hay equivalencias registradas.</p>
                                <p v-else class="text-slate-500">Ningún registro coincide con los filtros.</p>
                                <p v-if="sinFiltros" class="mx-auto mt-2 max-w-lg text-xs text-slate-400">
                                    Esta pantalla se alimenta sola de las simulaciones que guarde el equipo.
                                    Cada expediente evaluado deja aquí sus equivalencias, sin trabajo adicional.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="filas.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">
                    Mostrando <span class="font-medium text-slate-700">{{ filas.from }}</span>–<span class="font-medium text-slate-700">{{ filas.to }}</span>
                    de <span class="font-medium text-slate-700">{{ filas.total }}</span> equivalencias
                </p>
                <nav v-if="filas.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, idx) in filas.links" :key="idx">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-100'"
                              class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm" v-html="link.label" />
                        <span v-else class="min-w-[34px] cursor-not-allowed rounded-md px-2.5 py-1.5 text-center text-sm text-slate-300" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
