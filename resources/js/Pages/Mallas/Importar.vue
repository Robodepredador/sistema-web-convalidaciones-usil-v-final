<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';
defineProps({ carreras: Array });

const form = useForm({ carrera_id: '', anio: new Date().getFullYear(), version: '', archivo: null });
// Lee el archivo y lleva a la pantalla de revisión (no guarda todavía).
const enviar = () => form.post('/mallas/importar/previsualizar', { forceFormData: true });
</script>

<template>
    <div class="max-w-2xl">
        <VolverA href="/mallas" texto="Mallas curriculares" />
        <h1 class="mb-2 text-2xl font-semibold text-[#1F3864]">Importar malla desde Excel</h1>
        <p class="mb-6 text-sm text-slate-500">
            Sube el plan de estudios en formato USIL (una hoja con columnas
            <code class="rounded bg-slate-100 px-1">Ciclo</code>,
            <code class="rounded bg-slate-100 px-1">Curso</code>,
            <code class="rounded bg-slate-100 px-1">CR</code>,
            <code class="rounded bg-slate-100 px-1">TH</code>,
            <code class="rounded bg-slate-100 px-1">Pre-requisito</code>).
            El sistema detecta los ciclos y las menciones, propone un código por curso y te deja
            <strong>revisar y corregir</strong> todo antes de registrar.
        </p>

        <form @submit.prevent="enviar" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Carrera</label>
                    <select v-model="form.carrera_id" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="" disabled>Seleccione</option>
                        <option v-for="c in carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                    </select>
                    <p v-if="form.errors.carrera_id" class="mt-1 text-xs text-red-600">{{ form.errors.carrera_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Año</label>
                    <input v-model="form.anio" type="number" class="w-full rounded-md border-slate-300 text-sm" />
                    <p v-if="form.errors.anio" class="mt-1 text-xs text-red-600">{{ form.errors.anio }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Versión</label>
                    <input v-model="form.version" type="text" placeholder="2023-01" class="w-full rounded-md border-slate-300 text-sm" />
                    <p v-if="form.errors.version" class="mt-1 text-xs text-red-600">{{ form.errors.version }}</p>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Archivo Excel (.xlsx)</label>
                <input type="file" accept=".xlsx,.xls" @input="form.archivo = $event.target.files[0]"
                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-[#1F3864] file:px-4 file:py-2 file:text-sm file:text-white" />
                <p v-if="form.errors.archivo" class="mt-1 text-xs text-red-600">{{ form.errors.archivo }}</p>
                <p v-if="form.progress" class="mt-1 text-xs text-slate-500">Subiendo: {{ form.progress.percentage }}%</p>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-md bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    {{ form.processing ? 'Leyendo archivo…' : 'Leer y revisar' }}
                </button>
                <Link href="/mallas" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            </div>
        </form>
    </div>
</template>
