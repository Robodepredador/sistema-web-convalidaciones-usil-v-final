<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';
import { computed } from 'vue';

const props = defineProps({
    carrera: Object,     // { id, nombre, codigo }
    cabecera: Object,    // { anio, version, facultad }
    ciclos: Array,
    menciones: Array,
    resumen: Object,     // { cursos, ciclos, menciones }
    hoja: String,
});

const form = useForm({
    carrera_id: props.carrera.id,
    anio: props.cabecera.anio,
    version: props.cabecera.version,
    modalidad: 'presencial',
    periodo: props.cabecera.version ?? '',
    activa: false,
    ciclos: props.ciclos.map((c) => ({
        numero: c.numero,
        cursos: c.cursos.map((cu) => ({ ...cu })),
    })),
    menciones: props.menciones.map((m) => ({
        nombre: m.nombre,
        cursos: m.cursos.map((cu) => ({ ...cu })),
    })),
});

const totalCursos = computed(() =>
    form.ciclos.reduce((a, c) => a + c.cursos.length, 0) +
    form.menciones.reduce((a, m) => a + m.cursos.length, 0));

const creditosCiclo = (c) => c.cursos.reduce((a, cu) => a + (Number(cu.creditos) || 0), 0);
const creditosMencion = (m) => m.cursos.reduce((a, cu) => a + (Number(cu.creditos) || 0), 0);

const quitarCurso = (cursos, i) => cursos.splice(i, 1);

const registrar = () => form.post('/mallas/importar/guardar');
</script>

<template>
    <div>
        <VolverA href="/mallas" texto="Mallas curriculares" />
        <h1 class="text-2xl font-semibold text-[#1F3864]">Revisar malla importada</h1>
        <p class="mt-1 text-sm text-slate-500">
            Verifica y corrige los datos leídos antes de registrarlos. Nada se guarda hasta que confirmes.
        </p>

        <!-- Banner de lectura -->
        <div class="mt-5 flex flex-wrap items-center gap-3 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800">
            <svg class="h-5 w-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span class="text-sm font-medium">Archivo leído correctamente
                <span class="font-normal opacity-80">· hoja «{{ hoja }}»</span>
            </span>
            <div class="ml-auto flex flex-wrap gap-2">
                <span class="rounded-full border border-green-200 bg-white px-3 py-1 text-xs text-slate-600"><b class="text-[#1F3864]">{{ totalCursos }}</b> cursos</span>
                <span class="rounded-full border border-green-200 bg-white px-3 py-1 text-xs text-slate-600"><b class="text-[#1F3864]">{{ form.ciclos.length }}</b> ciclos</span>
                <span class="rounded-full border border-green-200 bg-white px-3 py-1 text-xs text-slate-600"><b class="text-[#1F3864]">{{ form.menciones.length }}</b> menciones</span>
            </div>
        </div>

        <!-- Información general -->
        <div class="mt-5 rounded-lg border border-slate-200 bg-white p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-[#1F3864]">Información general</h2>
                <span class="text-xs text-slate-400">Detectado del archivo — editable</span>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Carrera</label>
                    <input :value="carrera.nombre" disabled class="w-full rounded-md border-slate-200 bg-slate-50 text-sm text-slate-500" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Año de vigencia</label>
                    <input v-model="form.anio" type="number" class="w-full rounded-md border-slate-300 text-sm" />
                    <p v-if="form.errors.anio" class="mt-1 text-xs text-red-600">{{ form.errors.anio }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Versión del plan</label>
                    <input v-model="form.version" type="text" class="w-full rounded-md border-slate-300 text-sm" />
                    <p v-if="form.errors.version" class="mt-1 text-xs text-red-600">{{ form.errors.version }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Modalidad</label>
                    <select v-model="form.modalidad" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="presencial">Presencial</option>
                        <option value="hibrido">Híbrido</option>
                        <option value="virtual">Virtual</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Periodo</label>
                    <input v-model="form.periodo" type="text" placeholder="2023-01" class="w-full rounded-md border-slate-300 text-sm" />
                </div>
                <label class="flex items-end gap-2 pb-1 text-sm text-slate-700">
                    <input v-model="form.activa" type="checkbox" class="rounded border-slate-300 text-[#2E75B6]" />
                    Marcar como malla activa
                </label>
            </div>
        </div>

        <!-- Nota sobre códigos -->
        <div class="mt-4 flex items-start gap-2.5 rounded-md border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-slate-600">
            <svg class="h-5 w-5 shrink-0 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <span>El archivo no traía código de curso, así que el sistema propuso uno con el formato
                <code class="rounded bg-white px-1 font-mono text-xs">{{ carrera.codigo }}-C1-01</code>
                (carrera · ciclo · número). Todos los campos son editables. Un prerrequisito múltiple conserva el texto completo del archivo.</span>
        </div>

        <!-- ================= CICLOS ================= -->
        <div class="mt-5 rounded-lg border border-slate-200 bg-white p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-[#1F3864]">Cursos del plan regular</h2>
                <span class="text-xs text-slate-400">Agrupados por ciclo</span>
            </div>

            <div v-for="(ciclo, ci) in form.ciclos" :key="'c' + ci" class="mb-4 overflow-hidden rounded-lg border border-slate-200">
                <div class="flex items-center gap-2.5 border-b border-slate-200 bg-slate-50 px-4 py-2.5">
                    <span class="rounded-md bg-[#1F3864] px-2.5 py-1 text-xs font-semibold text-white">Ciclo {{ ciclo.numero }}</span>
                    <span class="text-xs text-slate-500">{{ ciclo.cursos.length }} cursos</span>
                    <span class="ml-auto text-xs font-medium text-slate-400">{{ creditosCiclo(ciclo) }} créditos</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] border-collapse text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wide text-slate-400">
                                <th class="px-3 py-2 font-semibold">Código</th>
                                <th class="px-3 py-2 font-semibold">Nombre</th>
                                <th class="px-3 py-2 font-semibold">Condición</th>
                                <th class="px-3 py-2 font-semibold">Créd.</th>
                                <th class="px-3 py-2 font-semibold">Horas</th>
                                <th class="px-3 py-2 font-semibold">Pre-requisito</th>
                                <th class="px-3 py-2 text-center font-semibold">Conval.</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(curso, cj) in ciclo.cursos" :key="'cc' + cj" class="border-t border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-1.5"><input v-model="curso.codigo" class="w-28 rounded-md border-slate-300 font-mono text-xs" /></td>
                                <td class="px-3 py-1.5"><input v-model="curso.nombre" class="w-full min-w-[220px] rounded-md border-slate-300 text-sm" /></td>
                                <td class="px-3 py-1.5">
                                    <select v-model="curso.es_electivo" class="rounded-md border-slate-300 text-sm">
                                        <option :value="false">Obligatorio</option>
                                        <option :value="true">Electivo</option>
                                    </select>
                                </td>
                                <td class="px-3 py-1.5"><input v-model="curso.creditos" type="number" step="0.5" class="w-16 rounded-md border-slate-300 text-center text-sm" /></td>
                                <td class="px-3 py-1.5"><input v-model="curso.horas" type="number" step="0.5" class="w-16 rounded-md border-slate-300 text-center text-sm" /></td>
                                <td class="px-3 py-1.5"><input v-model="curso.prerequisito" class="w-full min-w-[170px] rounded-md border-slate-300 text-sm" /></td>
                                <td class="px-3 py-1.5 text-center"><input v-model="curso.convalidable" type="checkbox" class="rounded border-slate-300 text-[#2E75B6]" /></td>
                                <td class="px-2 py-1.5">
                                    <button type="button" @click="quitarCurso(ciclo.cursos, cj)" class="text-slate-400 hover:text-red-600" title="Quitar">✕</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================= MENCIONES ================= -->
        <template v-if="form.menciones.length">
            <div class="mb-3 mt-7 flex items-center gap-2.5">
                <span class="h-5 w-[3px] rounded bg-[#2E75B6]"></span>
                <h2 class="text-lg font-semibold text-[#1F3864]">Menciones / Especialidades</h2>
                <span class="rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-medium text-[#2E75B6]">{{ form.menciones.length }} menciones</span>
            </div>

            <div class="rounded-lg border border-blue-100 bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-[#1F3864]">Cursos de especialización</h2>
                    <span class="text-xs text-slate-400">Se guardan separados del plan regular</span>
                </div>

                <div v-for="(men, mi) in form.menciones" :key="'m' + mi" class="mb-4 overflow-hidden rounded-lg border border-blue-100">
                    <div class="flex items-center gap-2.5 border-b border-blue-100 border-l-[3px] border-l-[#2E75B6] bg-blue-50 px-4 py-2.5">
                        <span class="rounded-md bg-[#2E75B6] px-2.5 py-1 text-xs font-semibold text-white">M{{ mi + 1 }}</span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-[#2E75B6]">Mención</span>
                        <input v-model="men.nombre" class="min-w-[260px] flex-1 rounded-md border-blue-100 bg-white text-sm font-medium text-[#1F3864]" />
                        <span class="ml-auto whitespace-nowrap text-xs font-medium text-slate-400">{{ men.cursos.length }} cursos · {{ creditosMencion(men) }} créditos</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[820px] border-collapse text-sm">
                            <thead>
                                <tr class="text-left text-[11px] uppercase tracking-wide text-slate-400">
                                    <th class="px-3 py-2 font-semibold">Código</th>
                                    <th class="px-3 py-2 font-semibold">Nombre</th>
                                    <th class="px-3 py-2 font-semibold">Ciclo</th>
                                    <th class="px-3 py-2 font-semibold">Créd.</th>
                                    <th class="px-3 py-2 font-semibold">Horas</th>
                                    <th class="px-3 py-2 font-semibold">Pre-requisito</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(curso, cj) in men.cursos" :key="'mc' + cj" class="border-t border-slate-100 hover:bg-slate-50">
                                    <td class="px-3 py-1.5"><input v-model="curso.codigo" class="w-28 rounded-md border-slate-300 font-mono text-xs" /></td>
                                    <td class="px-3 py-1.5"><input v-model="curso.nombre" class="w-full min-w-[220px] rounded-md border-slate-300 text-sm" /></td>
                                    <td class="px-3 py-1.5"><input v-model="curso.ciclo" type="number" min="1" max="14" class="w-16 rounded-md border-slate-300 text-center text-sm" /></td>
                                    <td class="px-3 py-1.5"><input v-model="curso.creditos" type="number" step="0.5" class="w-16 rounded-md border-slate-300 text-center text-sm" /></td>
                                    <td class="px-3 py-1.5"><input v-model="curso.horas" type="number" step="0.5" class="w-16 rounded-md border-slate-300 text-center text-sm" /></td>
                                    <td class="px-3 py-1.5"><input v-model="curso.prerequisito" class="w-full min-w-[170px] rounded-md border-slate-300 text-sm" /></td>
                                    <td class="px-2 py-1.5">
                                        <button type="button" @click="quitarCurso(men.cursos, cj)" class="text-slate-400 hover:text-red-600" title="Quitar">✕</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- Acciones -->
        <div class="sticky bottom-4 mt-6 flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-5 py-4 shadow-lg">
            <button type="button" @click="registrar" :disabled="form.processing"
                    class="rounded-md bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                {{ form.processing ? 'Registrando…' : 'Registrar malla' }}
            </button>
            <Link href="/mallas" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            <div class="ml-auto text-right text-xs text-slate-500">
                Se registrarán <b class="text-slate-700">{{ totalCursos }} cursos</b> en
                <b class="text-slate-700">{{ form.ciclos.length }} ciclos</b>
                <span v-if="form.menciones.length"> + <b class="text-slate-700">{{ form.menciones.length }} menciones</b></span><br>
                para <b class="text-slate-700">{{ carrera.nombre }} {{ form.version }}</b>
            </div>
        </div>
    </div>
</template>
