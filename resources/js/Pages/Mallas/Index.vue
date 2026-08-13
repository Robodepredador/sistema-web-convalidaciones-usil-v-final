<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    mallas: Object,
    mallasActivas: { type: Number, default: 0 },
    unidades: { type: Array, default: () => [] },
    facultades: { type: Array, default: () => [] },
    carreras: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
});

const filtro = reactive({
    unidad_negocio_id: props.filtros?.unidad_negocio_id ?? '',
    facultad_id: props.filtros?.facultad_id ?? '',
    carrera_id: props.filtros?.carrera_id ?? '',
    anio: props.filtros?.anio ?? '',
});

// Filtros en cascada: facultad depende de la unidad; carrera depende de la facultad.
const facultadesFiltradas = computed(() =>
    filtro.unidad_negocio_id
        ? props.facultades.filter((f) => String(f.unidad_negocio_id) === String(filtro.unidad_negocio_id))
        : props.facultades,
);

const carrerasFiltradas = computed(() => {
    if (filtro.facultad_id) {
        return props.carreras.filter((c) => String(c.facultad_id) === String(filtro.facultad_id));
    }
    if (filtro.unidad_negocio_id) {
        const ids = facultadesFiltradas.value.map((f) => String(f.id));
        return props.carreras.filter((c) => ids.includes(String(c.facultad_id)));
    }
    return props.carreras;
});

const onUnidadChange = () => {
    filtro.facultad_id = '';
    filtro.carrera_id = '';
};
const onFacultadChange = () => {
    filtro.carrera_id = '';
};

const unidadesOpts = computed(() => props.unidades.map((u) => ({ value: u.id, label: u.nombre })));
const facultadesOpts = computed(() => facultadesFiltradas.value.map((f) => ({ value: f.id, label: f.nombre })));
const carrerasOpts = computed(() => carrerasFiltradas.value.map((c) => ({ value: c.id, label: c.nombre })));

const aplicar = () => router.get('/mallas', filtro, { preserveState: true, preserveScroll: true, replace: true });

const limpiar = () => {
    filtro.unidad_negocio_id = '';
    filtro.facultad_id = '';
    filtro.carrera_id = '';
    filtro.anio = '';
    router.get('/mallas', {}, { preserveScroll: true, replace: true });
};

const origenBadge = (o) =>
    o === 'excel' ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-slate-100 text-slate-600 ring-slate-200';

const MODALIDADES = {
    presencial: { label: 'Presencial', clase: 'bg-sky-50 text-sky-700 ring-sky-200' },
    hibrido: { label: 'Híbrido', clase: 'bg-violet-50 text-violet-700 ring-violet-200' },
    virtual: { label: 'Virtual', clase: 'bg-teal-50 text-teal-700 ring-teal-200' },
};
const modalidad = (m) => MODALIDADES[m] ?? { label: m ?? '—', clase: 'bg-slate-100 text-slate-600 ring-slate-200' };

const eliminar = (m) => {
    const aviso = m.activa
        ? `⚠️ Esta es la malla ACTIVA de ${m.carrera} (${m.version}). ¿Eliminarla de todos modos?`
        : `¿Eliminar la malla ${m.carrera} — ${m.version}?`;
    if (confirm(aviso)) router.delete(`/mallas/${m.id}`, { preserveScroll: true });
};
</script>

<template>
    <div>
        <!-- Encabezado -->
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">Gestión de Mallas Curriculares</h1>
                <p class="mt-1 text-sm text-slate-500">Administración y seguimiento de los programas académicos.</p>
            </div>
            <div>
                <Link href="/mallas/crear"
                      class="inline-flex items-center gap-1.5 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#2E75B6]">
                    <span class="text-base leading-none">+</span> Nueva malla
                </Link>
            </div>
        </div>

        <!-- Filtros + tarjeta de resumen -->
        <div class="mb-6 grid gap-4 lg:grid-cols-[1fr_auto]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Unidad de Negocio</label>
                        <Autocomplete v-model="filtro.unidad_negocio_id" :options="unidadesOpts"
                                      placeholder="Todas las unidades" @update:modelValue="onUnidadChange" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Facultad</label>
                        <Autocomplete v-model="filtro.facultad_id" :options="facultadesOpts"
                                      placeholder="Todas las facultades" @update:modelValue="onFacultadChange" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Carrera</label>
                        <Autocomplete v-model="filtro.carrera_id" :options="carrerasOpts" placeholder="Todas las carreras" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Año</label>
                        <input v-model="filtro.anio" type="number" placeholder="Todos" min="2000" max="2100"
                               class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
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

            <!-- Tarjeta resumen: Mallas Activas -->
            <div class="flex min-w-[170px] flex-col justify-center rounded-xl bg-gradient-to-br from-[#1F3864] to-[#2E75B6] p-5 text-white shadow-sm">
                <span class="text-xs font-medium uppercase tracking-wide text-blue-100">Mallas activas</span>
                <span class="mt-1 text-4xl font-bold leading-none">{{ mallasActivas }}</span>
                <span class="mt-1 text-xs text-blue-100">en tu alcance</span>
            </div>
        </div>

        <!-- Tabla -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Unidad de Negocio</th>
                            <th class="px-4 py-3 font-semibold">Facultad</th>
                            <th class="px-4 py-3 font-semibold">Carrera</th>
                            <th class="px-4 py-3 font-semibold">Año</th>
                            <th class="px-4 py-3 font-semibold">Versión</th>
                            <th class="px-4 py-3 font-semibold">Modalidad</th>
                            <th class="px-4 py-3 font-semibold">Periodo</th>
                            <th class="px-4 py-3 font-semibold">Origen</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="m in mallas.data" :key="m.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 text-slate-600">{{ m.unidad }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ m.facultad }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ m.carrera }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ m.anio }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ m.version }}</td>
                            <td class="px-4 py-3">
                                <span :class="modalidad(m.modalidad).clase"
                                      class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    {{ modalidad(m.modalidad).label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ m.periodo || '—' }}</td>
                            <td class="px-4 py-3">
                                <span :class="origenBadge(m.origen)"
                                      class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ring-1 ring-inset">
                                    {{ m.origen }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="m.activa ? 'bg-green-50 text-green-700 ring-green-200' : 'bg-slate-100 text-slate-500 ring-slate-200'"
                                      class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    <span :class="m.activa ? 'bg-green-500' : 'bg-slate-400'" class="h-1.5 w-1.5 rounded-full"></span>
                                    {{ m.activa ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/mallas/${m.id}`"
                                          class="rounded-md bg-[#1F3864] px-2.5 py-1 text-xs font-medium text-white hover:bg-[#2E75B6]"
                                          title="Gestionar ciclos y cursos">Gestionar</Link>
                                    <Link :href="`/mallas/${m.id}/editar`"
                                          class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-[#2E75B6] hover:border-[#2E75B6] hover:bg-slate-50"
                                          title="Editar cabecera">
                                        <span aria-hidden="true">✎</span> Editar
                                    </Link>
                                    <button @click="eliminar(m)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:border-red-300 hover:bg-red-50"
                                            title="Eliminar malla">✕ Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!mallas.data.length">
                            <td colspan="10" class="px-4 py-10 text-center text-slate-400">
                                No se encontraron mallas con los filtros seleccionados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pie: conteo + paginación -->
            <div v-if="mallas.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">
                    Mostrando <span class="font-medium text-slate-700">{{ mallas.from }}</span>–<span class="font-medium text-slate-700">{{ mallas.to }}</span>
                    de <span class="font-medium text-slate-700">{{ mallas.total }}</span> mallas
                </p>
                <nav v-if="mallas.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, i) in mallas.links" :key="i">
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
