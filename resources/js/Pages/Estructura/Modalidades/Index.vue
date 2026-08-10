<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import VolverA from '../../../Components/VolverA.vue';

const props = defineProps({ modalidades: Object, activas: Number, filtros: Object });

const filtro = reactive({ q: props.filtros?.q ?? '', estado: props.filtros?.estado ?? '' });
const aplicar = () => router.get('/estructura/modalidades', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => { filtro.q = ''; filtro.estado = ''; router.get('/estructura/modalidades', {}, { preserveScroll: true, replace: true }); };
const cambiarEstado = (m) => router.patch(`/estructura/modalidades/${m.id}/estado`, {}, { preserveScroll: true });
const eliminar = (m) => { if (confirm(`¿Eliminar la modalidad "${m.nombre}"?`)) router.delete(`/estructura/modalidades/${m.id}`, { preserveScroll: true }); };
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <VolverA href="/estructura" texto="Estructura institucional" />
                <h1 class="mt-1 text-2xl font-semibold text-[#1F3864]">Modalidades</h1>
                <p class="mt-1 text-sm text-slate-500">Modalidades de estudio (presencial, semipresencial, virtual).</p>
            </div>
            <Link href="/estructura/modalidades/crear"
                  class="inline-flex items-center gap-2 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#2E75B6]">
                <span class="text-base leading-none">+</span> Nueva modalidad
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
                <span class="text-xs font-medium uppercase tracking-wide text-blue-100">Modalidades activas</span>
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
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="m in modalidades.data" :key="m.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ m.codigo }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ m.nombre }}</td>
                            <td class="px-4 py-3">
                                <span :class="m.activo ? 'bg-green-50 text-green-700 ring-green-200' : 'bg-slate-100 text-slate-500 ring-slate-200'"
                                      class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    <span :class="m.activo ? 'bg-green-500' : 'bg-slate-400'" class="h-1.5 w-1.5 rounded-full"></span>
                                    {{ m.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/estructura/modalidades/${m.id}/editar`"
                                          class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-[#2E75B6] hover:border-[#2E75B6] hover:bg-slate-50">Editar</Link>
                                    <button @click="cambiarEstado(m)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">{{ m.activo ? 'Inactivar' : 'Activar' }}</button>
                                    <button @click="eliminar(m)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:border-red-300 hover:bg-red-50">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!modalidades.data.length">
                            <td colspan="4" class="px-4 py-10 text-center text-slate-400">No se encontraron modalidades.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="modalidades.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">Mostrando {{ modalidades.from }}–{{ modalidades.to }} de {{ modalidades.total }}</p>
                <nav v-if="modalidades.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, i) in modalidades.links" :key="i">
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
