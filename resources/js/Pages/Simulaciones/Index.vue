<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    postulantes: Object,
    carreras: Array,
    filtros: Object,
    ia: Object,
});

const filtro = reactive({
    q: props.filtros?.q ?? '',
    carrera_destino_id: props.filtros?.carrera_destino_id ?? '',
    desde: props.filtros?.desde ?? '',
    hasta: props.filtros?.hasta ?? '',
});
const carrerasOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));

const aplicar = () => router.get('/simulaciones', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => {
    Object.keys(filtro).forEach((k) => { filtro[k] = ''; });
    router.get('/simulaciones', {}, { preserveScroll: true, replace: true });
};
</script>

<template>
    <div>
        <!-- Encabezado -->
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">Simulaciones de convalidación</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Elige un postulante para simular su convalidación de cursos —
                    <span class="font-medium text-slate-600">manual</span> o
                    <span class="font-medium text-violet-600">con IA</span>.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <Link href="/simulaciones/historico"
                      class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                    Histórico de equivalencias
                </Link>
                <span v-if="ia?.disponible"
                      class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-3 py-1 font-medium text-violet-700 ring-1 ring-inset ring-violet-200">
                    ✨ IA activa ({{ ia.proveedor }})
                </span>
                <span v-else
                      class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-500 ring-1 ring-inset ring-slate-200"
                      title="Configura GEMINI_API_KEY en .env para habilitar la IA">
                    IA inactiva · modo similitud
                </span>
            </div>
        </div>

        <!-- Filtros -->
        <div class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Buscar postulante</label>
                    <input v-model="filtro.q" @keyup.enter="aplicar" type="search" placeholder="Nombre, apellido o documento"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Carrera destino (USIL)</label>
                    <Autocomplete v-model="filtro.carrera_destino_id" :options="carrerasOpts" placeholder="Todas las carreras" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Solicitado desde</label>
                    <input v-model="filtro.desde" type="date" :max="filtro.hasta || undefined" @keyup.enter="aplicar"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Solicitado hasta</label>
                    <input v-model="filtro.hasta" type="date" :min="filtro.desde || undefined" @keyup.enter="aplicar"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button @click="aplicar" class="rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864]">Filtrar</button>
                <button @click="limpiar" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Limpiar</button>
            </div>
        </div>

        <!-- Tabla de postulantes -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Postulante</th>
                            <th class="px-4 py-3 font-semibold">Documento</th>
                            <th class="px-4 py-3 font-semibold">Institución de origen</th>
                            <th class="px-4 py-3 font-semibold">Carrera destino</th>
                            <th class="px-4 py-3 font-semibold whitespace-nowrap">Solicitado</th>
                            <th class="px-4 py-3 text-center font-semibold">Simulaciones</th>
                            <th class="px-4 py-3 text-right font-semibold">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in postulantes.data" :key="p.destino_id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800">{{ p.nombre }}</div>
                                <div class="text-xs text-slate-400">{{ p.codigo }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ p.documento }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ p.institucion || '—' }}
                                <div class="text-xs text-slate-400">{{ p.carrera_externa }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ p.carrera_destino || '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs tabular-nums text-slate-500">{{ p.solicitado || '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                    {{ p.simulaciones_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/simulaciones/simular/${p.id}?carrera=${p.carrera_destino_id}`"
                                      class="inline-flex items-center gap-1 rounded-md bg-[#1F3864] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#2E75B6]">
                                    Simular →
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!postulantes.data.length">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                No hay postulantes. Registra postulantes para iniciar simulaciones.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="postulantes.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">
                    Mostrando <span class="font-medium text-slate-700">{{ postulantes.from }}</span>–<span class="font-medium text-slate-700">{{ postulantes.to }}</span>
                    de <span class="font-medium text-slate-700">{{ postulantes.total }}</span> postulantes
                </p>
                <nav v-if="postulantes.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, idx) in postulantes.links" :key="idx">
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
