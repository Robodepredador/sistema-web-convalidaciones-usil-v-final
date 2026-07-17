<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, nextTick } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({ postulantes: Object, total: Number, carreras: Array, estados: Array, filtros: Object });
const carrerasOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));

const filtro = reactive({
    q: props.filtros?.q ?? '',
    estado: props.filtros?.estado ?? '',
    carrera_destino_id: props.filtros?.carrera_destino_id ?? '',
});
const aplicar = () => router.get('/postulantes', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => { filtro.q = ''; filtro.estado = ''; filtro.carrera_destino_id = ''; router.get('/postulantes', {}, { preserveScroll: true, replace: true }); };
const eliminar = (p) => { if (confirm(`¿Eliminar al postulante "${p.nombre}"?`)) router.delete(`/postulantes/${p.id}`, { preserveScroll: true }); };
const resetearAcceso = (p) => { if (confirm(`¿Restablecer el acceso al portal de "${p.nombre}"? Se generará una contraseña temporal.`)) router.patch(`/postulantes/${p.id}/reset-acceso`, {}, { preserveScroll: true }); };

const ESTADO = {
    nuevo: { label: 'Nuevo', clase: 'bg-slate-100 text-slate-600 ring-slate-200' },
    en_evaluacion: { label: 'En evaluación', clase: 'bg-amber-50 text-amber-700 ring-amber-200' },
    admitido: { label: 'Admitido', clase: 'bg-green-50 text-green-700 ring-green-200' },
    rechazado: { label: 'Rechazado', clase: 'bg-red-50 text-red-700 ring-red-200' },
    matriculado: { label: 'Matriculado', clase: 'bg-indigo-50 text-indigo-700 ring-indigo-200' },
};
const estadoLabel = (e) => ESTADO[e]?.label ?? e;

// Estado de preconvalidación derivado (lo calcula el backend a partir de las simulaciones reales).
const PRECONV = {
    pendiente:   { label: 'Pendiente',    clase: 'bg-slate-100 text-slate-500 ring-slate-200' },
    atendida:    { label: 'Preconvalidada', clase: 'bg-blue-50 text-[#2E75B6] ring-blue-200' },
    convalidada: { label: 'Convalidada',  clase: 'bg-green-50 text-green-700 ring-green-200' },
};

// Estado de revisión de admisión (Ejecutivo Comercial).
const REVISION = {
    pendiente: { label: 'Pendiente de revisión', clase: 'bg-amber-50 text-amber-700 ring-amber-200' },
    aprobada:  { label: 'Aprobada',              clase: 'bg-green-50 text-green-700 ring-green-200' },
    observada: { label: 'Observada',             clase: 'bg-orange-50 text-orange-700 ring-orange-200' },
};

// ── Modal de preconvalidación ──
const modalAbierto = ref(false);
const modalCargando = ref(false);
const modalError = ref('');
const modalDatos = ref(null); // { postulante, preconvalidaciones, preconvalidacion_estado }
const detalleAbierto = reactive({});

const toggleDetalle = (id) => { detalleAbierto[id] = !detalleAbierto[id]; };

const abrirPreconvalidacion = async (postulante) => {
    // Solo abrir si tiene preconvalidaciones (no pendiente).
    if (postulante.preconvalidacion === 'pendiente') return;

    modalAbierto.value = true;
    modalCargando.value = true;
    modalError.value = '';
    modalDatos.value = null;
    // Limpiar detalles abiertos anteriores.
    Object.keys(detalleAbierto).forEach((k) => delete detalleAbierto[k]);

    try {
        const { data } = await window.axios.get(`/postulantes/${postulante.id}/preconvalidacion`);
        modalDatos.value = data;
    } catch (e) {
        modalError.value = e.response?.data?.message || 'No se pudieron cargar los datos de preconvalidación.';
    } finally {
        modalCargando.value = false;
    }
};

const cerrarModal = () => {
    modalAbierto.value = false;
    // Pequeño delay para la animación de salida.
    setTimeout(() => {
        modalDatos.value = null;
        modalError.value = '';
    }, 300);
};
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">Postulantes</h1>
                <p class="mt-1 text-sm text-slate-500">Solicitantes de convalidación por traslado externo.</p>
            </div>
            <Link href="/postulantes/crear"
                  class="inline-flex items-center gap-2 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#2E75B6]">
                <span class="text-base leading-none">+</span> Nuevo postulante
            </Link>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-[1fr_auto]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
                        <input v-model="filtro.q" type="text" placeholder="Nombre, documento, código o correo…" @keyup.enter="aplicar"
                               class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Estado</label>
                        <select v-model="filtro.estado" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Todos</option>
                            <option v-for="e in estados" :key="e" :value="e">{{ estadoLabel(e) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Carrera destino</label>
                        <Autocomplete v-model="filtro.carrera_destino_id" :options="carrerasOpts" placeholder="Todas las carreras" />
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button @click="aplicar" class="rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864]">Filtrar</button>
                    <button @click="limpiar" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Limpiar</button>
                </div>
            </div>
            <div class="flex min-w-[160px] flex-col justify-center rounded-xl bg-gradient-to-br from-[#1F3864] to-[#2E75B6] p-5 text-white shadow-sm">
                <span class="text-xs font-medium uppercase tracking-wide text-blue-100">Postulantes</span>
                <span class="mt-1 text-4xl font-bold leading-none">{{ total }}</span>
                <span class="mt-1 text-xs text-blue-100">registrados</span>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Código</th>
                            <th class="px-4 py-3 font-semibold">Documento</th>
                            <th class="px-4 py-3 font-semibold">Postulante</th>
                            <th class="px-4 py-3 font-semibold">Carrera destino</th>
                            <th class="px-4 py-3 font-semibold">Procedencia</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 font-semibold">Revisión</th>
                            <th class="px-4 py-3 font-semibold">Preconvalidación</th>
                            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in postulantes.data" :key="p.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ p.codigo }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.documento }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ p.nombre }}</p>
                                <p class="text-xs text-slate-400">{{ p.email }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ p.carrera_destino || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.procedencia || '—' }}</td>
                            <td class="px-4 py-3">
                                <span :class="ESTADO[p.estado]?.clase" class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">{{ estadoLabel(p.estado) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="p.revision" :class="REVISION[p.revision]?.clase" class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    {{ REVISION[p.revision]?.label ?? p.revision }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <button v-if="p.preconvalidacion !== 'pendiente'"
                                        @click="abrirPreconvalidacion(p)"
                                        :class="PRECONV[p.preconvalidacion]?.clase"
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset transition-all hover:shadow-md hover:ring-2 cursor-pointer">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    {{ PRECONV[p.preconvalidacion]?.label }}
                                </button>
                                <span v-else :class="PRECONV[p.preconvalidacion]?.clase" class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    {{ PRECONV[p.preconvalidacion]?.label }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/postulantes/${p.id}/editar`"
                                          class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-[#2E75B6] hover:border-[#2E75B6] hover:bg-slate-50">Editar</Link>
                                    <button @click="resetearAcceso(p)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-amber-700 hover:border-amber-300 hover:bg-amber-50">Resetear acceso</button>
                                    <button @click="eliminar(p)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:border-red-300 hover:bg-red-50">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!postulantes.data.length">
                            <td colspan="9" class="px-4 py-10 text-center text-slate-400">No se encontraron postulantes.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="postulantes.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">Mostrando {{ postulantes.from }}–{{ postulantes.to }} de {{ postulantes.total }}</p>
                <nav v-if="postulantes.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, i) in postulantes.links" :key="i">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-100'"
                              class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm" v-html="link.label" />
                        <span v-else class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm text-slate-300" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- Modal / Slide-over: Detalle de preconvalidación                    -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="modalAbierto" class="fixed inset-0 z-[60] flex justify-end" @click.self="cerrarModal">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="cerrarModal"></div>

                    <!-- Panel -->
                    <div class="relative z-10 flex w-full max-w-2xl flex-col bg-white shadow-2xl transition-transform">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-slate-200 bg-gradient-to-r from-[#1F3864] to-[#2E75B6] px-6 py-4">
                            <div>
                                <h2 class="text-lg font-semibold text-white">Detalle de preconvalidación</h2>
                                <p v-if="modalDatos" class="mt-0.5 text-sm text-blue-100">
                                    {{ modalDatos.postulante.nombre }} · {{ modalDatos.postulante.codigo }}
                                </p>
                            </div>
                            <button @click="cerrarModal"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-white/80 transition-colors hover:bg-white/10 hover:text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="flex-1 overflow-y-auto px-6 py-5">
                            <!-- Cargando -->
                            <div v-if="modalCargando" class="flex flex-col items-center justify-center py-16">
                                <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-[#2E75B6]"></div>
                                <p class="mt-4 text-sm text-slate-500">Cargando preconvalidación…</p>
                            </div>

                            <!-- Error -->
                            <div v-else-if="modalError" class="flex flex-col items-center justify-center py-16">
                                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-red-50">
                                    <svg class="h-7 w-7 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                </div>
                                <p class="mt-3 text-sm text-red-600">{{ modalError }}</p>
                                <button @click="cerrarModal" class="mt-4 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cerrar</button>
                            </div>

                            <!-- Datos -->
                            <template v-else-if="modalDatos">
                                <!-- Estado general -->
                                <div class="mb-5 flex items-center gap-3">
                                    <span :class="PRECONV[modalDatos.preconvalidacion_estado]?.clase"
                                          class="inline-block rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset">
                                        {{ PRECONV[modalDatos.preconvalidacion_estado]?.label }}
                                    </span>
                                    <span class="text-sm text-slate-500">
                                        {{ modalDatos.preconvalidaciones.length }} expediente{{ modalDatos.preconvalidaciones.length !== 1 ? 's' : '' }}
                                    </span>
                                </div>

                                <!-- Sin expedientes (no debería ocurrir si no es pendiente) -->
                                <div v-if="!modalDatos.preconvalidaciones.length"
                                     class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                                    No hay expedientes de preconvalidación registrados.
                                </div>

                                <!-- Lista de expedientes -->
                                <div v-else class="space-y-3">
                                    <div v-for="s in modalDatos.preconvalidaciones" :key="s.id"
                                         class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">
                                        <!-- Cabecera del expediente -->
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#2E75B6]/10">
                                                    <svg class="h-4 w-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                    </svg>
                                                </div>
                                                <span class="font-semibold text-slate-700">Expediente #{{ s.id }}</span>
                                            </div>
                                            <span class="text-sm text-slate-500">{{ s.carrera || '—' }}</span>
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ s.metodo === 'ia' ? '🤖 Asistida' : '✋ Manual' }}</span>
                                            <span class="text-xs text-slate-400">{{ s.fecha }}</span>
                                        </div>

                                        <!-- Métricas -->
                                        <div class="grid grid-cols-3 gap-px border-t border-b border-slate-100 bg-slate-100">
                                            <div class="flex flex-col items-center bg-white py-3">
                                                <span class="text-lg font-bold text-[#1F3864]">{{ s.convalidados }}</span>
                                                <span class="text-xs text-slate-400">Convalidados</span>
                                            </div>
                                            <div class="flex flex-col items-center bg-white py-3">
                                                <span class="text-lg font-bold text-[#2E75B6]">{{ s.creditos.toFixed(1) }}</span>
                                                <span class="text-xs text-slate-400">Créditos</span>
                                            </div>
                                            <div class="flex flex-col items-center bg-white py-3">
                                                <span v-if="s.convalidada" class="inline-flex items-center gap-1 text-sm font-semibold text-green-700">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                    Convalidada
                                                </span>
                                                <span v-else class="text-sm font-medium capitalize text-[#2E75B6]">{{ s.estado }}</span>
                                                <span v-if="s.convalidada && s.memorandum" class="text-xs text-slate-400">{{ s.memorandum }}</span>
                                                <span v-else class="text-xs text-slate-400">Estado</span>
                                            </div>
                                        </div>

                                        <!-- Acciones y toggle de detalle -->
                                        <div class="flex items-center justify-between px-4 py-2.5">
                                            <button type="button" @click="toggleDetalle(s.id)"
                                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-[#2E75B6]">
                                                <svg class="h-4 w-4 transition-transform duration-200" :class="detalleAbierto[s.id] ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                                {{ detalleAbierto[s.id] ? 'Ocultar cursos' : 'Ver cursos' }}
                                            </button>
                                            <div class="flex items-center gap-2">
                                                <a :href="s.pdf" target="_blank" rel="noopener"
                                                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-[#2E75B6] transition-colors hover:border-[#2E75B6] hover:bg-[#2E75B6]/5">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                    </svg>
                                                    PDF
                                                </a>
                                                <a :href="s.excel" target="_blank" rel="noopener"
                                                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-green-700 transition-colors hover:border-green-300 hover:bg-green-50">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M12 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5" />
                                                    </svg>
                                                    Excel
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Tabla de cursos (detalle expandible) -->
                                        <Transition name="expand">
                                            <div v-if="detalleAbierto[s.id]" class="border-t border-slate-100 bg-slate-50/50">
                                                <div class="overflow-x-auto px-4 py-3">
                                                    <table class="min-w-full text-xs">
                                                        <thead class="text-left">
                                                            <tr class="border-b border-slate-200">
                                                                <th class="pb-2 pr-4 font-semibold text-slate-500">Curso de origen</th>
                                                                <th class="pb-2 pr-4 font-semibold text-slate-500">Nota</th>
                                                                <th class="pb-2 pr-4 font-semibold text-slate-500">Convalida con (USIL)</th>
                                                                <th class="pb-2 text-right font-semibold text-slate-500">Créd.</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-slate-100">
                                                            <tr v-for="(c, i) in s.cursos" :key="i" class="group hover:bg-white">
                                                                <td class="py-2 pr-4 text-slate-600">{{ c.origen }}</td>
                                                                <td class="py-2 pr-4">
                                                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-500">{{ c.nota || '—' }}</span>
                                                                </td>
                                                                <td class="py-2 pr-4 font-medium text-slate-700">
                                                                    <span v-if="c.usil" class="inline-flex items-center gap-1">
                                                                        <svg class="h-3 w-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                                        </svg>
                                                                        {{ c.usil }}
                                                                    </span>
                                                                    <span v-else class="text-slate-400">—</span>
                                                                </td>
                                                                <td class="py-2 text-right font-medium text-slate-600">{{ c.creditos.toFixed(1) }}</td>
                                                            </tr>
                                                            <tr v-if="!s.cursos.length">
                                                                <td colspan="4" class="py-4 text-center text-slate-400">Sin cursos convalidados en este expediente.</td>
                                                            </tr>
                                                        </tbody>
                                                        <!-- Totales -->
                                                        <tfoot v-if="s.cursos.length" class="border-t border-slate-200 font-semibold">
                                                            <tr>
                                                                <td class="py-2 pr-4 text-slate-600">Total</td>
                                                                <td class="py-2 pr-4"></td>
                                                                <td class="py-2 pr-4 text-slate-600">{{ s.convalidados }} curso{{ s.convalidados !== 1 ? 's' : '' }}</td>
                                                                <td class="py-2 text-right text-[#2E75B6]">{{ s.creditos.toFixed(1) }}</td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            </div>
                                        </Transition>
                                    </div>
                                </div>

                                <!-- Nota al pie -->
                                <p class="mt-4 text-center text-xs text-slate-400">
                                    Resultado de la evaluación del coordinador. Solo lectura.
                                </p>
                            </template>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-slate-200 bg-slate-50/80 px-6 py-3">
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-slate-400">Preconvalidación · Solo lectura</p>
                                <button @click="cerrarModal"
                                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-white hover:shadow-sm">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* Transición del modal slide-over */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}
.modal-enter-active > div:last-child,
.modal-leave-active > div:last-child {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-from > div:last-child,
.modal-leave-to > div:last-child {
    transform: translateX(100%);
}

/* Transición para expandir/contraer el detalle de cursos */
.expand-enter-active,
.expand-leave-active {
    transition: all 0.25s ease-out;
    overflow: hidden;
}
.expand-enter-from,
.expand-leave-to {
    max-height: 0;
    opacity: 0;
}
.expand-enter-to,
.expand-leave-from {
    max-height: 500px;
    opacity: 1;
}
</style>
