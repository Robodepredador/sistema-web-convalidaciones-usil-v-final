<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import VolverA from '../../../Components/VolverA.vue';

const props = defineProps({ sedes: Object, activas: Number, filtros: Object });

const filtro = reactive({
    q: props.filtros?.q ?? '',
    estado: props.filtros?.estado ?? '',
});

const aplicar = () => router.get('/estructura/sedes', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => { filtro.q = ''; filtro.estado = ''; router.get('/estructura/sedes', {}, { preserveScroll: true, replace: true }); };
const cambiarEstado = (s) => router.patch(`/estructura/sedes/${s.id}/estado`, {}, { preserveScroll: true });
const eliminar = (s) => { if (confirm(`¿Eliminar la sede "${s.nombre}"?`)) router.delete(`/estructura/sedes/${s.id}`, { preserveScroll: true }); };
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <VolverA href="/estructura" texto="Estructura institucional" />
                <h1 class="mt-1 text-2xl font-semibold text-[#1F3864]">Sedes</h1>
                <p class="mt-1 text-sm text-slate-500">Campus o locales de la institución.</p>
            </div>
            <Link href="/estructura/sedes/crear"
                  class="inline-flex items-center gap-2 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#2E75B6]">
                <span class="text-base leading-none">+</span> Nueva sede
            </Link>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-[1fr_auto]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
                        <input v-model="filtro.q" type="text" placeholder="Código o nombre…" @keyup.enter="aplicar"
                               class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Estado</label>
                        <select v-model="filtro.estado" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Todos</option>
                            <option value="activo">Activos</option>
                            <option value="inactivo">Inactivos</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button @click="aplicar" class="rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864]">Filtrar</button>
                    <button @click="limpiar" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Limpiar</button>
                </div>
            </div>
            <div class="flex min-w-[160px] flex-col justify-center rounded-xl bg-gradient-to-br from-[#1F3864] to-[#2E75B6] p-5 text-white shadow-sm">
                <span class="text-xs font-medium uppercase tracking-wide text-blue-100">Sedes activas</span>
                <span class="mt-1 text-4xl font-bold leading-none">{{ activas }}</span>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Código</th>
                            <th class="px-4 py-3 font-semibold">Nombre</th>
                            <th class="px-4 py-3 font-semibold">Dirección</th>
                            <th class="px-4 py-3 font-semibold">Facultades</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="s in sedes.data" :key="s.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ s.codigo }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ s.nombre }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ s.direccion || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ s.facultades_count }}</td>
                            <td class="px-4 py-3">
                                <span :class="s.activo ? 'bg-green-50 text-green-700 ring-green-200' : 'bg-slate-100 text-slate-500 ring-slate-200'"
                                      class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    <span :class="s.activo ? 'bg-green-500' : 'bg-slate-400'" class="h-1.5 w-1.5 rounded-full"></span>
                                    {{ s.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/estructura/sedes/${s.id}/editar`"
                                          class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-[#2E75B6] hover:border-[#2E75B6] hover:bg-slate-50">Editar</Link>
                                    <button @click="cambiarEstado(s)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                        {{ s.activo ? 'Inactivar' : 'Activar' }}
                                    </button>
                                    <button @click="eliminar(s)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:border-red-300 hover:bg-red-50">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!sedes.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">No se encontraron sedes.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="sedes.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">Mostrando {{ sedes.from }}–{{ sedes.to }} de {{ sedes.total }}</p>
                <nav v-if="sedes.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, i) in sedes.links" :key="i">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-100'"
                              class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm" v-html="link.label" />
                        <span v-else class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm text-slate-300" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
