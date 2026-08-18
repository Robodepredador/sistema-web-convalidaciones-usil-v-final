<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';
import VolverA from '../../../Components/VolverA.vue';

const props = defineProps({ modalidades: Object, activas: Number, filtros: Object });

const filtro = reactive({
    q: props.filtros?.q ?? '',
    estado: props.filtros?.estado ?? '',
});

const hayFiltrosActivos = computed(() => Boolean(filtro.q || filtro.estado));

const aplicar = () => router.get('/estructura/modalidades', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => {
    filtro.q = '';
    filtro.estado = '';
    router.get('/estructura/modalidades', {}, { preserveScroll: true, replace: true });
};
const cambiarEstado = (m) => router.patch(`/estructura/modalidades/${m.id}/estado`, {}, { preserveScroll: true });
const eliminar = (m) => {
    if (confirm(`¿Eliminar la modalidad "${m.nombre}"?`)) {
        router.delete(`/estructura/modalidades/${m.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="w-full pb-16">
        <VolverA href="/estructura" texto="Volver a Estructura Institucional" class="mb-4" />

        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                            </svg>
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Estructura Institucional · Nivel 4</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Modalidades de Estudio
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                            Gestiona las modalidades formativas habilitadas (Presencial, Semipresencial, A Distancia/Virtual) según la normativa universitaria.
                        </p>
                    </div>

                    <!-- Botón Crear -->
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <Link href="/estructura/modalidades/crear"
                              class="inline-flex items-center gap-2 rounded-2xl bg-white hover:bg-slate-50 px-5 py-3 text-xs font-bold text-[#1F3864] shadow-md transition-all duration-300 hover:shadow-lg hover:scale-[1.02]">
                            <svg class="h-4 w-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Nueva modalidad</span>
                        </Link>
                    </div>
                </div>

                <!-- KPIs en Header -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-6">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-200 uppercase tracking-wider">Modalidades Totales</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ modalidades.total || 0 }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Formatos registrados</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-200 uppercase tracking-wider">Modalidades Activas</div>
                        <div class="text-2xl font-extrabold text-emerald-300 mt-1">{{ activas }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Disponibles para asignación</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= FILTROS ======================= -->
        <div class="mb-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Buscar modalidad</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                        <input v-model="filtro.q" type="text" placeholder="Código o nombre de modalidad…" @keyup.enter="aplicar"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-10 pr-4 py-2.5 text-xs text-slate-800 focus:border-[#2E75B6] focus:bg-white focus:outline-hidden focus:ring-3 focus:ring-blue-100 transition-all duration-200" />
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Estado operativo</label>
                    <select v-model="filtro.estado" @change="aplicar"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-800 focus:border-[#2E75B6] focus:bg-white focus:outline-hidden focus:ring-3 focus:ring-blue-100 transition-all duration-200">
                        <option value="">Todos los estados</option>
                        <option value="activo">Solo Activas</option>
                        <option value="inactivo">Solo Inactivas</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                <div class="flex items-center gap-2">
                    <button @click="aplicar" class="inline-flex items-center gap-2 rounded-xl bg-[#1F3864] hover:bg-[#2E75B6] px-4 py-2 text-xs font-bold text-white shadow-xs transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        Filtrar modalidades
                    </button>
                    <button v-if="hayFiltrosActivos" @click="limpiar" class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 transition-colors">
                        Limpiar filtros
                    </button>
                </div>
                <span class="text-xs text-slate-400 font-medium">Mostrando {{ modalidades.data.length }} de {{ modalidades.total }} registros</span>
            </div>
        </div>

        <!-- ======================= TABLA DE MODALIDADES ======================= -->
        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50/80 text-left uppercase tracking-wider text-slate-500 font-bold">
                        <tr>
                            <th class="px-5 py-4">Código</th>
                            <th class="px-5 py-4">Nombre de Modalidad</th>
                            <th class="px-5 py-4 text-center">Estado</th>
                            <th class="px-5 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="m in modalidades.data" :key="m.id" class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-5 py-4 font-mono font-bold text-slate-700">
                                <span class="rounded-lg bg-slate-100 px-2 py-1 text-slate-700 border border-slate-200/70">{{ m.codigo }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="font-bold text-slate-900 text-sm block">{{ m.nombre }}</span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span :class="m.activo ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200'"
                                      class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold">
                                    <span :class="m.activo ? 'bg-emerald-500' : 'bg-slate-400'" class="h-1.5 w-1.5 rounded-full"></span>
                                    {{ m.activo ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/estructura/modalidades/${m.id}/editar`"
                                          class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-[#2E75B6] shadow-2xs hover:border-[#2E75B6] hover:bg-blue-50/50 transition-all">
                                        Editar
                                    </Link>
                                    <button @click="cambiarEstado(m)"
                                            class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-2xs hover:bg-slate-50 transition-all">
                                        {{ m.activo ? 'Inactivar' : 'Activar' }}
                                    </button>
                                    <button @click="eliminar(m)"
                                            class="rounded-xl border border-rose-200 bg-rose-50/50 px-3 py-1.5 text-xs font-bold text-rose-600 shadow-2xs hover:bg-rose-100 transition-all">
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!modalidades.data.length">
                            <td colspan="4" class="px-5 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="h-10 w-10 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                    </svg>
                                    <p class="text-sm font-semibold text-slate-600">No se encontraron modalidades</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Intenta modificar los filtros de búsqueda.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="modalidades.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-6 py-4 bg-slate-50/50">
                <p class="text-xs text-slate-500 font-medium">Mostrando {{ modalidades.from }}–{{ modalidades.to }} de {{ modalidades.total }} modalidades</p>
                <nav v-if="modalidades.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, i) in modalidades.links" :key="i">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#1F3864] text-white shadow-xs font-bold' : 'text-slate-600 hover:bg-white bg-slate-100/70'"
                              class="min-w-[32px] rounded-lg px-2.5 py-1.5 text-center text-xs transition-colors" v-html="link.label" />
                        <span v-else class="min-w-[32px] rounded-lg px-2.5 py-1.5 text-center text-xs text-slate-300" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
