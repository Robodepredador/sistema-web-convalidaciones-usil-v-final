<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';
import VolverA from '../../Components/VolverA.vue';

const props = defineProps({ carreras: Array });
const carrerasOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));

const form = useForm({
    carrera_id: '',
    anio: new Date().getFullYear(),
    version: '',
    modalidad: 'presencial',
    periodo: '',
    activa: false,
    ciclos: [{ numero: 1, cursos: [{ codigo: '', nombre: '', creditos: '' }] }],
});

const totalCursos = computed(() =>
    form.ciclos.reduce((acc, ciclo) => acc + ciclo.cursos.length, 0)
);

const totalCreditos = computed(() =>
    form.ciclos.reduce((acc, ciclo) =>
        acc + ciclo.cursos.reduce((cAcc, curso) => cAcc + (Number(curso.creditos) || 0), 0)
    , 0)
);

const creditosCiclo = (ciclo) =>
    ciclo.cursos.reduce((acc, curso) => acc + (Number(curso.creditos) || 0), 0);

const agregarCiclo = () => {
    const siguiente = form.ciclos.length ? Math.max(...form.ciclos.map(c => Number(c.numero) || 0)) + 1 : 1;
    form.ciclos.push({ numero: siguiente, cursos: [{ codigo: '', nombre: '', creditos: '' }] });
};

const quitarCiclo = (i) => {
    if (confirm(`¿Eliminar el Ciclo ${form.ciclos[i].numero} y sus asignaturas?`)) {
        form.ciclos.splice(i, 1);
    }
};

const agregarCurso = (ci) => form.ciclos[ci].cursos.push({ codigo: '', nombre: '', creditos: '' });
const quitarCurso = (ci, cj) => form.ciclos[ci].cursos.splice(cj, 1);

const enviar = () => form.post('/mallas');
</script>

<template>
    <div class="w-full pb-16">
        <VolverA href="/mallas" texto="Mallas curriculares" />

        <!-- Header Hero Banner -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] p-8 sm:p-10 text-white shadow-xl">
            <!-- Decorative background elements -->
            <div class="absolute -top-24 -right-24 w-80 h-80 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/3 w-64 h-64 bg-[#0036DC] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div class="max-w-xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md">
                        <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Configuración Curricular</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">Nueva Malla Curricular</h1>
                    <p class="mt-2 text-sm sm:text-base text-blue-100/90 leading-relaxed">
                        Registra la estructura académica definiendo ciclos y asignaturas de forma manual o acelerando el proceso desde Excel.
                    </p>
                </div>

                <!-- Botones Rápidos en el Header -->
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <Link href="/mallas/importar"
                          class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-xs font-bold text-[#00205B] shadow-md transition-all duration-200 hover:bg-blue-50 hover:scale-105">
                        <svg class="w-4 h-4 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        Importar desde Excel
                    </Link>
                </div>
            </div>
        </div>

        <form @submit.prevent="enviar" class="space-y-8">
            <!-- SECCIÓN 1: DATOS GENERALES -->
            <div class="bg-white rounded-3xl border border-slate-200/70 shadow-sm p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-[#0036DC]/30 to-transparent"></div>

                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                    <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-blue-50 text-xs font-bold text-[#0036DC]">
                        1
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-800">Información General del Plan de Estudios</h2>
                        <p class="text-xs text-slate-500">Parámetros de vigencia y carrera a la que pertenece esta malla</p>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Carrera -->
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <svg class="w-3.5 h-3.5 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342" />
                            </svg>
                            Carrera / Programa
                        </label>
                        <Autocomplete v-model="form.carrera_id" :options="carrerasOpts" placeholder="Buscar carrera…" />
                        <p v-if="form.errors.carrera_id" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.carrera_id }}</p>
                    </div>

                    <!-- Año de Vigencia -->
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <svg class="w-3.5 h-3.5 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Año de vigencia
                        </label>
                        <input v-model="form.anio" type="number" min="2000" max="2100" placeholder="Ej. 2026"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-[#0036DC] focus:ring-2 focus:ring-[#0036DC]/20 transition-all" />
                        <p v-if="form.errors.anio" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.anio }}</p>
                    </div>

                    <!-- Versión del Plan -->
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <svg class="w-3.5 h-3.5 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.386a12.04 12.04 0 004.996-4.996c.486-.827.313-1.908-.386-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                            </svg>
                            Versión del plan
                        </label>
                        <input v-model="form.version" type="text" placeholder="Ej. 2026-I o 2026-01"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-[#0036DC] focus:ring-2 focus:ring-[#0036DC]/20 transition-all font-mono" />
                        <p v-if="form.errors.version_unica" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.version_unica }}</p>
                        <p v-if="form.errors.version" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.version }}</p>
                    </div>

                    <!-- Modalidad -->
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <svg class="w-3.5 h-3.5 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                            </svg>
                            Modalidad
                        </label>
                        <select v-model="form.modalidad"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-[#0036DC] focus:ring-2 focus:ring-[#0036DC]/20 transition-all">
                            <option value="presencial">Presencial</option>
                            <option value="hibrido">Híbrido</option>
                            <option value="virtual">Virtual</option>
                        </select>
                        <p v-if="form.errors.modalidad" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.modalidad }}</p>
                    </div>

                    <!-- Periodo -->
                    <div>
                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <svg class="w-3.5 h-3.5 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Periodo
                        </label>
                        <input v-model="form.periodo" type="text" placeholder="Ej. 2026-01"
                               class="w-full rounded-xl border-slate-300 text-sm focus:border-[#0036DC] focus:ring-2 focus:ring-[#0036DC]/20 transition-all font-mono" />
                        <p v-if="form.errors.periodo" class="mt-1.5 text-xs font-medium text-red-600">{{ form.errors.periodo }}</p>
                    </div>

                    <!-- Switch Malla Activa -->
                    <div class="sm:col-span-2 lg:col-span-1 flex items-end">
                        <label :class="form.activa ? 'bg-emerald-50 border-emerald-200 ring-1 ring-emerald-200' : 'bg-slate-50 border-slate-200 hover:bg-slate-100/70'"
                               class="w-full flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition-all">
                            <input v-model="form.activa" type="checkbox"
                                   class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <div class="text-xs">
                                <span class="font-bold text-slate-800">Malla Activa Oficial</span>
                                <span class="block text-slate-500 text-[11px]">Vigente para convalidaciones</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: ESTRUCTURA DE CICLOS Y ASIGNATURAS -->
            <div class="bg-white rounded-3xl border border-slate-200/70 shadow-sm p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-[#0036DC]/30 to-transparent"></div>

                <div class="flex flex-wrap items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-blue-50 text-xs font-bold text-[#0036DC]">
                            2
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-slate-800">Estructura de Ciclos y Asignaturas</h2>
                            <p class="text-xs text-slate-500">Agrega las materias correspondientes a cada periodo académico</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs font-bold bg-blue-50 text-[#00205B]">
                            {{ form.ciclos.length }} {{ form.ciclos.length === 1 ? 'Ciclo' : 'Ciclos' }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs font-bold bg-slate-100 text-slate-700">
                            {{ totalCursos }} {{ totalCursos === 1 ? 'Curso' : 'Cursos' }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700">
                            {{ totalCreditos }} Créditos
                        </span>
                    </div>
                </div>

                <!-- Lista de Ciclos -->
                <div class="space-y-6">
                    <div v-for="(ciclo, ci) in form.ciclos" :key="ci"
                         class="rounded-2xl border border-slate-200/80 bg-slate-50/40 p-5 sm:p-6 transition-all hover:border-blue-200 hover:bg-white hover:shadow-sm">
                        
                        <!-- Cabecera del Ciclo -->
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-200/60">
                            <div class="flex items-center gap-3">
                                <div class="h-8 px-3 rounded-lg bg-[#00205B] text-white flex items-center justify-center text-xs font-bold shadow-xs">
                                    Ciclo {{ ciclo.numero }}
                                </div>
                                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                    <span>N° Ciclo:</span>
                                    <input v-model.number="ciclo.numero" type="number" min="1" max="14"
                                           class="w-16 h-7 rounded-lg border-slate-300 text-xs font-bold text-center py-0" />
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="text-xs font-semibold text-slate-500 bg-white px-2.5 py-1 rounded-lg border border-slate-200/60">
                                    <span class="font-bold text-[#0036DC]">{{ creditosCiclo(ciclo) }}</span> créditos en este ciclo
                                </span>
                                <button type="button" @click="quitarCiclo(ci)" v-if="form.ciclos.length > 1"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-700 hover:underline transition-colors"
                                        title="Eliminar este ciclo">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                    Quitar ciclo
                                </button>
                            </div>
                        </div>

                        <!-- Lista de Cursos del Ciclo -->
                        <div class="space-y-2.5">
                            <!-- Encabezado de columnas visible en desktop -->
                            <div class="hidden sm:flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 px-1">
                                <div class="w-32">Código</div>
                                <div class="flex-1">Nombre de la Asignatura</div>
                                <div class="w-24 text-center">Créditos</div>
                                <div class="w-8"></div>
                            </div>

                            <div v-for="(curso, cj) in ciclo.cursos" :key="cj"
                                 class="flex flex-wrap sm:flex-nowrap items-center gap-2 bg-white p-2 rounded-xl border border-slate-200/80 shadow-2xs">
                                <input v-model="curso.codigo" placeholder="Ej. INF101"
                                       class="w-full sm:w-32 rounded-lg border-slate-300 text-xs font-mono uppercase focus:border-[#0036DC] focus:ring-[#0036DC]" />
                                <input v-model="curso.nombre" placeholder="Nombre de la asignatura"
                                       class="flex-1 w-full rounded-lg border-slate-300 text-xs focus:border-[#0036DC] focus:ring-[#0036DC]" />
                                <input v-model="curso.creditos" type="number" step="0.5" min="0" placeholder="Créd."
                                       class="w-24 rounded-lg border-slate-300 text-xs text-center font-bold focus:border-[#0036DC] focus:ring-[#0036DC]" />
                                <button type="button" @click="quitarCurso(ci, cj)" v-if="ciclo.cursos.length > 1"
                                        class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                        title="Quitar asignatura">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Botón Agregar Curso -->
                        <div class="mt-4 pt-2">
                            <button type="button" @click="agregarCurso(ci)"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0036DC] hover:text-[#00205B] bg-white px-3 py-1.5 rounded-xl border border-blue-100 hover:border-blue-200 transition-all shadow-2xs">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Agregar curso a este ciclo
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Botón Agregar Siguiente Ciclo -->
                <div class="mt-6">
                    <button type="button" @click="agregarCiclo"
                            class="group w-full py-4 rounded-2xl border-2 border-dashed border-slate-300 hover:border-[#0036DC] hover:bg-blue-50/40 text-slate-600 hover:text-[#0036DC] font-bold text-sm flex items-center justify-center gap-2 transition-all duration-200">
                        <div class="w-6 h-6 rounded-full bg-slate-100 group-hover:bg-[#0036DC] group-hover:text-white flex items-center justify-center transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </div>
                        Agregar siguiente ciclo (Ciclo {{ form.ciclos.length + 1 }})
                    </button>
                </div>
            </div>

            <!-- BARRA DE ACCIONES INFERIOR -->
            <div class="bg-white rounded-3xl border border-slate-200/70 p-5 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <Link href="/mallas"
                      class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors">
                    Cancelar
                </Link>

                <div class="flex items-center gap-4">
                    <div class="hidden sm:block text-right text-xs text-slate-500">
                        <span class="font-bold text-slate-800">{{ form.ciclos.length }} ciclos</span> · 
                        <span class="font-bold text-slate-800">{{ totalCursos }} cursos</span> · 
                        <span class="font-bold text-[#0036DC]">{{ totalCreditos }} créditos</span>
                    </div>

                    <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] px-7 py-3 text-sm font-bold text-white shadow-md hover:shadow-lg hover:scale-102 active:scale-98 transition-all duration-200 disabled:opacity-60">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        {{ form.processing ? 'Registrando malla…' : 'Registrar malla curricular' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

