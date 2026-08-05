<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

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
    pais: props.filtros?.pais ?? '',
    estado: props.filtros?.estado ?? '',
});

const aplicar = () => router.get('/instituciones', filtro, { preserveState: true, preserveScroll: true, replace: true });

const limpiar = () => {
    filtro.buscar = '';
    filtro.tipo_id = '';
    filtro.gestion = '';
    filtro.pais = '';
    filtro.estado = '';
    router.get('/instituciones', {}, { preserveScroll: true, replace: true });
};

const licenciaBadge = (l) =>
    l === 'licenciada'
        ? { label: 'Licenciada', clase: 'bg-green-50 text-green-700 ring-green-200' }
        : l === 'no_licenciada'
            ? { label: 'No licenciada', clase: 'bg-red-50 text-red-700 ring-red-200' }
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
    <div>
        <!-- Encabezado -->
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">Instituciones externas</h1>
                <p class="mt-1 text-sm text-slate-500">Universidades e institutos de procedencia para convalidaciones.</p>
            </div>
            <Link href="/instituciones/crear"
                  class="inline-flex items-center gap-2 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#2E75B6]">
                <span class="text-base leading-none">+</span> Nueva institución
            </Link>
        </div>

        <!-- Filtros + tarjeta de resumen -->
        <div class="mb-6 grid gap-4 lg:grid-cols-[1fr_auto]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3">
                    <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">🔍</span>
                        <input v-model="filtro.buscar" @keyup.enter="aplicar" type="search"
                               placeholder="Buscar institución por nombre…"
                               class="w-full rounded-md border-slate-300 pl-9 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Tipo</label>
                        <select v-model="filtro.tipo_id"
                                class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Todos los tipos</option>
                            <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Gestión</label>
                        <select v-model="filtro.gestion"
                                class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Todas</option>
                            <option value="publica">Pública</option>
                            <option value="privada">Privada</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">País</label>
                        <select v-model="filtro.pais"
                                class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Todos los países</option>
                            <option v-for="p in paises" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Estado</label>
                        <select v-model="filtro.estado"
                                class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Todos</option>
                            <option value="activa">Activas</option>
                            <option value="inactiva">Inactivas</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button @click="aplicar"
                            class="rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864]">
                        Filtrar
                    </button>
                    <button @click="limpiar"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                        Limpiar
                    </button>
                </div>
            </div>

            <div class="flex min-w-[170px] flex-col justify-center rounded-xl bg-gradient-to-br from-[#1F3864] to-[#2E75B6] p-5 text-white shadow-sm">
                <span class="text-xs font-medium uppercase tracking-wide text-blue-100">Instituciones activas</span>
                <span class="mt-1 text-4xl font-bold leading-none">{{ institucionesActivas }}</span>
                <span class="mt-1 text-xs text-blue-100">registradas</span>
            </div>
        </div>

        <!-- Tabla -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Nombre</th>
                            <th class="px-4 py-3 font-semibold">Tipo</th>
                            <th class="px-4 py-3 font-semibold">Gestión</th>
                            <th class="px-4 py-3 font-semibold">País</th>
                            <th class="px-4 py-3 font-semibold">Carreras</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="i in instituciones.data" :key="i.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ i.nombre }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-inset ring-sky-200">
                                    {{ i.tipo }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="gestionBadge(i.gestion).clase"
                                      class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    {{ gestionBadge(i.gestion).label }}
                                </span>
                                <span :class="licenciaBadge(i.licenciamiento).clase"
                                      class="ml-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    {{ licenciaBadge(i.licenciamiento).label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ i.pais ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ i.carreras_count }}</td>
                            <td class="px-4 py-3">
                                <span :class="i.activa ? 'bg-green-50 text-green-700 ring-green-200' : 'bg-slate-100 text-slate-500 ring-slate-200'"
                                      class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    <span :class="i.activa ? 'bg-green-500' : 'bg-slate-400'" class="h-1.5 w-1.5 rounded-full"></span>
                                    {{ i.activa ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/instituciones/${i.id}/editar`"
                                          class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-[#2E75B6] hover:border-[#2E75B6] hover:bg-slate-50"
                                          title="Editar institución">
                                        <span aria-hidden="true">✎</span> Editar
                                    </Link>
                                    <button v-if="i.activa" @click="desactivar(i)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:border-red-300 hover:bg-red-50">
                                        Desactivar
                                    </button>
                                    <button v-else @click="activar(i)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-green-700 hover:border-green-300 hover:bg-green-50">
                                        Activar
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!instituciones.data.length">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                No se encontraron instituciones con los filtros seleccionados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="instituciones.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">
                    Mostrando <span class="font-medium text-slate-700">{{ instituciones.from }}</span>–<span class="font-medium text-slate-700">{{ instituciones.to }}</span>
                    de <span class="font-medium text-slate-700">{{ instituciones.total }}</span> instituciones
                </p>
                <nav v-if="instituciones.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, idx) in instituciones.links" :key="idx">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-100'"
                              class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm" v-html="link.label" />
                        <span v-else class="min-w-[34px] cursor-not-allowed rounded-md px-2.5 py-1.5 text-center text-sm text-slate-300"
                              v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
