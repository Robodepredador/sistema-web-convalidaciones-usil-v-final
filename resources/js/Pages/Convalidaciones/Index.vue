<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({ simulaciones: Object, filtros: Object, kpis: Object });

// ── Filtros ──
const filtro = reactive({
    q: props.filtros?.q ?? '',
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
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">Historial de Simulaciones</h1>
                <p class="mt-1 text-sm text-slate-500">Visualiza y descarga los resultados de las simulaciones realizadas.</p>
            </div>
        </div>

        <!-- ── KPIs ── -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-[#2E75B6]">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800">{{ kpis.total_simulaciones }}</p>
                        <p class="text-xs font-medium text-slate-500">Simulaciones Totales</p>
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
                        <p class="text-xs font-medium text-slate-500">Créditos Promedio</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Filtros ── -->
        <div class="mb-8 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
                    <input v-model="filtro.q" type="text" placeholder="Estudiante, documento o memo…" @keyup.enter="aplicar"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
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

        <!-- ── Historial de Simulaciones ── -->
        <section>
            <h2 class="mb-3 text-sm font-bold uppercase tracking-widest text-slate-400">Resultados de Simulación</h2>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Simulación ID</th>
                                <th class="px-4 py-3 font-semibold">Estudiante</th>
                                <th class="px-4 py-3 font-semibold">Carrera destino</th>
                                <th class="px-4 py-3 font-semibold text-center">Cursos</th>
                                <th class="px-4 py-3 font-semibold text-center">Créditos</th>
                                <th class="px-4 py-3 font-semibold">Estado</th>
                                <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="c in simulaciones.data" :key="c.id">
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-4 py-3">
                                        <span class="font-medium text-slate-700">#{{ c.id }}</span>
                                        <p class="text-xs text-slate-400">{{ c.fecha }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-800">{{ c.estudiante }}</p>
                                        <p class="text-xs text-slate-400">{{ c.documento }} · {{ c.origen || 'Sin origen' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ c.carrera || '—' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ c.convalidados }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold text-[#2E75B6]">{{ c.creditos.toFixed(1) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                  :class="c.estado === 'aceptada' ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' : 'bg-slate-100 text-slate-600'">
                                                <span class="h-1.5 w-1.5 rounded-full" :class="c.estado === 'aceptada' ? 'bg-green-500' : 'bg-slate-400'"></span> 
                                                {{ c.estado === 'aceptada' ? 'Validada' : 'Generada' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="flex items-center gap-1">
                                                <button v-if="c.pdf_preconv" @click="descargarArchivo(c.pdf_preconv)" title="Descargar PDF de Simulación" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-blue-600">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v11.25m0 0l-3-3m3 3l3-3M4.5 19.5h15" /></svg>
                                                </button>
                                                <button v-if="c.excel_preconv" @click="descargarArchivo(c.excel_preconv)" title="Descargar Excel de Simulación" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-green-600">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M12 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5" /></svg>
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
                                            <p class="mb-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">Cursos Simulados</p>
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
                            <tr v-if="!simulaciones.data.length">
                                <td colspan="7" class="px-4 py-10 text-center text-slate-400">No se encontraron simulaciones.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="simulaciones.last_page > 1" class="border-t border-slate-200 bg-slate-50 px-4 py-3">
                    <nav class="flex flex-wrap items-center gap-1">
                        <template v-for="(link, i) in simulaciones.links" :key="i">
                            <Link v-if="link.url" :href="link.url" preserve-scroll :class="link.active ? 'bg-[#1F3864] text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50'" class="min-w-[32px] rounded px-2 py-1 text-center text-xs font-medium" v-html="link.label" />
                            <span v-else class="min-w-[32px] rounded border border-slate-200 bg-slate-50 px-2 py-1 text-center text-xs font-medium text-slate-400" v-html="link.label" />
                        </template>
                    </nav>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.fade-enter-active > div:last-child, .fade-leave-active > div:last-child { transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
.fade-enter-from > div:last-child, .fade-leave-to > div:last-child { transform: scale(0.95); opacity: 0; }
</style>
