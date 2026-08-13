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

const agregarCiclo = () => {
    const siguiente = form.ciclos.length + 1;
    form.ciclos.push({ numero: siguiente, cursos: [{ codigo: '', nombre: '', creditos: '' }] });
};
const quitarCiclo = (i) => form.ciclos.splice(i, 1);
const agregarCurso = (ci) => form.ciclos[ci].cursos.push({ codigo: '', nombre: '', creditos: '' });
const quitarCurso = (ci, cj) => form.ciclos[ci].cursos.splice(cj, 1);

const enviar = () => form.post('/mallas');
</script>

<template>
    <div class="max-w-4xl">
        <VolverA href="/mallas" texto="Mallas curriculares" />
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold text-[#1F3864]">Nueva malla curricular</h1>
            <div class="flex gap-2">
                <a href="/mallas/plantilla" target="_blank"
                   class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span aria-hidden="true">📥</span> Descargar plantilla
                </a>
                <Link href="/mallas/importar"
                      class="inline-flex items-center gap-2 rounded-md border border-[#1F3864] px-4 py-2 text-sm font-medium text-[#1F3864] hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Importar desde Excel
                </Link>
            </div>
        </div>
        <p class="-mt-4 mb-6 text-sm text-slate-500">
            Llena los datos manualmente, o
            <Link href="/mallas/importar" class="font-medium text-[#2E75B6] hover:underline">importa el plan desde un Excel</Link>
            y revísalo antes de registrar.
        </p>

        <form @submit.prevent="enviar" class="space-y-6">
            <!-- Datos generales -->
            <div class="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-3">
                <div class="sm:col-span-1">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Carrera</label>
                    <Autocomplete v-model="form.carrera_id" :options="carrerasOpts" placeholder="Buscar carrera…" />
                    <p v-if="form.errors.carrera_id" class="mt-1 text-xs text-red-600">{{ form.errors.carrera_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Año de vigencia</label>
                    <input v-model="form.anio" type="number" class="w-full rounded-md border-slate-300 text-sm" />
                    <p v-if="form.errors.anio" class="mt-1 text-xs text-red-600">{{ form.errors.anio }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Versión del plan</label>
                    <input v-model="form.version" type="text" placeholder="2026-I"
                           class="w-full rounded-md border-slate-300 text-sm" />
                    <p v-if="form.errors.version_unica" class="mt-1 text-xs text-red-600">{{ form.errors.version_unica }}</p>
                    <p v-if="form.errors.version" class="mt-1 text-xs text-red-600">{{ form.errors.version }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Modalidad</label>
                    <select v-model="form.modalidad" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="presencial">Presencial</option>
                        <option value="hibrido">Híbrido</option>
                        <option value="virtual">Virtual</option>
                    </select>
                    <p v-if="form.errors.modalidad" class="mt-1 text-xs text-red-600">{{ form.errors.modalidad }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Periodo</label>
                    <input v-model="form.periodo" type="text" placeholder="2026-01"
                           class="w-full rounded-md border-slate-300 text-sm" />
                    <p v-if="form.errors.periodo" class="mt-1 text-xs text-red-600">{{ form.errors.periodo }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-3">
                    <input v-model="form.activa" type="checkbox" class="rounded border-slate-300 text-[#2E75B6]" />
                    Marcar como malla activa (desactiva las demás de la carrera)
                </label>
            </div>

            <!-- Ciclos y cursos -->
            <div v-for="(ciclo, ci) in form.ciclos" :key="ci" class="rounded-lg border border-slate-200 bg-white p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-slate-700">Ciclo</span>
                        <input v-model.number="ciclo.numero" type="number" min="1" max="14"
                               class="w-20 rounded-md border-slate-300 text-sm" />
                    </div>
                    <button type="button" @click="quitarCiclo(ci)" v-if="form.ciclos.length > 1"
                            class="text-xs text-red-600 hover:underline">Quitar ciclo</button>
                </div>

                <div class="space-y-2">
                    <div v-for="(curso, cj) in ciclo.cursos" :key="cj" class="flex flex-wrap items-center gap-2">
                        <input v-model="curso.codigo" placeholder="Código" class="w-28 rounded-md border-slate-300 text-sm" />
                        <input v-model="curso.nombre" placeholder="Nombre del curso" class="flex-1 rounded-md border-slate-300 text-sm" />
                        <input v-model="curso.creditos" type="number" step="0.5" placeholder="Créd."
                               class="w-20 rounded-md border-slate-300 text-sm" />
                        <button type="button" @click="quitarCurso(ci, cj)" v-if="ciclo.cursos.length > 1"
                                class="text-slate-400 hover:text-red-600">✕</button>
                    </div>
                </div>
                <button type="button" @click="agregarCurso(ci)" class="mt-3 text-sm text-[#2E75B6] hover:underline">
                    + Agregar curso
                </button>
            </div>

            <button type="button" @click="agregarCiclo"
                    class="rounded-md border border-dashed border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                + Agregar ciclo
            </button>

            <div class="flex gap-3 border-t border-slate-200 pt-4">
                <button type="submit" :disabled="form.processing"
                        class="rounded-md bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    Registrar malla
                </button>
                <Link href="/mallas" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    Cancelar
                </Link>
            </div>
        </form>
    </div>
</template>
