<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps({ simulacion: Object, detalles: Array, creditos_total: Number });

const toggle = (id) => router.patch(`/simulaciones/${props.simulacion.id}/detalle/${id}`, {}, { preserveScroll: true });

// Descarga robusta: enlace temporal en la misma pestaña (evita bloqueo de pop-ups y pestañas en blanco).
const descargarArchivo = (url) => {
    const a = document.createElement('a');
    a.href = url;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
};
const descargarPdf = () => descargarArchivo(`/simulaciones/${props.simulacion.id}/pdf`);
const descargarExcel = () => descargarArchivo(`/simulaciones/${props.simulacion.id}/excel`);

// La tabla principal lista los cursos con equivalencia USIL.
const filasConvalidadas = computed(() => props.detalles.filter((d) => d.curso_usil));
const convalidados = computed(() => props.detalles.filter((d) => d.curso_usil && !d.excluido).length);

// El resto del récord: por qué los demás cursos no suman créditos. Sin esto, la
// cabecera dice "41 evaluados" y la tabla muestra 3 sin explicar la diferencia.
const MOTIVO = {
    desaprobado: 'Desaprobado',
    no_convalidable: 'No convalidable',
    convalidable: 'Sin curso USIL asignado',
};
const filasRestantes = computed(() => props.detalles.filter((d) => ! d.curso_usil));
const sinAsignar = computed(() => filasRestantes.value.filter((d) => d.clasificacion === 'convalidable').length);
const verRestantes = ref(false);
</script>

<template>
    <div class="max-w-5xl">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">{{ simulacion.estudiante }}</h1>
                <p class="text-sm text-slate-500">
                    {{ simulacion.documento }} · Destino: {{ simulacion.carrera }}
                    <span v-if="simulacion.origen"> · Origen: {{ simulacion.origen }}</span>
                </p>
                <p v-if="simulacion.documento_fuente" class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-400">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    Extraído del documento: {{ simulacion.documento_fuente }}
                </p>
            </div>
            <!-- Estado = píldora; el método es un dato, no una acción: dos píldoras juntas se leían como un selector. -->
            <div class="flex items-center gap-2 text-xs">
                <span class="text-slate-400">Mapeo {{ simulacion.metodo === 'ia' ? 'con IA' : 'manual' }}</span>
                <span class="rounded-full bg-blue-100 px-3 py-1 font-medium capitalize text-blue-700">{{ simulacion.estado }}</span>
            </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-[#1F3864]">{{ detalles.length }}</p>
                <p class="text-xs text-slate-500">Cursos evaluados</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-green-600">{{ convalidados }}</p>
                <p class="text-xs text-slate-500">Convalidados</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-[#2E75B6]">{{ Number(creditos_total).toFixed(1) }}</p>
                <p class="text-xs text-slate-500">Créditos reconocidos</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold" :class="sinAsignar ? 'text-amber-500' : 'text-slate-400'">{{ sinAsignar }}</p>
                <p class="text-xs text-slate-500">Sin asignar</p>
            </div>
        </div>

        <p v-if="sinAsignar" class="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
            <span>
                Hay <strong>{{ sinAsignar }}</strong> curso(s) aprobado(s) en el récord de origen sin un curso USIL asignado; no suman créditos.
                <Link :href="`/simulaciones/${simulacion.id}/editar`" class="font-medium underline">Editar el mapeo</Link> para revisarlos.
            </span>
        </p>

        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Cursos a convalidar</h2>
            <Link :href="`/simulaciones/${simulacion.id}/editar`"
                  class="inline-flex items-center gap-1.5 rounded-md border border-[#2E75B6] px-3 py-1.5 text-xs font-medium text-[#2E75B6] hover:bg-blue-50">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                Editar mapeo
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Curso de origen</th>
                        <th class="px-4 py-3 font-semibold">Nota</th>
                        <th class="px-4 py-3 font-semibold">Convalida con (USIL)</th>
                        <th class="px-4 py-3 text-right font-semibold">Créditos</th>
                        <th class="px-4 py-3 text-center font-semibold" title="Desmarca un curso para excluirlo del total de créditos">Incluir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="d in filasConvalidadas" :key="d.id" :class="d.excluido ? 'opacity-40' : ''" class="hover:bg-slate-50/70">
                        <td class="px-4 py-2 text-slate-700">{{ d.curso_externo }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ d.nota || '—' }}</td>
                        <td class="px-4 py-2 font-medium text-slate-800">
                            {{ d.curso_usil }}
                            <span v-if="d.confianza" class="ml-1 text-xs text-slate-400">({{ Number(d.confianza).toFixed(0) }}%)</span>
                        </td>
                        <td class="px-4 py-2 text-right text-slate-600">{{ Number(d.creditos).toFixed(1) }}</td>
                        <td class="px-4 py-2 text-center">
                            <input type="checkbox" :checked="!d.excluido" @change="toggle(d.id)"
                                   class="rounded border-slate-300 text-[#2E75B6]" />
                        </td>
                    </tr>
                    <tr v-if="!filasConvalidadas.length">
                        <td colspan="5" class="px-4 py-6 text-center text-slate-400">
                            Ningún curso tiene equivalencia USIL todavía. Usa «Editar mapeo» para asignarlas.
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="filasConvalidadas.length">
                    <tr class="bg-slate-50">
                        <td colspan="3" class="px-4 py-2 text-right font-medium text-slate-600">Créditos reconocidos</td>
                        <td class="px-4 py-2 text-right font-semibold text-[#1F3864]">{{ Number(creditos_total).toFixed(1) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Resto del récord: cierra la brecha entre "N cursos evaluados" y las filas de arriba. -->
        <div v-if="filasRestantes.length" class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <button type="button" @click="verRestantes = ! verRestantes"
                    class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-slate-50">
                <span class="text-sm font-medium text-slate-600">
                    Cursos que no convalidan
                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ filasRestantes.length }}</span>
                </span>
                <svg class="h-4 w-4 text-slate-400 transition-transform" :class="verRestantes ? 'rotate-180' : ''"
                     fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <table v-if="verRestantes" class="min-w-full divide-y divide-slate-200 border-t border-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Curso de origen</th>
                        <th class="px-4 py-2 font-semibold">Nota</th>
                        <th class="px-4 py-2 font-semibold">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="d in filasRestantes" :key="d.id">
                        <td class="px-4 py-2 text-slate-700">{{ d.curso_externo }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ d.nota || '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs"
                                  :class="d.clasificacion === 'convalidable' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500'">
                                {{ MOTIVO[d.clasificacion] || 'Sin clasificar' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button @click="descargarPdf" class="inline-flex items-center gap-2 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E75B6]">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Descargar PDF
            </button>
            <button @click="descargarExcel" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Descargar Excel
            </button>
            <Link href="/simulaciones" class="ml-auto text-sm text-slate-500 hover:text-slate-700 hover:underline">Volver a Simulaciones</Link>
        </div>

        <p class="mt-3 text-xs text-slate-400">
            La convalidación oficial y su memorándum se emiten desde el módulo Convalidaciones.
        </p>
    </div>
</template>
