<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Autocomplete from '../../../Components/Autocomplete.vue';
import VolverA from '../../../Components/VolverA.vue';

const props = defineProps({ plan: Object, programas: Array, modalidades: Array });
const editando = !!props.plan;
const programasOpts = computed(() => props.programas.map((p) => ({ value: p.id, label: p.nombre })));

const form = useForm({
    codigo: props.plan?.codigo ?? '',
    carrera_id: props.plan?.carrera_id ?? '',
    modalidad_id: props.plan?.modalidad_id ?? '',
    nombre: props.plan?.nombre ?? '',
    anio: props.plan?.anio ?? new Date().getFullYear(),
    version: props.plan?.version ?? '',
    activo: props.plan?.activo ?? true,
});

const enviar = () => {
    if (editando) form.put(`/estructura/planes/${props.plan.id}`);
    else form.post('/estructura/planes');
};
</script>

<template>
    <div class="max-w-2xl">
        <VolverA href="/estructura/planes" texto="Planes de estudio" />
        <h1 class="mb-6 text-2xl font-semibold text-[#1F3864]">{{ editando ? 'Editar plan de estudios' : 'Nuevo plan de estudios' }}</h1>

        <form @submit.prevent="enviar" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Código</label>
                    <input v-model="form.codigo" type="text" maxlength="30"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.codigo" class="mt-1 text-xs text-red-600">{{ form.errors.codigo }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Programa Académico</label>
                    <Autocomplete v-model="form.carrera_id" :options="programasOpts" placeholder="Buscar programa…" />
                    <p v-if="form.errors.carrera_id" class="mt-1 text-xs text-red-600">{{ form.errors.carrera_id }}</p>
                    <div class="mt-1 text-xs">
                        <span v-if="!programas.length" class="text-amber-600">No hay programas registrados. </span>
                        <Link href="/estructura/programas/crear" class="font-medium text-[#2E75B6] hover:underline">+ Agregar programa</Link>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Modalidad</label>
                    <select v-model="form.modalidad_id" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                        <option value="" disabled>Seleccione</option>
                        <option v-for="m in modalidades" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                    </select>
                    <p v-if="form.errors.modalidad_id" class="mt-1 text-xs text-red-600">{{ form.errors.modalidad_id }}</p>
                    <div class="mt-1 text-xs">
                        <span v-if="!modalidades.length" class="text-amber-600">No hay modalidades registradas. </span>
                        <Link href="/estructura/modalidades/crear" class="font-medium text-[#2E75B6] hover:underline">+ Agregar modalidad</Link>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre del Plan</label>
                    <input v-model="form.nombre" type="text"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Año</label>
                    <input v-model="form.anio" type="number" min="2000" max="2100"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.anio" class="mt-1 text-xs text-red-600">{{ form.errors.anio }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Versión</label>
                    <input v-model="form.version" type="text" maxlength="20" placeholder="v1.0"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.version" class="mt-1 text-xs text-red-600">{{ form.errors.version }}</p>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.activo" type="checkbox" class="rounded border-slate-300 text-[#2E75B6]" /> Activo
            </label>

            <div class="flex gap-3 border-t border-slate-200 pt-4">
                <button type="submit" :disabled="form.processing"
                        class="rounded-md bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    {{ editando ? 'Guardar cambios' : 'Registrar' }}
                </button>
                <Link href="/estructura/planes" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            </div>
        </form>
    </div>
</template>
