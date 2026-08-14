<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import VolverA from '../../Components/VolverA.vue';

defineProps({ carreras: Array });

const form = useForm({
    carrera_id: '',
    anio: new Date().getFullYear(),
    version: '',
    archivo: null,
});

const archivoNombre = ref('');

const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.archivo = file;
        archivoNombre.value = file.name;
    }
};

const enviar = () => form.post('/mallas/importar/previsualizar', { forceFormData: true });
</script>

<template>
    <div class="max-w-5xl mx-auto pb-16">
        <VolverA href="/mallas" texto="Mallas curriculares" />

        <!-- Header -->
        <div class="mb-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 mb-2 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-700">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                <span class="text-xs font-bold uppercase tracking-wider">Carga Asistida · Formato Excel</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#1F3864] tracking-tight">Importar Malla desde Excel</h1>
            <p class="mt-1 text-sm text-slate-500">Sube la matriz curricular institucional para previsualizar, corregir y registrar los ciclos y cursos de forma automatizada.</p>
        </div>

        <div class="grid gap-8 lg:grid-cols-12 items-start">
            <!-- Formulario Principal (7 cols) -->
            <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200/70 shadow-sm p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-emerald-500/30 to-transparent"></div>

                <form @submit.prevent="enviar" class="space-y-6">
                    <!-- Parámetros del plan -->
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <svg class="w-3.5 h-3.5 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342" />
                            </svg>
                            Carrera destino
                        </label>
                        <select v-model="form.carrera_id"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-2 focus:ring-[#2E75B6]/20 transition-all">
                            <option value="" disabled>Selecciona una carrera…</option>
                            <option v-for="c in carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                        </select>
                        <p v-if="form.errors.carrera_id" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.carrera_id }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                                <svg class="w-3.5 h-3.5 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Año de vigencia
                            </label>
                            <input v-model="form.anio" type="number" min="2000" max="2100" placeholder="Ej. 2026"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-2 focus:ring-[#2E75B6]/20 transition-all" />
                            <p v-if="form.errors.anio" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.anio }}</p>
                        </div>

                        <div>
                            <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                                <svg class="w-3.5 h-3.5 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.386a12.04 12.04 0 004.996-4.996c.486-.827.313-1.908-.386-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                </svg>
                                Versión del plan
                            </label>
                            <input v-model="form.version" type="text" placeholder="Ej. 2026-I"
                                   class="w-full rounded-xl border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-2 focus:ring-[#2E75B6]/20 transition-all font-mono" />
                            <p v-if="form.errors.version" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.version }}</p>
                        </div>
                    </div>

                    <!-- Zona Dropzone de Archivo -->
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                            Archivo Excel (.xlsx o .xls)
                        </label>
                        <div class="relative mt-1 flex justify-center rounded-2xl border-2 border-dashed border-slate-300 px-6 pt-6 pb-6 hover:border-[#2E75B6] hover:bg-blue-50/20 transition-all duration-200 group text-center">
                            <div class="space-y-2">
                                <div class="mx-auto h-12 w-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                </div>
                                <div class="text-xs text-slate-600">
                                    <label class="relative cursor-pointer font-bold text-[#2E75B6] hover:text-[#1F3864]">
                                        <span>Seleccionar archivo desde tu equipo</span>
                                        <input type="file" accept=".xlsx,.xls" @change="onFileChange" class="sr-only" />
                                    </label>
                                    <p class="text-[11px] text-slate-400 mt-1">Formatos permitidos: .xlsx, .xls</p>
                                </div>
                                <div v-if="archivoNombre" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs font-semibold border border-emerald-200">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    {{ archivoNombre }}
                                </div>
                            </div>
                        </div>
                        <p v-if="form.errors.archivo" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.archivo }}</p>
                        <div v-if="form.progress" class="mt-2">
                            <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full bg-[#2E75B6] transition-all duration-200" :style="{ width: `${form.progress.percentage}%` }"></div>
                            </div>
                            <span class="text-xs text-slate-500 mt-1 block">Subiendo archivo: {{ form.progress.percentage }}%</span>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex items-center justify-between gap-4 pt-4 border-t border-slate-100">
                        <Link href="/mallas"
                              class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors">
                            Cancelar
                        </Link>

                        <button type="submit" :disabled="form.processing || !form.archivo || !form.carrera_id"
                                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#1F3864] to-[#2E75B6] px-6 py-2.5 text-sm font-bold text-white shadow-md hover:shadow-lg hover:scale-102 active:scale-98 transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                            {{ form.processing ? 'Leyendo archivo…' : 'Leer y previsualizar' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Panel Lateral de Ayuda e Instrucciones (5 cols) -->
            <div class="lg:col-span-5 space-y-5">
                <div class="bg-gradient-to-br from-slate-50 to-blue-50/40 rounded-3xl border border-slate-200/80 p-6 shadow-sm">
                    <h3 class="text-sm font-bold text-[#1F3864] flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        Estructura Requerida del Excel
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed mb-4">
                        El archivo debe contener una hoja con las columnas oficiales en la primera fila:
                    </p>

                    <div class="space-y-2 text-xs">
                        <div class="p-2.5 rounded-xl bg-white border border-slate-200/80 flex items-center justify-between">
                            <span class="font-mono font-bold text-slate-700">Ciclo</span>
                            <span class="text-slate-500">Número de ciclo (1 al 12)</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white border border-slate-200/80 flex items-center justify-between">
                            <span class="font-mono font-bold text-slate-700">Curso</span>
                            <span class="text-slate-500">Nombre de la materia</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white border border-slate-200/80 flex items-center justify-between">
                            <span class="font-mono font-bold text-slate-700">CR / TH</span>
                            <span class="text-slate-500">Créditos y Horas Totales</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white border border-slate-200/80 flex items-center justify-between">
                            <span class="font-mono font-bold text-slate-700">Pre-requisito</span>
                            <span class="text-slate-500">Requisitos previos</span>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-200/60">
                        <a href="/mallas/plantilla" target="_blank"
                           class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-xl bg-white border border-slate-300 text-xs font-bold text-[#1F3864] hover:bg-blue-50 transition-colors shadow-2xs">
                            <svg class="w-4 h-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12L12 16.5m0 0L16.5 12M12 16.5V3" />
                            </svg>
                            Descargar plantilla de ejemplo (.xlsx)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

