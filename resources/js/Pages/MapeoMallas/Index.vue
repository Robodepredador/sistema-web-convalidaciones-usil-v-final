<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ mapeos: Array });
</script>

<template>
    <div class="max-w-6xl mx-auto pb-10">
        <!-- Banner Header -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] shadow-xl">
            <!-- Decorative background elements -->
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-20 rounded-full blur-2xl"></div>
            
            <div class="relative z-10 px-8 py-10 sm:p-12 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 mb-4 rounded-full bg-white/10 border border-white/20 backdrop-blur-md">
                        <svg class="w-4 h-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5zm0 9.75h2.25A2.25 2.25 0 0010.5 18v-2.25a2.25 2.25 0 00-2.25-2.25H6a2.25 2.25 0 00-2.25 2.25V18A2.25 2.25 0 006 20.25zm9.75-9.75H18a2.25 2.25 0 002.25-2.25V6A2.25 2.25 0 0018 3.75h-2.25A2.25 2.25 0 0013.5 6v2.25a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        <span class="text-xs font-medium tracking-wide text-blue-100 uppercase">Gestión Académica</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">Mapeo de equivalencias</h1>
                    <p class="mt-3 text-base text-blue-100/90 leading-relaxed max-w-xl">
                        Establece el criterio entre la malla de una institución externa y un plan USIL. 
                        Tus mapeos agilizarán futuras convalidaciones como primeras sugerencias.
                    </p>
                </div>
                <Link href="/mapeo-mallas/crear"
                      class="group relative inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-bold text-[#1F3864] shadow-lg transition-all duration-300 hover:scale-105 hover:bg-blue-50 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-white/30">
                    <svg class="w-5 h-5 transition-transform duration-300 group-hover:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Nuevo mapeo
                </Link>
            </div>
        </div>

        <!-- Table Section -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200/60 overflow-hidden relative">
            <!-- Subtle top highlight -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-blue-100 to-transparent"></div>
            
            <div class="overflow-x-auto p-4 sm:p-6">
                <table class="min-w-full border-separate border-spacing-y-3">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 font-bold px-4">
                            <th class="px-6 py-2">Institución de origen</th>
                            <th class="px-6 py-2">Carrera USIL destino</th>
                            <th class="px-6 py-2 text-center">Equivalencias</th>
                            <th class="px-6 py-2 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(m, i) in mapeos" :key="i" 
                            class="group relative bg-white transition-all duration-300 hover:shadow-md hover:bg-slate-50/50 rounded-2xl">
                            <!-- Card Background for tr via tds -->
                            <td class="px-6 py-5 rounded-l-2xl border-y border-l border-slate-100 group-hover:border-blue-100/50">
                                <div class="flex items-start gap-4">
                                    <div class="hidden sm:flex mt-1 h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-50 to-blue-50 text-[#2E75B6] ring-1 ring-inset ring-blue-100/50">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.315 48.315 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" /></svg>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800 text-base mb-0.5">{{ m.institucion }}</div>
                                        <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                                            <span>{{ m.carrera_externa }}</span>
                                            <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                            <span>Malla {{ m.anio_externa }}</span>
                                            <span v-if="!m.malla_externa_vigente"
                                                  class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 ring-1 ring-inset ring-amber-500/20 uppercase tracking-wide">
                                                Plan anterior
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5 border-y border-slate-100 group-hover:border-blue-100/50">
                                <div class="font-semibold text-[#1F3864] text-base mb-0.5">{{ m.carrera_usil }}</div>
                                <div class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500">
                                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                    Plan {{ m.plan_usil }}
                                </div>
                            </td>
                            <td class="px-6 py-5 border-y border-slate-100 group-hover:border-blue-100/50 text-center">
                                <div class="inline-flex items-center justify-center min-w-[3rem] px-3 py-1.5 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100/60 shadow-sm">
                                    <span class="text-sm font-bold text-[#2E75B6]">{{ m.equivalencias }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-5 rounded-r-2xl border-y border-r border-slate-100 group-hover:border-blue-100/50 text-right">
                                <Link :href="`/mapeo-mallas/crear?malla_externa_id=${m.malla_externa_id}&carrera_usil_id=${m.carrera_usil_id}`"
                                      class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-[#2E75B6] transition-colors hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-[#2E75B6]/20">
                                    Continuar
                                    <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                                </Link>
                            </td>
                        </tr>
                        <!-- Empty State -->
                        <tr v-if="!mapeos.length">
                            <td colspan="4" class="px-6 py-20 rounded-2xl border border-slate-100 bg-slate-50/50">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100/50">
                                        <svg class="h-8 w-8 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z" /></svg>
                                    </div>
                                    <h3 class="mb-1 text-lg font-semibold text-slate-800">No hay mapeos registrados</h3>
                                    <p class="max-w-md text-sm text-slate-500 mb-6 leading-relaxed">
                                        Empieza por una institución de la que recibas traslados con frecuencia. No hace falta cubrir toda la malla: lo que declares ya ayuda al evaluar.
                                    </p>
                                    <Link href="/mapeo-mallas/crear" class="inline-flex items-center gap-2 rounded-xl bg-[#1F3864] px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-[#2E75B6] hover:shadow-lg">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                        Crear mi primer mapeo
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
