<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import logoImg from '../../../images/usil_logo.jpg';

const props = defineProps({
    postulante: Object,
    destinos: { type: Array, default: () => [] },
    timeline: { type: Array, default: () => [] },
    simulaciones: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash?.status ?? null);

const toastVisible = ref(false);
const toastMensaje = ref('');
let toastTimer = null;

const mostrarToast = (msg) => {
    if (toastTimer) clearTimeout(toastTimer);
    toastMensaje.value = msg;
    toastVisible.value = true;
    toastTimer = setTimeout(() => {
        toastVisible.value = false;
    }, 4500);
};

watch(flash, (nuevo) => {
    if (nuevo) mostrarToast(nuevo);
}, { immediate: true });

const logout = () => router.post('/portal/logout');

const iniciales = computed(() => {
    const n = props.postulante?.nombre || '';
    const partes = n.replace(',', '').split(/\s+/).filter(Boolean);
    if (!partes.length) return 'US';
    return (partes[0][0] + (partes[1]?.[0] || '')).toUpperCase();
});

const ESTADO_SIM = {
    generada: 'Dictamen Preliminar',
    confirmada: 'Dictamen Oficial Aprobado',
    borrador: 'En Elaboración',
    enviada: 'Enviada para Revisión',
};
</script>

<template>
    <div class="min-h-screen bg-[#F4F6F9] text-slate-800 font-sans antialiased selection:bg-blue-100 selection:text-blue-900">
        <!-- TOAST FLOTANTE -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-4">
            <div v-if="toastVisible"
                 class="fixed top-5 right-5 z-50 flex items-center gap-3 rounded-2xl bg-slate-900 px-4 py-3 text-xs font-semibold text-white shadow-2xl border border-slate-700">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">✓</span>
                <span>{{ toastMensaje }}</span>
                <button type="button" @click="toastVisible = false" class="ml-2 text-slate-400 hover:text-white">✕</button>
            </div>
        </Transition>

        <!-- TOP BAR / NAVBAR -->
        <header class="sticky top-0 z-40 bg-[#00205B] text-white shadow-md border-b border-[#00195A]">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 sm:px-6 py-3.5">
                <div class="flex items-center gap-3.5">
                    <img :src="logoImg" alt="USIL" class="h-9 w-auto object-contain rounded-xl p-0.5 bg-white shadow-xs" />
                    <div>
                        <h1 class="text-sm font-bold tracking-tight text-white flex items-center gap-2">
                            <span>Portal del Postulante</span>
                            <span class="hidden sm:inline-block px-2 py-0.5 rounded-full bg-[#FFB81C]/20 border border-[#FFB81C]/40 text-[10px] font-bold text-[#FFB81C]">
                                Traslado Externo
                            </span>
                        </h1>
                        <p class="text-[11px] text-blue-200 font-medium">Seguimiento y Estado de Convalidación</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden sm:flex items-center gap-2.5 pl-3 border-l border-white/15">
                        <div class="text-right leading-tight">
                            <span class="block text-xs font-bold text-white max-w-[200px] truncate">{{ postulante.nombre }}</span>
                            <span class="block font-mono text-[10px] text-blue-200">{{ postulante.codigo }}</span>
                        </div>
                        <div class="grid h-8 w-8 place-items-center rounded-full bg-white/15 text-xs font-bold text-white border border-white/20">
                            {{ iniciales }}
                        </div>
                    </div>

                    <button type="button" @click="logout"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 hover:bg-white/20 px-3.5 py-1.5 text-xs font-bold text-white transition-colors cursor-pointer shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                        <span>Cerrar sesión</span>
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 sm:px-6 py-6 sm:py-8 space-y-6">
            <!-- HERO BIENVENIDA -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#00195A] via-[#00205B] to-[#012085] p-6 sm:p-8 text-white shadow-lg">
                <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/15 border border-white/20 text-[11px] font-bold text-blue-100 mb-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                            <span>Expediente en Proceso</span>
                        </span>
                        <h2 class="text-xl sm:text-2xl font-black text-white leading-tight">
                            Hola, {{ postulante.nombre }}
                        </h2>
                        <p class="text-xs sm:text-sm text-blue-100 max-w-xl leading-relaxed">
                            Aquí puedes consultar en tiempo real el avance de tu evaluación de convalidación académica hacia USIL.
                        </p>
                    </div>

                    <div class="shrink-0 bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/20 text-center sm:text-right min-w-[160px]">
                        <span class="block text-[10px] font-extrabold uppercase tracking-widest text-blue-200">Código de Solicitud</span>
                        <span class="block font-mono text-base sm:text-lg font-black text-white tracking-wider mt-0.5">{{ postulante.codigo }}</span>
                        <span class="inline-block mt-1 text-[11px] font-semibold text-blue-100 bg-white/10 px-2 py-0.5 rounded-md">
                            Ciclo {{ postulante.ciclo_postulacion || '2026' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- ALERTA DE OBSERVACIÓN (SI APLICA) -->
            <div v-if="postulante.revision_estado === 'observada'"
                 class="rounded-2xl border border-rose-200 bg-rose-50/90 p-4 sm:p-5 shadow-xs flex items-start gap-3.5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-rose-900">Documentación Observada por Admisión</h3>
                    <p class="mt-1 text-xs font-semibold text-rose-950 leading-relaxed">
                        {{ postulante.revision_observaciones || 'Comunícate con tu asesor de admisión para subsanar los requisitos señalados.' }}
                    </p>
                </div>
            </div>

            <!-- PROCESO Y TIMELINE DE CONVALIDACIÓN -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xs">
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#2E75B6]"></span>
                            <span>Seguimiento del Proceso de Convalidación</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Etapas oficiales desde el registro hasta el dictamen académico.</p>
                    </div>
                </div>

                <div class="relative">
                    <ol class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <li v-for="(t, i) in timeline" :key="i"
                            :class="[
                                t.estado === 'completado' ? 'border-emerald-200 bg-emerald-50/40' :
                                t.estado === 'actual' ? 'border-[#2E75B6] bg-blue-50/50 ring-2 ring-blue-100' :
                                t.estado === 'rechazado' ? 'border-rose-200 bg-rose-50/50' : 'border-slate-200 bg-slate-50/40'
                            ]"
                            class="relative rounded-2xl border p-4 sm:p-5 transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <span :class="{
                                        'bg-emerald-600 text-white': t.estado === 'completado',
                                        'bg-[#1F3864] text-white': t.estado === 'actual',
                                        'bg-slate-200 text-slate-500': t.estado === 'pendiente',
                                        'bg-rose-600 text-white': t.estado === 'rechazado',
                                    }" class="flex h-7 w-7 items-center justify-center rounded-xl text-xs font-bold shadow-2xs">
                                        <span v-if="t.estado === 'completado'">✓</span>
                                        <span v-else-if="t.estado === 'rechazado'">✕</span>
                                        <span v-else>{{ i + 1 }}</span>
                                    </span>

                                    <span v-if="t.estado === 'completado'" class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                                        Completado
                                    </span>
                                    <span v-else-if="t.estado === 'actual'" class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 animate-pulse">
                                        En Curso
                                    </span>
                                    <span v-else-if="t.estado === 'rechazado'" class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-rose-100 text-rose-800">
                                        Observado
                                    </span>
                                    <span v-else class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-100 text-slate-400">
                                        Pendiente
                                    </span>
                                </div>

                                <h4 class="text-xs font-bold text-slate-900 leading-snug">{{ t.label }}</h4>
                                <p class="mt-1 text-[11px] text-slate-500 leading-relaxed">{{ t.detalle }}</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>

            <!-- DETALLES DE PROCEDENCIA Y DESTINO (GRID) -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- CARRERAS DESTINO EN USIL -->
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100 mb-4">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-[#1F3864]">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Carrera(s) Destino USIL</h3>
                                <p class="text-[11px] text-slate-400">Programas académicos de interés</p>
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            <div v-for="(d, i) in (destinos.length ? destinos : [{ carrera: postulante.carrera_destino || '—' }])" :key="i"
                                 class="p-3.5 rounded-2xl bg-blue-50/50 border border-blue-100/80 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <span class="h-2 w-2 rounded-full bg-[#2E75B6]"></span>
                                    <span class="text-xs font-bold text-slate-900">{{ d.carrera }}</span>
                                </div>
                                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-white border border-blue-200/60 text-[#1F3864]">
                                    USIL
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-500 flex items-center justify-between">
                        <span>Ciclo de Ingreso:</span>
                        <strong class="text-slate-800">{{ postulante.ciclo_postulacion || '2026-1' }}</strong>
                    </div>
                </div>

                <!-- PROCEDENCIA ACADÉMICA -->
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100 mb-4">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Institución de Procedencia</h3>
                                <p class="text-[11px] text-slate-400">Universidad o Instituto de origen</p>
                            </div>
                        </div>

                        <dl class="space-y-3 text-xs">
                            <div class="p-3 rounded-2xl bg-slate-50/80 border border-slate-200/70">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Institución:</dt>
                                <dd class="font-bold text-slate-800 text-xs mt-0.5">{{ postulante.institucion || '—' }}</dd>
                            </div>

                            <div class="p-3 rounded-2xl bg-slate-50/80 border border-slate-200/70">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Carrera de Origen:</dt>
                                <dd class="font-semibold text-slate-800 text-xs mt-0.5">{{ postulante.carrera_externa || '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="postulante.observaciones" class="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-500">
                        <span class="font-bold text-slate-700">Nota:</span> {{ postulante.observaciones }}
                    </div>
                </div>
            </div>

            <!-- RESULTADOS DE CONVALIDACIÓN / DICTAMEN ACADÉMICO -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-6 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            <span>Resultados de Convalidación Académica</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Cursos y créditos homologados por la comisión evaluadora de la carrera.
                        </p>
                    </div>

                    <span v-if="simulaciones.length" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800">
                        <span>Dictamen Generado</span>
                    </span>
                </div>

                <!-- SI HAY RESULTADOS EVALUADOS -->
                <div v-if="simulaciones.length" class="space-y-6">
                    <div v-for="s in simulaciones" :key="s.id" class="rounded-2xl border border-slate-200 bg-slate-50/40 p-5 sm:p-6 space-y-5">
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-200/80">
                            <div>
                                <span class="inline-block text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 mb-1">
                                    {{ ESTADO_SIM[s.estado] || s.estado }}
                                </span>
                                <h4 class="text-sm font-bold text-slate-900">Preconvalidación #{{ s.id }}</h4>
                                <p class="text-[11px] text-slate-400">Emitido el {{ s.fecha }}</p>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="rounded-xl bg-white border border-slate-200 px-3.5 py-2 text-center shadow-2xs">
                                    <span class="block text-[10px] font-bold uppercase text-slate-400">Cursos</span>
                                    <span class="font-extrabold text-slate-800 text-sm">{{ s.cursos }}</span>
                                </div>
                                <div class="rounded-xl bg-[#1F3864] text-white px-4 py-2 text-center shadow-xs">
                                    <span class="block text-[10px] font-bold uppercase text-blue-200">Créditos Reconocidos</span>
                                    <span class="font-black text-sm sm:text-base">{{ s.creditos }} cr.</span>
                                </div>
                            </div>
                        </div>

                        <!-- TABLA DE CURSOS CONVALIDADOS -->
                        <div v-if="s.convalidados.length" class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                            <table class="min-w-full text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                    <tr>
                                        <th class="py-3 px-4">Asignatura de Origen</th>
                                        <th class="py-3 px-4">Curso Homologado en USIL</th>
                                        <th class="py-3 px-4 text-right">Créditos</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr v-for="(c, ci) in s.convalidados" :key="ci" class="hover:bg-slate-50/60 transition-colors">
                                        <td class="py-2.5 px-4 font-medium text-slate-600">{{ c.origen }}</td>
                                        <td class="py-2.5 px-4 font-bold text-slate-900 flex items-center gap-1.5">
                                            <span class="text-emerald-600 font-bold">✓</span>
                                            <span>{{ c.usil }}</span>
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-[#1F3864]">{{ c.creditos }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ASIGNATURAS NO CONVALIDABLES -->
                        <div v-if="s.no_convalidados && s.no_convalidados.length" class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-4">
                            <h5 class="text-[11px] font-bold uppercase tracking-wider text-amber-900 mb-2 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                </svg>
                                <span>Asignaturas No Convalidables</span>
                            </h5>
                            <ul class="space-y-1.5 text-xs text-amber-950">
                                <li v-for="(nc, nci) in s.no_convalidados" :key="nci" class="flex items-start gap-2">
                                    <span class="text-amber-600 font-bold mt-0.5">•</span>
                                    <span><strong class="text-slate-800">{{ nc.origen }}</strong>: <span class="text-amber-800">{{ nc.motivo }}</span></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- EMPTY STATE ELEGANTE (CUANDO AÚN ESTÁ EN EVALUACIÓN) -->
                <div v-else class="text-center py-10 px-4 rounded-2xl bg-slate-50/60 border border-dashed border-slate-200">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100 text-[#1F3864] mb-3 shadow-2xs">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-bold text-slate-800">Evaluación Académica en Proceso</h4>
                    <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
                        Tu récord académico y documentación están siendo evaluados por la comisión académica de la carrera. En cuanto concluya el dictamen, podrás ver aquí el detalle de las asignaturas convalidadas.
                    </p>
                </div>
            </div>
        </main>

        <!-- FOOTER DISCRETO -->
        <footer class="mt-12 border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-400">
            <div class="mx-auto max-w-5xl px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <span>© {{ new Date().getFullYear() }} Universidad San Ignacio de Loyola. Todos los derechos reservados.</span>
                <span class="font-medium text-slate-500">Dirección de Admisiones y Convalidaciones</span>
            </div>
        </footer>
    </div>
</template>
