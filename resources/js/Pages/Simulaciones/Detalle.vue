<script setup>
import { Link, router } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

const props = defineProps({ simulacion: Object, detalles: Array, creditos_total: Number });

// Excluir o incluir un curso cambia los créditos que reconoce el expediente:
// es una decisión académica y el servidor exige su motivo para la auditoría.
const excluyendo = ref(null);
const motivo = ref('');
const enviando = ref(false);
const errorMotivo = ref('');

const abrirToggle = (d) => {
    excluyendo.value = d;
    motivo.value = '';
    errorMotivo.value = '';
};

const confirmarToggle = () => {
    if (motivo.value.trim().length < 5) {
        errorMotivo.value = 'Indica el motivo (mínimo 5 caracteres).';
        return;
    }
    enviando.value = true;
    router.patch(`/simulaciones/${props.simulacion.id}/detalle/${excluyendo.value.id}`,
        { motivo: motivo.value.trim() },
        {
            preserveScroll: true,
            onFinish: () => { enviando.value = false; excluyendo.value = null; },
        });
};

// Descarga robusta: enlace temporal en la misma pestaña
const descargarArchivo = (url) => {
    const a = document.createElement('a');
    a.href = url;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
};
const descargarPdf = () => descargarArchivo(`/simulaciones/${props.simulacion.id}/pdf`);

const validando = ref(false);

const abrirValidar = () => {
    validando.value = true;
};

const confirmarValidar = () => {
    validando.value = false;
    router.patch(`/simulaciones/${props.simulacion.id}/validar`, {}, { preserveScroll: true });
};

const guardandoBorrador = ref(false);
const guardarBorrador = () => {
    guardandoBorrador.value = true;
    router.patch(`/simulaciones/${props.simulacion.id}/guardar-borrador`, {}, {
        preserveScroll: true,
        onFinish: () => { guardandoBorrador.value = false; }
    });
};

const descargarExcel = () => descargarArchivo(`/simulaciones/${props.simulacion.id}/excel`);
const descargarExcelOficial = () => descargarArchivo(`/simulaciones/${props.simulacion.id}/excel-oficial`);

// Búsqueda en la tabla de cursos
const busquedaDetalle = ref('');

// La tabla principal lista los cursos con equivalencia USIL seleccionados y convalidables.
const todasFilasConvalidadas = computed(() => props.detalles.filter((d) => d.curso_usil && (d.clasificacion === 'convalidable' || d.curso_externo)));
const filasConvalidadas = computed(() => {
    const q = busquedaDetalle.value.trim().toLowerCase();
    if (!q) return todasFilasConvalidadas.value;
    return todasFilasConvalidadas.value.filter(d =>
        (d.curso_externo || '').toLowerCase().includes(q) ||
        (d.curso_usil || '').toLowerCase().includes(q)
    );
});
const convalidados = computed(() => todasFilasConvalidadas.value.filter((d) => !d.excluido).length);

// El resto del récord (cursos no convalidables o desaprobados)
const MOTIVO = {
    desaprobado: 'Desaprobado',
    no_convalidable: 'No convalidable',
    convalidable: 'Sin curso USIL asignado',
};
const filasRestantes = computed(() => props.detalles.filter((d) => d.clasificacion !== 'convalidable' && d.curso_externo));
const sinAsignar = computed(() => filasRestantes.value.filter((d) => d.clasificacion === 'convalidable').length);
const totalEvaluados = computed(() => todasFilasConvalidadas.value.length + filasRestantes.value.length);
const verRestantes = ref(false);
</script>

<template>
    <div class="w-full pb-16">
        <VolverA href="/simulaciones" texto="Volver a Simulaciones" class="mb-4" />

        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#0036DC] opacity-20 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">
                                Expediente de Convalidación #{{ simulacion.id }}
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            {{ simulacion.estudiante }}
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 flex flex-wrap items-center gap-2">
                            <span class="font-mono bg-white/10 px-2 py-0.5 rounded-md border border-white/10 text-xs">{{ simulacion.documento }}</span>
                            <span class="text-blue-300">•</span>
                            <span>Destino: <b>{{ simulacion.carrera }}</b></span>
                            <span v-if="simulacion.origen" class="text-blue-300">•</span>
                            <span v-if="simulacion.origen">Origen: {{ simulacion.origen }}</span>
                        </p>
                        <p v-if="simulacion.documento_fuente" class="mt-2 flex items-center gap-1.5 text-xs text-blue-200">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <span>Fuente: {{ simulacion.documento_fuente }}</span>
                        </p>
                    </div>

                    <!-- Píldoras de Estado y Método -->
                    <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                        <span class="rounded-2xl bg-white/10 border border-white/20 px-3.5 py-2 text-xs font-semibold text-blue-100 backdrop-blur-md">
                            Mapeo manual
                        </span>
                        <span class="rounded-2xl px-4 py-2 text-xs font-extrabold uppercase tracking-wider shadow-sm"
                              :class="{
                                  'bg-amber-400 text-slate-900': simulacion.estado === 'borrador',
                                  'bg-[#0036DC] text-white': simulacion.estado === 'generada',
                                  'bg-emerald-400 text-slate-900': simulacion.estado === 'validada' || simulacion.estado === 'convalidada'
                              }">
                            {{ simulacion.estado }}
                        </span>
                    </div>
                </div>

                <!-- Tira de 4 Micro-KPIs -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Cursos Evaluados</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ totalEvaluados }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Récord seleccionado</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Convalidados</div>
                        <div class="text-2xl font-extrabold text-emerald-300 mt-1">{{ convalidados }}</div>
                        <div class="text-[10px] text-emerald-200/80 mt-0.5">Cursos reconocidos</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Créditos Totales</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ Number(creditos_total).toFixed(1) }} <span class="text-xs font-normal text-blue-200">cr.</span></div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Suma aprobada</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Sin Asignar</div>
                        <div class="text-2xl font-extrabold mt-1" :class="sinAsignar ? 'text-amber-300' : 'text-slate-300'">{{ sinAsignar }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">{{ sinAsignar ? 'Requieren revisión' : 'Mapeo completo' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas informativas -->
        <div v-if="sinAsignar" class="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 shadow-xs">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div>
                Hay <strong>{{ sinAsignar }}</strong> curso(s) aprobado(s) en el récord de origen sin un curso USIL asignado (no suman créditos).
                <Link v-if="!simulacion.convalidada" :href="`/simulaciones/${simulacion.id}/editar`" class="font-bold underline ml-1 text-amber-900">Editar el mapeo</Link>
                <span v-else class="ml-1">Para corregirlo hay que anular la convalidación y generar un expediente nuevo.</span>
            </div>
        </div>

        <div v-if="simulacion.convalidada" class="mb-6 flex items-start gap-3 rounded-2xl border border-slate-300 bg-slate-50 p-4 text-xs text-slate-600 shadow-xs">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
            <div>Expediente cerrado: sustenta un memorándum emitido. Una corrección exige anular la convalidación y evaluar de nuevo.</div>
        </div>

        <!-- Barra de Acciones de la Simulación y Búsqueda -->
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Cursos Convalidados</h2>
                <div class="relative w-64">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input v-model="busquedaDetalle" type="search" placeholder="Filtrar cursos..."
                           class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#0036DC] focus:ring-[#0036DC]" />
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <button v-if="simulacion.estado === 'borrador'" @click="guardarBorrador" :disabled="guardandoBorrador" type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-slate-700 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50 transition-colors shadow-2xs">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" /></svg>
                    <span>{{ guardandoBorrador ? 'Guardando...' : 'Guardar borrador' }}</span>
                </button>
                <button v-if="['borrador', 'generada'].includes(simulacion.estado)" @click="abrirValidar" type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2 text-xs font-bold text-white hover:from-emerald-700 hover:to-teal-700 transition-all shadow-2xs">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <span>Validar Simulación</span>
                </button>
                <Link v-if="!simulacion.convalidada" :href="`/simulaciones/${simulacion.id}/editar`"
                      class="inline-flex items-center gap-1.5 rounded-xl border border-[#0036DC] bg-white px-4 py-2 text-xs font-bold text-[#0036DC] hover:bg-blue-50 transition-colors shadow-2xs">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                    <span>Editar mapeo</span>
                </Link>
            </div>
        </div>

        <!-- Tabla de Cursos Convalidados -->
        <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs mb-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50/90 text-left text-[11px] uppercase tracking-wider text-slate-500 font-extrabold">
                        <tr>
                            <th class="px-6 py-3.5">Curso de Origen</th>
                            <th class="px-6 py-3.5 text-center">Nota</th>
                            <th class="px-6 py-3.5">Convalida con (USIL)</th>
                            <th class="px-6 py-3.5 text-right">Créditos</th>
                            <th class="px-6 py-3.5 text-center" title="Desmarca un curso para excluirlo del total de créditos">Incluir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="d in filasConvalidadas" :key="d.id" :class="d.excluido ? 'opacity-40 bg-slate-50/50' : 'hover:bg-slate-50/70'" class="transition-colors">
                            <td class="px-6 py-3.5 font-bold text-slate-800">{{ d.curso_externo }}</td>
                            <td class="px-6 py-3.5 text-center">
                                <span v-if="d.nota" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono font-bold">
                                    {{ d.nota }}
                                </span>
                                <span v-else class="text-slate-300">—</span>
                            </td>
                            <td class="px-6 py-3.5">
                                <div class="font-bold text-[#00205B]">{{ d.curso_usil }}</div>
                            </td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-700">{{ Number(d.creditos).toFixed(1) }}</td>
                            <td class="px-6 py-3.5 text-center">
                                <input type="checkbox" :checked="!d.excluido" :disabled="simulacion.convalidada"
                                       @click.prevent="abrirToggle(d)"
                                       :title="simulacion.convalidada ? 'Expediente cerrado por convalidación' : ''"
                                       class="h-4 w-4 rounded border-slate-300 text-[#00205B] focus:ring-[#0036DC] disabled:opacity-40 cursor-pointer" />
                            </td>
                        </tr>
                        <tr v-if="!filasConvalidadas.length">
                            <td colspan="5" class="px-6 py-8 text-center text-slate-400">
                                Ningún curso tiene equivalencia USIL todavía. Usa «Editar mapeo» para asignarlas.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="filasConvalidadas.length">
                        <tr class="bg-slate-50 font-bold">
                            <td colspan="3" class="px-6 py-3 text-right uppercase tracking-wider text-slate-600 text-[11px]">Total Créditos Reconocidos</td>
                            <td class="px-6 py-3 text-right font-mono text-sm text-[#00205B]">{{ Number(creditos_total).toFixed(1) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Cursos que no convalidan (Acordeón) -->
        <div v-if="filasRestantes.length" class="mb-8 bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs">
            <button type="button" @click="verRestantes = !verRestantes"
                    class="flex w-full items-center justify-between px-6 py-4 text-left hover:bg-slate-50/70 transition-colors">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-2">
                    <span>Asignaturas no convalidables</span>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">{{ filasRestantes.length }}</span>
                </span>
                <svg class="h-4 w-4 text-slate-400 transition-transform duration-300" :class="verRestantes ? 'rotate-180' : ''"
                     fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
            <div v-if="verRestantes" class="border-t border-slate-100 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50/90 text-left text-[11px] uppercase tracking-wider text-slate-500 font-extrabold">
                        <tr>
                            <th class="px-6 py-3">Curso de origen</th>
                            <th class="px-6 py-3">Nota</th>
                            <th class="px-6 py-3">Motivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="d in filasRestantes" :key="d.id" class="hover:bg-slate-50/70">
                            <td class="px-6 py-3 text-slate-700 font-medium">{{ d.curso_externo }}</td>
                            <td class="px-6 py-3 text-slate-500 font-mono">
                                {{ d.nota || (d.motivo ? '' : '—') }}
                                <div v-if="d.motivo" :class="d.nota ? 'mt-0.5 text-[11px] text-slate-400' : 'text-slate-500'">{{ d.motivo }}</div>
                            </td>
                            <td class="px-6 py-3">
                                <span class="rounded-lg px-2 py-0.5 text-[11px] font-bold"
                                      :class="d.clasificacion === 'convalidable' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-500'">
                                    {{ MOTIVO[d.clasificacion] || 'Sin clasificar' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Barra de Descargas / Exportación Oficial -->
        <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Exportar Dictamen de Simulación</h3>
                <p class="text-xs text-slate-400 mt-0.5">Descarga el informe oficial de convalidación en formato PDF o Excel.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <button @click="descargarPdf" type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#0036DC] transition-colors shadow-2xs">
                    <svg class="h-4 w-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Descargar PDF</span>
                </button>
                <button @click="descargarExcelOficial" type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Excel Oficial</span>
                </button>
                <button @click="descargarExcel" type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs">
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Excel ERP</span>
                </button>
            </div>
        </div>

        <!-- Diálogos de Confirmación -->
        <ConfirmDialog :open="!!excluyendo" :procesando="enviando"
                       :titulo="excluyendo?.excluido ? '¿Incluir este curso?' : '¿Excluir este curso?'"
                       :mensaje="excluyendo?.excluido
                           ? 'Volverá a sumar sus créditos al expediente. El motivo queda en la auditoría.'
                           : 'Dejará de sumar sus créditos al expediente. El motivo queda en la auditoría.'"
                       :texto-confirmar="excluyendo?.excluido ? 'Incluir' : 'Excluir'"
                       tono="aviso"
                       @cancelar="excluyendo = null" @confirmar="confirmarToggle">
            <p class="mb-3 font-bold text-slate-800 text-xs">{{ excluyendo?.curso_externo }} → {{ excluyendo?.curso_usil }}</p>
            <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600" for="motivo-exclusion">Motivo</label>
            <textarea id="motivo-exclusion" v-model="motivo" rows="3" maxlength="300"
                      class="w-full rounded-xl border-slate-200 text-xs font-medium p-2.5 focus:border-[#0036DC] focus:ring-[#0036DC]"
                      placeholder="Ej.: el sílabo no cubre las competencias del curso USIL."></textarea>
            <p v-if="errorMotivo" class="mt-1 text-xs font-bold text-red-600">{{ errorMotivo }}</p>
        </ConfirmDialog>
        
        <ConfirmDialog :open="validando"
                       titulo="Validar Simulación"
                       mensaje="¿Estás seguro de que deseas validar (aceptar) esta simulación?"
                       texto-confirmar="Aceptar" tono="exito"
                       @cancelar="validando = false" @confirmar="confirmarValidar">
        </ConfirmDialog>
    </div>
</template>

