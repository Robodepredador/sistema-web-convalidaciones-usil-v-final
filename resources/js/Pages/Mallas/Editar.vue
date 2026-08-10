<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';

const props = defineProps({ malla: Object });

const form = useForm({
    anio: props.malla.anio,
    version: props.malla.version,
    modalidad: props.malla.modalidad,
    periodo: props.malla.periodo ?? '',
    activa: props.malla.activa,
});

const enviar = () => form.put(`/mallas/${props.malla.id}`);
</script>

<template>
    <div class="max-w-2xl">
        <VolverA href="/mallas" texto="Mallas curriculares" />
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-[#1F3864]">Editar malla curricular</h1>
            <p class="mt-1 text-sm text-slate-500">{{ malla.carrera }}</p>
        </div>

        <form @submit.prevent="enviar" class="space-y-6">
            <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Carrera</label>
                    <input :value="malla.carrera" type="text" disabled
                           class="w-full cursor-not-allowed rounded-md border-slate-200 bg-slate-50 text-sm text-slate-500" />
                    <p class="mt-1 text-xs text-slate-400">La carrera no puede modificarse desde aquí.</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Año de vigencia</label>
                    <input v-model="form.anio" type="number" min="2000" max="2100"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.anio" class="mt-1 text-xs text-red-600">{{ form.errors.anio }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Versión del plan</label>
                    <input v-model="form.version" type="text" placeholder="2026-I"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.version_unica" class="mt-1 text-xs text-red-600">{{ form.errors.version_unica }}</p>
                    <p v-if="form.errors.version" class="mt-1 text-xs text-red-600">{{ form.errors.version }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Modalidad</label>
                    <select v-model="form.modalidad"
                            class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                        <option value="presencial">Presencial</option>
                        <option value="hibrido">Híbrido</option>
                        <option value="virtual">Virtual</option>
                    </select>
                    <p v-if="form.errors.modalidad" class="mt-1 text-xs text-red-600">{{ form.errors.modalidad }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Periodo</label>
                    <input v-model="form.periodo" type="text" placeholder="2026-01"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.periodo" class="mt-1 text-xs text-red-600">{{ form.errors.periodo }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                    <input v-model="form.activa" type="checkbox" class="rounded border-slate-300 text-[#2E75B6]" />
                    Marcar como malla activa (desactiva las demás de la carrera)
                </label>
            </div>

            <div class="flex gap-3 border-t border-slate-200 pt-4">
                <button type="submit" :disabled="form.processing"
                        class="rounded-md bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    Guardar cambios
                </button>
                <Link href="/mallas" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    Cancelar
                </Link>
            </div>
        </form>
    </div>
</template>
