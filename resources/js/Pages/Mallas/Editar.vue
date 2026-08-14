<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';

const props = defineProps({ malla: Object });

const form = useForm({
    anio: props.malla.anio,
    version: props.malla.version,
    modalidad: props.malla.modalidad,
    periodo: props.malla.periodo ?? '',
    activa: Boolean(props.malla.activa),
});

const enviar = () => form.put(`/mallas/${props.malla.id}`);
</script>

<template>
    <div class="max-w-4xl mx-auto pb-12">
        <VolverA href="/mallas" texto="Mallas curriculares" />

        <!-- Header -->
        <div class="mb-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 mb-2 rounded-full bg-blue-50 border border-blue-100 text-[#2E75B6]">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                </svg>
                <span class="text-xs font-bold uppercase tracking-wider">Parámetros del Plan de Estudios</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#1F3864] tracking-tight">Editar Malla Curricular</h1>
            <p class="mt-1 text-sm text-slate-500">Actualiza los datos generales, vigencia, versión y estado activo del plan académico.</p>
        </div>

        <form @submit.prevent="enviar" class="space-y-6">
            <!-- Contenedor Principal del Formulario -->
            <div class="bg-white rounded-3xl border border-slate-200/70 shadow-sm p-6 sm:p-8 relative overflow-hidden">
                <!-- Top highlight line -->
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-[#2E75B6]/30 to-transparent"></div>

                <!-- Tarjeta Destacada de Carrera (Readonly) -->
                <div class="mb-8 p-5 rounded-2xl bg-gradient-to-r from-slate-50 via-blue-50/30 to-slate-50 border border-slate-200/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-[#1F3864] text-white flex items-center justify-center shadow-sm shrink-0">
                            <svg class="h-6 w-6 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Carrera / Programa</span>
                            <div class="text-base sm:text-lg font-bold text-slate-800">{{ malla.carrera }}</div>
                        </div>
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-semibold text-slate-500 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        Carrera fija
                    </div>
                </div>

                <!-- Campos en Cuadrícula -->
                <div class="grid gap-6 sm:grid-cols-2">
                    <!-- Año de Vigencia -->
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <svg class="w-3.5 h-3.5 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Año de vigencia
                        </label>
                        <input v-model="form.anio" type="number" min="2000" max="2100" placeholder="Ej. 2026"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-2 focus:ring-[#2E75B6]/20 transition-all" />
                        <p class="mt-1 text-xs text-slate-400">Año lectivo de aprobación de la malla.</p>
                        <p v-if="form.errors.anio" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.anio }}</p>
                    </div>

                    <!-- Versión del Plan -->
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <svg class="w-3.5 h-3.5 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.386a12.04 12.04 0 004.996-4.996c.486-.827.313-1.908-.386-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                            </svg>
                            Versión del plan
                        </label>
                        <input v-model="form.version" type="text" placeholder="Ej. 2026-I o 2026-01"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-2 focus:ring-[#2E75B6]/20 transition-all font-mono" />
                        <p class="mt-1 text-xs text-slate-400">Identificador único de versión del programa.</p>
                        <p v-if="form.errors.version_unica" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.version_unica }}</p>
                        <p v-if="form.errors.version" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.version }}</p>
                    </div>

                    <!-- Modalidad -->
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <svg class="w-3.5 h-3.5 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                            </svg>
                            Modalidad
                        </label>
                        <select v-model="form.modalidad"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-2 focus:ring-[#2E75B6]/20 transition-all">
                            <option value="presencial">Presencial</option>
                            <option value="hibrido">Híbrido</option>
                            <option value="virtual">Virtual</option>
                        </select>
                        <p v-if="form.errors.modalidad" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.modalidad }}</p>
                    </div>

                    <!-- Periodo -->
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <svg class="w-3.5 h-3.5 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Periodo
                        </label>
                        <input v-model="form.periodo" type="text" placeholder="Ej. 2026-01"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-2 focus:ring-[#2E75B6]/20 transition-all font-mono" />
                        <p v-if="form.errors.periodo" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.periodo }}</p>
                    </div>
                </div>

                <!-- Tarjeta Interactiva: Malla Activa -->
                <div class="mt-8 pt-6 border-t border-slate-100">
                    <label :class="form.activa ? 'bg-emerald-50/60 border-emerald-200 ring-1 ring-emerald-200' : 'bg-slate-50/60 border-slate-200 hover:bg-slate-50'"
                           class="flex items-start gap-4 p-4 sm:p-5 rounded-2xl border cursor-pointer transition-all duration-200">
                        <input v-model="form.activa" type="checkbox"
                               class="mt-1 h-5 w-5 rounded-md border-slate-300 text-emerald-600 focus:ring-emerald-500 transition-colors" />
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-slate-800">Marcar como Malla Activa Oficial</span>
                                <span v-if="form.activa" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                    Vigente
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500 leading-relaxed">
                                Establece esta versión como la malla oficial vigente para el cálculo de convalidaciones y consultas académicas. Al marcarla, se desactivarán automáticamente otras versiones de esta misma carrera.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                <Link href="/mallas"
                      class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors">
                    Cancelar
                </Link>

                <div class="flex items-center gap-3">
                    <Link :href="`/mallas/${malla.id}`"
                          class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-100 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition-colors">
                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25z" />
                        </svg>
                        Gestionar ciclos
                    </Link>

                    <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#1F3864] to-[#2E75B6] px-6 py-2.5 text-sm font-bold text-white shadow-md hover:shadow-lg hover:scale-102 active:scale-98 transition-all duration-200 disabled:opacity-60">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        {{ form.processing ? 'Guardando cambios…' : 'Guardar cambios' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

