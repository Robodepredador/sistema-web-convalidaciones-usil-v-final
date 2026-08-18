<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../../Components/Autocomplete.vue';
import VolverA from '../../../Components/VolverA.vue';

const props = defineProps({ programas: Object, activos: Number, facultades: Array, filtros: Object });
const facultadesOpts = computed(() => props.facultades.map((f) => ({ value: f.id, label: f.nombre })));

const filtro = reactive({
    q: props.filtros?.q ?? '',
    facultad_id: props.filtros?.facultad_id ?? '',
    estado: props.filtros?.estado ?? '',
});

const hayFiltrosActivos = computed(() => Boolean(filtro.q || filtro.facultad_id || filtro.estado));

const aplicar = () => router.get('/estructura/programas', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => {
    filtro.q = '';
    filtro.facultad_id = '';
    filtro.estado = '';
    router.get('/estructura/programas', {}, { preserveScroll: true, replace: true });
};
const cambiarEstado = (p) => router.patch(`/estructura/programas/${p.id}/estado`, {}, { preserveScroll: true });
const eliminar = (p) => {
    if (confirm(`¿Eliminar el programa académico "${p.nombre}"?`)) {
        router.delete(`/estructura/programas/${p.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="w-full pb-16">
        <VolverA href="/estructura" texto="Volver a Estructura Institucional" class="mb-4" />

        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#0036DC] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Estructura Institucional · Nivel 3</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Programas Académicos (Carreras)
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                            Administra el catálogo oficial de carreras profesionales de pregrado y programas formativos habilitados para convalidaciones.
                        </p>
                    </div>

                    <!-- Botón Crear -->
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <Link href="/estructura/programas/crear"
                              class="inline-flex items-center gap-2 rounded-2xl bg-white hover:bg-slate-50 px-5 py-3 text-xs font-bold text-[#00205B] shadow-md transition-all duration-300 hover:shadow-lg hover:scale-[1.02]">
                            <svg class="h-4 w-4 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Nuevo programa</span>
                        </Link>
                    </div>
                </div>

                <!-- KPIs en Header -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-6">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-200 uppercase tracking-wider">Programas Totales</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ programas.total || 0 }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Carreras en la institución</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-200 uppercase tracking-wider">Programas Activos</div>
                        <div class="text-2xl font-extrabold text-emerald-300 mt-1">{{ activos }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Disponibles para convalidación</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= FILTROS ======================= -->
        <div class="mb-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Buscar carrera</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                        <input v-model="filtro.q" type="text" placeholder="Código o nombre de carrera…" @keyup.enter="aplicar"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-10 pr-4 py-2.5 text-xs text-slate-800 focus:border-[#0036DC] focus:bg-white focus:outline-hidden focus:ring-3 focus:ring-[#0036DC]/20 transition-all duration-200" />
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Filtrar por facultad</label>
                    <Autocomplete v-model="filtro.facultad_id" :options="facultadesOpts" placeholder="Todas las facultades" />
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Estado operativo</label>
                    <select v-model="filtro.estado" @change="aplicar"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-800 focus:border-[#0036DC] focus:bg-white focus:outline-hidden focus:ring-3 focus:ring-[#0036DC]/20 transition-all duration-200">
                        <option value="">Todos los estados</option>
                        <option value="activo">Solo Activos</option>
                        <option value="inactivo">Solo Inactivos</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                <div class="flex items-center gap-2">
                    <button @click="aplicar" class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] px-4 py-2 text-xs font-bold text-white shadow-xs transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        Filtrar programas
                    </button>
                    <button v-if="hayFiltrosActivos" @click="limpiar" class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 transition-colors">
                        Limpiar filtros
                    </button>
                </div>
                <span class="text-xs text-slate-400 font-medium">Mostrando {{ programas.data.length }} de {{ programas.total }} registros</span>
            </div>
        </div>

        <!-- ======================= TABLA DE PROGRAMAS ======================= -->
        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50/80 text-left uppercase tracking-wider text-slate-500 font-bold">
                        <tr>
                            <th class="px-5 py-4">Código</th>
                            <th class="px-5 py-4">Facultad</th>
                            <th class="px-5 py-4">Programa / Carrera</th>
                            <th class="px-5 py-4 text-center">Mallas Activas</th>
                            <th class="px-5 py-4 text-center">Estado</th>
                            <th class="px-5 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="p in programas.data" :key="p.id" class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-5 py-4 font-mono font-bold text-slate-700">
                                <span class="rounded-lg bg-slate-100 px-2 py-1 text-slate-700 border border-slate-200/70">{{ p.codigo }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                                    </svg>
                                    {{ p.facultad || '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="font-bold text-slate-900 text-sm block">{{ p.nombre }}</span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <Link :href="`/mallas?carrera_id=${p.id}`"
                                      class="inline-flex items-center justify-center rounded-xl bg-sky-50 px-3 py-1 text-xs font-bold text-[#00205B] border border-sky-100 hover:bg-sky-100 transition-colors">
                                    {{ p.mallas_count || 0 }} mallas
                                </Link>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span :class="p.activo ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200'"
                                      class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold">
                                    <span :class="p.activo ? 'bg-emerald-500' : 'bg-slate-400'" class="h-1.5 w-1.5 rounded-full"></span>
                                    {{ p.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/estructura/programas/${p.id}/editar`"
                                          class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-[#0036DC] shadow-2xs hover:border-[#0036DC] hover:bg-blue-50/50 transition-all">
                                        Editar
                                    </Link>
                                    <button @click="cambiarEstado(p)"
                                            class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-2xs hover:bg-slate-50 transition-all">
                                        {{ p.activo ? 'Inactivar' : 'Activar' }}
                                    </button>
                                    <button @click="eliminar(p)"
                                            class="rounded-xl border border-rose-200 bg-rose-50/50 px-3 py-1.5 text-xs font-bold text-rose-600 shadow-2xs hover:bg-rose-100 transition-all">
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!programas.data.length">
                            <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="h-10 w-10 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                                    </svg>
                                    <p class="text-sm font-semibold text-slate-600">No se encontraron programas académicos</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Intenta modificar los filtros de búsqueda.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="programas.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-6 py-4 bg-slate-50/50">
                <p class="text-xs text-slate-500 font-medium">Mostrando {{ programas.from }}–{{ programas.to }} de {{ programas.total }} carreras</p>
                <nav v-if="programas.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, i) in programas.links" :key="i">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#00205B] text-white shadow-xs font-bold' : 'text-slate-600 hover:bg-white bg-slate-100/70'"
                              class="min-w-[32px] rounded-lg px-2.5 py-1.5 text-center text-xs transition-colors" v-html="link.label" />
                        <span v-else class="min-w-[32px] rounded-lg px-2.5 py-1.5 text-center text-xs text-slate-300" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
