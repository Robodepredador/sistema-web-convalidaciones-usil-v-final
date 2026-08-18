<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    postulantes: Object,
    total: Number,
    stats: Object,
    carreras: Array,
    revisiones: Array,
    filtros: Object,
    es_ejecutivo: Boolean,
    es_asesor: Boolean,
});

const carrerasOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));

// RBAC: capacidades del usuario para separar el flujo de cada rol.
const userRol = computed(() => {
    const r = usePage().props.auth?.user?.rol;
    if (!r) return '';
    return typeof r === 'object' ? (r.nombre ?? '') : String(r);
});
const permisos = computed(() => usePage().props.auth?.user?.permisos ?? []);
const puede = (clave) => permisos.value.includes('*') || permisos.value.includes(clave);
const esEjecutivo = computed(() => props.es_ejecutivo || userRol.value === 'Ejecutivo Comercial de Admisión' || (puede('solicitudes.validar') && !puede('evaluacion.editar') && userRol.value !== 'Asesor de Admisión'));
const esAsesor = computed(() => props.es_asesor || userRol.value === 'Asesor de Admisión');
const esSuperusuario = computed(() => userRol.value === 'Superusuario');
const puedeCrear = computed(() => esAsesor.value || esSuperusuario.value || puede('solicitudes.crear'));

const filtro = reactive({
    q: props.filtros?.q ?? '',
    revision: props.filtros?.revision ?? '',
    carrera_destino_id: props.filtros?.carrera_destino_id ?? '',
    desde: props.filtros?.desde ?? '',
    hasta: props.filtros?.hasta ?? '',
});

const aplicar = () => router.get('/postulantes', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => {
    Object.keys(filtro).forEach((k) => { filtro[k] = ''; });
    router.get('/postulantes', {}, { preserveScroll: true, replace: true });
};

const setRevisionRapida = (rev) => {
    filtro.revision = rev;
    aplicar();
};

const eliminar = (p) => {
    if (confirm(`¿Eliminar al postulante "${p.nombre}"?`)) {
        router.delete(`/postulantes/${p.id}`, { preserveScroll: true });
    }
};

const resetearAcceso = (p) => {
    if (confirm(`¿Restablecer el acceso al portal de "${p.nombre}"? Se generará una contraseña temporal.`)) {
        router.patch(`/postulantes/${p.id}/reset-acceso`, {}, { preserveScroll: true });
    }
};

// Estado de preconvalidación derivado
const PRECONV = {
    pendiente:   { label: 'Pendiente',      clase: 'bg-slate-100 text-slate-600 border-slate-200' },
    atendida:    { label: 'Preconvalidada', clase: 'bg-blue-50 text-[#0036DC] border-blue-200 font-semibold' },
    convalidada: { label: 'Convalidada',    clase: 'bg-emerald-50 text-emerald-700 border-emerald-200 font-semibold' },
};

// Estado de revisión de admisión
const REVISION = {
    pendiente: { label: 'Pendiente', clase: 'bg-amber-50 text-amber-700 border-amber-200' },
    aprobada:  { label: 'Aprobada',  clase: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    observada: { label: 'Observada', clase: 'bg-rose-50 text-rose-700 border-rose-200' },
};

// ── Modal de preconvalidación ──
const modalAbierto = ref(false);
const modalCargando = ref(false);
const modalError = ref('');
const modalDatos = ref(null);
const detalleAbierto = reactive({});

const toggleDetalle = (id) => { detalleAbierto[id] = !detalleAbierto[id]; };

const abrirPreconvalidacion = async (postulante) => {
    if (postulante.preconvalidacion === 'pendiente') return;

    modalAbierto.value = true;
    modalCargando.value = true;
    modalError.value = '';
    modalDatos.value = null;
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
    setTimeout(() => {
        modalDatos.value = null;
        modalError.value = '';
    }, 300);
};

// Función para obtener iniciales del postulante
const getIniciales = (nombre) => {
    if (!nombre) return 'P';
    const partes = nombre.trim().split(' ').filter(Boolean);
    if (partes.length >= 2) {
        return (partes[0][0] + partes[1][0]).toUpperCase();
    }
    return nombre.substring(0, 2).toUpperCase();
};
</script>

<template>
    <div class="min-h-screen bg-[#F4F6F9] p-6 lg:p-10">
        <!-- HERO BANNER INSTITUCIONAL USIL -->
        <div class="relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <div class="pointer-events-none absolute -right-12 -top-12 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
            <div class="pointer-events-none absolute right-1/3 -bottom-16 h-48 w-48 rounded-full bg-[#0036DC]/20 blur-xl"></div>

            <div class="relative z-10 p-6 sm:p-8 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 mb-2 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                        <span class="h-2 w-2 rounded-full bg-[#FFB81C] animate-pulse"></span>
                        <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">
                            {{ esEjecutivo ? 'AUDITORÍA DE ADMISIÓN · BANDEJA DE VALIDACIÓN' : 'MÓDULO DE SOLICITUDES · TRASLADO EXTERNO' }}
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                        {{ esEjecutivo ? 'Bandeja de Validación de Expedientes' : 'Gestión de Postulantes' }}
                    </h1>
                    <p class="mt-1 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-2xl">
                        {{ esEjecutivo
                            ? 'Audita la documentación de los postulantes de traslado externo y aprueba o devuelve los expedientes para su evaluación por facultad.'
                            : 'Registra, administra y da seguimiento a tus postulantes de traslado externo hacia programas USIL.' }}
                    </p>
                </div>

                <div v-if="!esEjecutivo && puedeCrear" class="flex items-center gap-3 shrink-0">
                    <Link href="/postulantes/crear"
                          class="inline-flex items-center gap-2 rounded-2xl bg-white text-[#00205B] hover:bg-blue-50 px-5 py-3 text-xs sm:text-sm font-bold shadow-lg hover:shadow-xl transition-all duration-200 hover:-translate-y-0.5">
                        <svg class="h-4 w-4 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Nuevo Postulante</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- TARJETAS DE MÉTRICAS KPI (ESPECIALMENTE RELEVANTES PARA EL EJECUTIVO) -->
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <!-- Pendientes -->
            <button @click="setRevisionRapida('pendiente')"
                    :class="filtro.revision === 'pendiente' ? 'ring-2 ring-amber-400 shadow-md bg-amber-50/90 border-amber-300' : 'bg-white border-slate-200/80 hover:border-amber-200 hover:bg-amber-50/40'"
                    class="rounded-3xl border p-4 text-left transition-all cursor-pointer">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-amber-800">Pendientes</span>
                    <span class="flex h-2 w-2 rounded-full bg-amber-500 animate-ping"></span>
                </div>
                <p class="mt-2 text-2xl font-black text-amber-900 tabular-nums">{{ stats?.pendientes ?? 0 }}</p>
                <p class="text-[10px] text-amber-700/80 font-medium">Requieren dictamen</p>
            </button>

            <!-- Aprobadas -->
            <button @click="setRevisionRapida('aprobada')"
                    :class="filtro.revision === 'aprobada' ? 'ring-2 ring-emerald-400 shadow-md bg-emerald-50/90 border-emerald-300' : 'bg-white border-slate-200/80 hover:border-emerald-200 hover:bg-emerald-50/40'"
                    class="rounded-3xl border p-4 text-left transition-all cursor-pointer">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-800">Aprobadas</span>
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                </div>
                <p class="mt-2 text-2xl font-black text-emerald-900 tabular-nums">{{ stats?.aprobadas ?? 0 }}</p>
                <p class="text-[10px] text-emerald-700/80 font-medium">Listas para facultad</p>
            </button>

            <!-- Observadas -->
            <button @click="setRevisionRapida('observada')"
                    :class="filtro.revision === 'observada' ? 'ring-2 ring-rose-400 shadow-md bg-rose-50/90 border-rose-300' : 'bg-white border-slate-200/80 hover:border-rose-200 hover:bg-rose-50/40'"
                    class="rounded-3xl border p-4 text-left transition-all cursor-pointer">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-rose-800">Observadas</span>
                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                </div>
                <p class="mt-2 text-2xl font-black text-rose-900 tabular-nums">{{ stats?.observadas ?? 0 }}</p>
                <p class="text-[10px] text-rose-700/80 font-medium">Devueltas para subsanación</p>
            </button>

            <!-- Total -->
            <button @click="setRevisionRapida('')"
                    :class="filtro.revision === '' ? 'ring-2 ring-[#0036DC] shadow-md bg-blue-50/90 border-blue-300' : 'bg-white border-slate-200/80 hover:border-blue-200 hover:bg-blue-50/40'"
                    class="rounded-3xl border p-4 text-left transition-all cursor-pointer">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-600">Total Expedientes</span>
                    <span class="h-2 w-2 rounded-full bg-[#0036DC]"></span>
                </div>
                <p class="mt-2 text-2xl font-black text-[#00205B] tabular-nums">{{ stats?.total ?? total }}</p>
                <p class="text-[10px] text-slate-500 font-medium">Bandeja general</p>
            </button>
        </div>

        <!-- FILTROS Y RESUMEN KPI -->
        <div class="mb-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <!-- Tabs rápidos de revisión -->
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 mr-1">Filtrar por revisión:</span>
                    <button @click="setRevisionRapida('')"
                            :class="filtro.revision === '' ? 'bg-[#00205B] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200/70'"
                            class="rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer">
                        Todos ({{ stats?.total ?? total }})
                    </button>
                    <button @click="setRevisionRapida('pendiente')"
                            :class="filtro.revision === 'pendiente' ? 'bg-amber-500 text-white shadow-xs' : 'bg-amber-50 text-amber-700 hover:bg-amber-100/70'"
                            class="rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer">
                        Pendientes ({{ stats?.pendientes ?? 0 }})
                    </button>
                    <button @click="setRevisionRapida('aprobada')"
                            :class="filtro.revision === 'aprobada' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100/70'"
                            class="rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer">
                        Aprobadas ({{ stats?.aprobadas ?? 0 }})
                    </button>
                    <button @click="setRevisionRapida('observada')"
                            :class="filtro.revision === 'observada' ? 'bg-rose-500 text-white shadow-xs' : 'bg-rose-50 text-rose-700 hover:bg-rose-100/70'"
                            class="rounded-xl px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer">
                        Observadas ({{ stats?.observadas ?? 0 }})
                    </button>
                </div>

                <!-- KPI Mini Counter -->
                <div class="flex items-center gap-2 rounded-2xl bg-blue-50/70 border border-blue-100 px-4 py-1.5">
                    <span class="text-xs font-bold text-slate-600">Total en vista:</span>
                    <span class="text-sm font-extrabold text-[#00205B]">{{ postulantes.total }} postulantes</span>
                </div>
            </div>

            <!-- Controles de búsqueda detallados -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Búsqueda por texto -->
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">Buscar postulante</label>
                    <div class="relative">
                        <input v-model="filtro.q" type="text" placeholder="Nombre, DNI, código, correo…" @keyup.enter="aplicar"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-10 pr-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15 focus:outline-hidden transition-all duration-200" />
                        <svg class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                </div>

                <!-- Carrera Destino Autocomplete -->
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">Carrera destino USIL</label>
                    <Autocomplete v-model="filtro.carrera_destino_id" :options="carrerasOpts" placeholder="Todas las carreras…" />
                </div>

                <!-- Fecha Desde -->
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">Registrado desde</label>
                    <input v-model="filtro.desde" type="date" :max="filtro.hasta || undefined" @keyup.enter="aplicar"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15 focus:outline-hidden transition-all duration-200" />
                </div>

                <!-- Fecha Hasta -->
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">Registrado hasta</label>
                    <input v-model="filtro.hasta" type="date" :min="filtro.desde || undefined" @keyup.enter="aplicar"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/15 focus:outline-hidden transition-all duration-200" />
                </div>
            </div>

            <!-- Botones de acción del filtro -->
            <div class="mt-4 flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button @click="limpiar" class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2 text-xs font-bold text-slate-600 transition-colors">
                    Limpiar filtros
                </button>
                <button @click="aplicar" class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] px-5 py-2 text-xs font-bold text-white shadow-md transition-all duration-200 hover:shadow-lg">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                    </svg>
                    <span>Aplicar Filtros</span>
                </button>
            </div>
        </div>

        <!-- TABLA DE POSTULANTES -->
        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                            <th class="py-3.5 px-4">Postulante</th>
                            <th class="py-3.5 px-4">Documento</th>
                            <th v-if="esRevisor" class="py-3.5 px-4">Asesor</th>
                            <th class="py-3.5 px-4">Carrera Destino USIL</th>
                            <th class="py-3.5 px-4">Procedencia</th>
                            <th class="py-3.5 px-4 text-center">Docs.</th>
                            <th class="py-3.5 px-4 text-center">Revisión</th>
                            <th class="py-3.5 px-4 text-center">Preconvalidación</th>
                            <th class="py-3.5 px-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <tr v-for="p in postulantes.data" :key="p.id" class="hover:bg-blue-50/30 transition-colors">
                            <!-- Postulante info -->
                            <td class="py-3 px-4 min-w-[200px]">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-8 w-8 shrink-0 rounded-2xl bg-[#00205B] flex items-center justify-center text-white font-bold text-xs shadow-xs">
                                        {{ getIniciales(p.nombre) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <p class="font-bold text-slate-800 leading-tight truncate">{{ p.nombre }}</p>
                                            <span v-if="p.es_borrador" class="rounded-md bg-amber-50 border border-amber-200 px-1.5 py-0.2 text-[9px] font-bold text-amber-700 uppercase">
                                                Borrador
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-400 mt-0.5 truncate">{{ p.email }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Documento -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-mono font-medium text-slate-700">
                                    {{ p.documento }}
                                </span>
                            </td>

                            <!-- Asesor (solo revisor) -->
                            <td v-if="esRevisor" class="py-3 px-4 text-slate-600 font-medium whitespace-nowrap">
                                {{ p.asesor || '—' }}
                            </td>

                            <!-- Carrera Destino -->
                            <td class="py-3 px-4 min-w-[180px]">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-[#0036DC]"></span>
                                    <span class="font-semibold text-slate-800 leading-snug">{{ p.carrera_destino || '—' }}</span>
                                </div>
                            </td>

                            <!-- Procedencia -->
                            <td class="py-3 px-4 text-slate-600 min-w-[150px]">
                                <span class="leading-snug line-clamp-2">{{ p.procedencia || '—' }}</span>
                            </td>

                            <!-- Documentos completados -->
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span :class="p.documentos >= p.documentos_total ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                                      class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-extrabold tabular-nums">
                                    <svg v-if="p.documentos >= p.documentos_total" class="h-3 w-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    <span>{{ p.documentos }}/{{ p.documentos_total }}</span>
                                </span>
                            </td>

                            <!-- Estado de Revisión -->
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span v-if="p.revision" :class="REVISION[p.revision]?.clase" class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold">
                                    {{ REVISION[p.revision]?.label ?? p.revision }}
                                </span>
                            </td>

                            <!-- Estado de Preconvalidación (Click para drawer) -->
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <button v-if="p.preconvalidacion !== 'pendiente'"
                                        @click="abrirPreconvalidacion(p)"
                                        :class="PRECONV[p.preconvalidacion]?.clase"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-all hover:shadow-sm hover:scale-105 cursor-pointer whitespace-nowrap">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    <span>{{ PRECONV[p.preconvalidacion]?.label }}</span>
                                </button>
                                <span v-else :class="PRECONV[p.preconvalidacion]?.clase" class="inline-flex items-center rounded-full border px-3 py-1 text-xs whitespace-nowrap">
                                    {{ PRECONV[p.preconvalidacion]?.label }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <template v-if="esEjecutivo">
                                        <Link :href="`/postulantes/${p.id}/editar`"
                                              :class="p.revision === 'pendiente'
                                                  ? 'bg-[#00205B] hover:bg-[#0036DC] text-white hover:shadow-md hover:-translate-y-0.5 font-extrabold'
                                                  : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-bold'"
                                              class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs transition-all shadow-xs">
                                            <svg v-if="p.revision === 'pendiente'" class="h-3.5 w-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                            <span>{{ p.revision === 'pendiente' ? 'Revisar' : 'Ver Ficha' }}</span>
                                        </Link>
                                    </template>
                                    <template v-else>
                                        <Link :href="`/postulantes/${p.id}/editar`"
                                              class="inline-flex items-center gap-1 rounded-xl bg-blue-50 text-[#00205B] hover:bg-blue-100 px-3 py-1.5 text-xs font-bold transition-all">
                                            <span>Editar</span>
                                        </Link>
                                        <button v-if="puedeCrear" @click="resetearAcceso(p)" title="Resetear acceso al portal"
                                                class="rounded-xl border border-amber-200 bg-amber-50/50 hover:bg-amber-100/70 p-1.5 text-amber-700 transition-colors cursor-pointer">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                                            </svg>
                                        </button>
                                        <button v-if="puedeCrear" @click="eliminar(p)" title="Eliminar postulante"
                                                class="rounded-xl border border-rose-200 bg-rose-50/50 hover:bg-rose-100/70 p-1.5 text-rose-600 transition-colors cursor-pointer">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!postulantes.data.length">
                            <td :colspan="esEjecutivo ? 9 : 8" class="py-12 px-4 text-center">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-sm font-bold text-slate-700">No se encontraron postulantes</h3>
                                    <p class="text-xs text-slate-500 mt-1">Prueba modificando los filtros de búsqueda o registra un nuevo postulante.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN -->
            <div v-if="postulantes.data.length" class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 bg-slate-50/50 p-4">
                <p class="text-xs font-medium text-slate-500">
                    Mostrando <span class="font-bold text-slate-700">{{ postulantes.from }}</span> a <span class="font-bold text-slate-700">{{ postulantes.to }}</span> de <span class="font-bold text-slate-700">{{ postulantes.total }}</span> expedientes
                </p>
                <nav v-if="postulantes.last_page > 1" class="flex flex-wrap items-center gap-1.5">
                    <template v-for="(link, i) in postulantes.links" :key="i">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#00205B] text-white font-bold shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium'"
                              class="min-w-[32px] h-8 rounded-xl px-3 flex items-center justify-center text-xs transition-all" v-html="link.label" />
                        <span v-else class="min-w-[32px] h-8 rounded-xl px-3 flex items-center justify-center text-xs text-slate-300 font-medium" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- Modal / Slide-over: Detalle de preconvalidación                     -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="modalAbierto" class="fixed inset-0 z-60 flex justify-end" @click.self="cerrarModal">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="cerrarModal"></div>

                    <!-- Panel Drawer -->
                    <div class="relative z-10 flex w-full max-w-2xl flex-col bg-white shadow-2xl transition-transform">
                        <!-- Header Banner -->
                        <div class="flex items-center justify-between border-b border-slate-200 bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] px-6 py-5 text-white">
                            <div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 mb-1.5 rounded-full bg-white/10 text-[10px] font-bold uppercase tracking-wider text-blue-100">
                                    Resultado Oficial de Coordinación
                                </div>
                                <h2 class="text-lg font-extrabold text-white">Evaluación de Preconvalidación</h2>
                                <p v-if="modalDatos" class="text-xs text-blue-100/90 mt-0.5">
                                    {{ modalDatos.postulante.nombre }} · {{ modalDatos.postulante.codigo }}
                                </p>
                            </div>
                            <button @click="cerrarModal" class="rounded-full bg-white/10 hover:bg-white/20 p-2 text-white transition-colors cursor-pointer">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Contenido -->
                        <div class="flex-1 overflow-y-auto p-6 space-y-6">
                            <!-- Spinner de carga -->
                            <div v-if="modalCargando" class="flex flex-col items-center justify-center py-16 text-slate-400">
                                <svg class="h-8 w-8 animate-spin text-[#0036DC]" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="mt-3 text-xs font-semibold text-slate-500">Cargando resultados de evaluación…</p>
                            </div>

                            <!-- Error -->
                            <div v-else-if="modalError" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-xs font-semibold text-red-700">
                                {{ modalError }}
                            </div>

                            <!-- Datos cargados -->
                            <div v-else-if="modalDatos" class="space-y-6">
                                <!-- Banner informativo -->
                                <div class="rounded-2xl border border-blue-100 bg-blue-50/60 p-4 text-xs text-[#00205B] flex items-start gap-3">
                                    <svg class="h-5 w-5 shrink-0 text-[#0036DC] mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    <div>
                                        <p class="font-bold">Resultado emitido para admisión</p>
                                        <p class="text-[11px] text-slate-600 mt-0.5">El postulante visualiza estos resultados directamente desde su portal oficial.</p>
                                    </div>
                                </div>

                                <!-- Lista de simulaciones / preconvalidaciones -->
                                <div v-for="sim in modalDatos.preconvalidaciones" :key="sim.id" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs">
                                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                                        <div>
                                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-[#00205B]">{{ sim.carrera }}</span>
                                            <p class="text-xs text-slate-500 mt-0.5">Fecha: {{ sim.fecha }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span v-if="sim.convalidada" class="rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-[11px] font-bold text-emerald-700">
                                                Convalidada
                                            </span>
                                            <span v-else class="rounded-full bg-blue-50 border border-blue-200 px-3 py-1 text-[11px] font-bold text-[#00205B]">
                                                Preconvalidada
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Métricas rápidas -->
                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-100 text-center">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Cursos Convalidados</p>
                                            <p class="text-xl font-extrabold text-[#00205B] mt-0.5">{{ sim.convalidados }}</p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-100 text-center">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Créditos Reconocidos</p>
                                            <p class="text-xl font-extrabold text-[#0036DC] mt-0.5">{{ sim.creditos }}</p>
                                        </div>
                                    </div>

                                    <!-- Botón expandir detalle de asignaturas -->
                                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                                        <button @click="toggleDetalle(sim.id)" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0036DC] hover:text-[#00205B] cursor-pointer">
                                            <span>{{ detalleAbierto[sim.id] ? 'Ocultar asignaturas' : 'Ver asignaturas convalidadas' }}</span>
                                            <svg :class="detalleAbierto[sim.id] ? 'rotate-180' : ''" class="h-3.5 w-3.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>

                                        <!-- Descargas oficiales -->
                                        <div class="flex items-center gap-2">
                                            <a :href="sim.pdf" target="_blank" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-slate-700 transition-colors">
                                                <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                                <span>PDF</span>
                                            </a>
                                            <a v-if="sim.excel_oficial" :href="sim.excel_oficial" target="_blank" class="inline-flex items-center gap-1 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 text-[11px] font-bold text-emerald-800 transition-colors">
                                                <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <span>Excel Oficial</span>
                                            </a>
                                            <a :href="sim.excel" target="_blank" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-3 py-1.5 text-[11px] font-bold text-slate-700 transition-colors">
                                                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                                <span>Excel ERP</span>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Tabla desplegable de cursos -->
                                    <div v-if="detalleAbierto[sim.id]" class="mt-4 overflow-hidden rounded-2xl border border-slate-100 bg-slate-50/50">
                                        <table class="w-full text-left text-xs">
                                            <thead class="bg-slate-100/80 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                                <tr>
                                                    <th class="py-2.5 px-3">Curso Origen</th>
                                                    <th class="py-2.5 px-3">Nota</th>
                                                    <th class="py-2.5 px-3">Curso USIL Equivalente</th>
                                                    <th class="py-2.5 px-3 text-right">Créditos</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <tr v-for="(c, ci) in sim.cursos" :key="ci" class="hover:bg-white transition-colors">
                                                    <td class="py-2.5 px-3 font-medium text-slate-700">{{ c.origen }}</td>
                                                    <td class="py-2.5 px-3 font-mono text-slate-500">{{ c.nota || '—' }}</td>
                                                    <td class="py-2.5 px-3 font-bold text-[#00205B]">{{ c.usil }}</td>
                                                    <td class="py-2.5 px-3 text-right font-extrabold text-[#0036DC]">{{ c.creditos }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.25s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
