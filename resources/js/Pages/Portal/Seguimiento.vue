<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ postulante: Object, destinos: { type: Array, default: () => [] }, timeline: { type: Array, default: () => [] }, simulaciones: Array });
const page = usePage();
const flash = computed(() => page.props.flash?.status ?? null);

const logout = () => router.post('/portal/logout');

const ESTADO_SIM = { generada: 'Generada', confirmada: 'Confirmada', borrador: 'Borrador', enviada: 'Enviada' };
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Cabecera -->
        <header class="border-b border-slate-200 bg-[#1F3864] text-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="grid h-9 w-9 place-items-center rounded-lg bg-white/15 text-[11px] font-bold">USIL</div>
                    <div class="leading-tight">
                        <p class="text-sm font-semibold">Portal del Postulante</p>
                        <p class="text-xs text-blue-200">Seguimiento de convalidación</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden text-blue-100 sm:inline">{{ postulante.nombre }}</span>
                    <button @click="logout" class="rounded-md bg-white/10 px-3 py-1.5 font-medium hover:bg-white/20">Salir</button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-8">
            <div v-if="flash" class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash }}</div>

            <div class="mb-2">
                <h1 class="text-2xl font-semibold text-[#1F3864]">Hola, {{ postulante.nombre }}</h1>
                <p class="mt-1 text-sm text-slate-500">Solicitud <span class="font-mono font-medium text-slate-700">{{ postulante.codigo }}</span></p>
            </div>

            <!-- Documentación observada por Admisión: qué debe corregir el postulante -->
            <div v-if="postulante.revision_estado === 'observada'"
                 class="mb-6 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
                <p class="font-medium">Documentación observada</p>
                <p class="mt-1">{{ postulante.revision_observaciones || 'Comunícate con tu asesor de admisión para más detalle.' }}</p>
            </div>

            <!-- Process Timeline: avance real del proceso de convalidación -->
            <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-sm font-semibold text-slate-700">Seguimiento del proceso</h2>

                <ol class="relative">
                    <li v-for="(t, i) in timeline" :key="i" class="flex gap-4 pb-6 last:pb-0">
                        <!-- Columna del hito + conector -->
                        <div class="relative flex flex-col items-center">
                            <span class="z-10 grid h-8 w-8 place-items-center rounded-full text-xs font-bold ring-4 ring-white"
                                  :class="{
                                      'bg-green-500 text-white': t.estado === 'completado',
                                      'bg-[#2E75B6] text-white animate-pulse': t.estado === 'actual',
                                      'bg-slate-200 text-slate-400': t.estado === 'pendiente',
                                      'bg-red-500 text-white': t.estado === 'rechazado',
                                  }">
                                <span v-if="t.estado === 'completado'">✓</span>
                                <span v-else-if="t.estado === 'rechazado'">✕</span>
                                <span v-else>{{ i + 1 }}</span>
                            </span>
                            <span v-if="i < timeline.length - 1"
                                  class="absolute top-8 h-full w-0.5"
                                  :class="t.estado === 'completado' ? 'bg-green-400' : 'bg-slate-200'"></span>
                        </div>
                        <!-- Contenido del hito -->
                        <div class="pt-0.5">
                            <p class="text-sm font-semibold"
                               :class="{
                                   'text-slate-800': t.estado === 'completado' || t.estado === 'actual',
                                   'text-slate-400': t.estado === 'pendiente',
                                   'text-red-700': t.estado === 'rechazado',
                               }">
                                {{ t.label }}
                                <span v-if="t.estado === 'actual'" class="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-medium text-blue-700">En curso</span>
                            </p>
                            <p class="mt-0.5 text-xs" :class="t.estado === 'pendiente' ? 'text-slate-400' : 'text-slate-500'">{{ t.detalle }}</p>
                        </div>
                    </li>
                </ol>
            </div>

            <!-- Carreras solicitadas (una o más) -->
            <div v-if="destinos.length" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Carreras solicitadas</h2>
                <ul class="space-y-2">
                    <li v-for="(d, i) in destinos" :key="i" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <span class="font-medium text-slate-700">{{ d.carrera }}</span>
                    </li>
                </ul>
            </div>

            <!-- Datos de la solicitud -->
            <div class="mb-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Datos de la solicitud</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-400">Carrera destino (USIL)</dt><dd class="font-medium text-slate-700">{{ postulante.carrera_destino || '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-400">Institución de origen</dt><dd class="text-slate-700">{{ postulante.institucion || '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-400">Carrera de origen</dt><dd class="text-slate-700">{{ postulante.carrera_externa || '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-400">Ciclo de postulación</dt><dd class="text-slate-700">{{ postulante.ciclo_postulacion || '—' }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Observaciones</h2>
                    <p class="whitespace-pre-line text-sm text-slate-600">{{ postulante.observaciones || 'Sin observaciones por el momento.' }}</p>
                </div>
            </div>

            <!-- Simulaciones / resultados -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">Resultados de convalidación</h2>
                <div v-if="simulaciones.length" class="space-y-2">
                    <div v-for="s in simulaciones" :key="s.id" class="flex items-center justify-between rounded-lg border border-slate-200 p-3 text-sm">
                        <div>
                            <p class="font-medium text-slate-700">Simulación #{{ s.id }}</p>
                            <p class="text-xs text-slate-400">{{ s.fecha }} · {{ ESTADO_SIM[s.estado] || s.estado }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="font-semibold text-[#1F3864]">{{ s.creditos }} créditos</p>
                                <p class="text-xs text-slate-400">{{ s.cursos }} cursos reconocidos</p>
                            </div>
                            <div v-if="s.pdf_url" class="flex items-center gap-2">
                                <a :href="s.pdf_url" target="_blank" rel="noopener"
                                   class="shrink-0 rounded-lg bg-[#1F3864] px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#2E75B6]">
                                    Ver documento
                                </a>
                                <a :href="`${s.pdf_url}?download=1`" target="_blank" rel="noopener"
                                   class="shrink-0 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-semibold text-green-700 shadow-sm transition hover:bg-green-100">
                                    Descargar PDF
                                </a>
                            </div>
                            <p v-else class="w-28 shrink-0 text-right text-xs text-slate-400">
                                Documento no disponible
                            </p>
                        </div>
                    </div>
                </div>
                <p v-else class="py-4 text-center text-sm text-slate-400">Aún no hay resultados de convalidación para tu solicitud.</p>
            </div>
        </main>
    </div>
</template>
