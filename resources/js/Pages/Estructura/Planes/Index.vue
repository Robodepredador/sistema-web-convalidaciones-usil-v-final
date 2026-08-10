<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../../Components/Autocomplete.vue';
import VolverA from '../../../Components/VolverA.vue';

const props = defineProps({ planes: Object, activos: Number, programas: Array, modalidades: Array, filtros: Object });
const programasOpts = computed(() => props.programas.map((p) => ({ value: p.id, label: p.nombre })));

const filtro = reactive({
    q: props.filtros?.q ?? '',
    carrera_id: props.filtros?.carrera_id ?? '',
    modalidad_id: props.filtros?.modalidad_id ?? '',
    estado: props.filtros?.estado ?? '',
});
const aplicar = () => router.get('/estructura/planes', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => { filtro.q = ''; filtro.carrera_id = ''; filtro.modalidad_id = ''; filtro.estado = ''; router.get('/estructura/planes', {}, { preserveScroll: true, replace: true }); };
const cambiarEstado = (p) => router.patch(`/estructura/planes/${p.id}/estado`, {}, { preserveScroll: true });
const eliminar = (p) => { if (confirm(`¿Eliminar el plan "${p.nombre}"?`)) router.delete(`/estructura/planes/${p.id}`, { preserveScroll: true }); };
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <VolverA href="/estructura" texto="Estructura institucional" />
                <h1 class="mt-1 text-2xl font-semibold text-[#1F3864]">Planes de Estudio</h1>
                <p class="mt-1 text-sm text-slate-500">Planes por programa académico y modalidad.</p>
            </div>
            <Link href="/estructura/planes/crear"
                  class="inline-flex items-center gap-2 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#2E75B6]">
                <span class="text-base leading-none">+</span> Nuevo plan
            </Link>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-[1fr_auto]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
                        <input v-model="filtro.q" type="text" placeholder="Código o nombre…" @keyup.enter="aplicar"
                               class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Programa</label>
                        <Autocomplete v-model="filtro.carrera_id" :options="programasOpts" placeholder="Todos los programas" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Modalidad</label>
                        <select v-model="filtro.modalidad_id" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Todas</option>
                            <option v-for="m in modalidades" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                        </select>
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
                <span class="text-xs font-medium uppercase tracking-wide text-blue-100">Planes activos</span>
                <span class="mt-1 text-4xl font-bold leading-none">{{ activos }}</span>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Código</th>
                            <th class="px-4 py-3 font-semibold">Programa</th>
                            <th class="px-4 py-3 font-semibold">Modalidad</th>
                            <th class="px-4 py-3 font-semibold">Nombre</th>
                            <th class="px-4 py-3 font-semibold">Año</th>
                            <th class="px-4 py-3 font-semibold">Versión</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in planes.data" :key="p.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ p.codigo }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.programa || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.modalidad || '—' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ p.nombre }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.anio }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.version }}</td>
                            <td class="px-4 py-3">
                                <span :class="p.activo ? 'bg-green-50 text-green-700 ring-green-200' : 'bg-slate-100 text-slate-500 ring-slate-200'"
                                      class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    <span :class="p.activo ? 'bg-green-500' : 'bg-slate-400'" class="h-1.5 w-1.5 rounded-full"></span>
                                    {{ p.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/estructura/planes/${p.id}/editar`"
                                          class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-[#2E75B6] hover:border-[#2E75B6] hover:bg-slate-50">Editar</Link>
                                    <button @click="cambiarEstado(p)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">{{ p.activo ? 'Inactivar' : 'Activar' }}</button>
                                    <button @click="eliminar(p)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:border-red-300 hover:bg-red-50">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!planes.data.length">
                            <td colspan="8" class="px-4 py-10 text-center text-slate-400">No se encontraron planes de estudio.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="planes.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">Mostrando {{ planes.from }}–{{ planes.to }} de {{ planes.total }}</p>
                <nav v-if="planes.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, i) in planes.links" :key="i">
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
