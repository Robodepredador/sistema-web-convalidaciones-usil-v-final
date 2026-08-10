<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import VolverA from '../../../Components/VolverA.vue';

const props = defineProps({ facultad: Object, sedes: Array });
const editando = !!props.facultad;

const form = useForm({
    codigo: props.facultad?.codigo ?? '',
    unidad_negocio_id: props.facultad?.unidad_negocio_id ?? '',
    nombre: props.facultad?.nombre ?? '',
    activo: props.facultad?.activo ?? true,
});

const enviar = () => {
    if (editando) form.put(`/estructura/facultades/${props.facultad.id}`);
    else form.post('/estructura/facultades');
};
</script>

<template>
    <div class="max-w-2xl">
        <VolverA href="/estructura/facultades" texto="Facultades" />
        <h1 class="mb-6 text-2xl font-semibold text-[#1F3864]">{{ editando ? 'Editar facultad' : 'Nueva facultad' }}</h1>

        <form @submit.prevent="enviar" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Código <span class="text-red-500">*</span></label>
                    <input v-model="form.codigo" type="text" maxlength="20" required
                           :class="form.errors.codigo ? 'border-red-400 ring-1 ring-red-300' : 'border-slate-300'"
                           class="w-full rounded-md text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.codigo" class="mt-1 text-xs text-red-600">{{ form.errors.codigo }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Sede <span class="text-red-500">*</span></label>
                    <select v-model="form.unidad_negocio_id" required
                            :class="form.errors.unidad_negocio_id ? 'border-red-400 ring-1 ring-red-300' : 'border-slate-300'"
                            class="w-full rounded-md text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                        <option value="" disabled>Seleccione</option>
                        <option v-for="s in sedes" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                    </select>
                    <p v-if="form.errors.unidad_negocio_id" class="mt-1 text-xs text-red-600">{{ form.errors.unidad_negocio_id }}</p>
                    <div class="mt-1 text-xs">
                        <span v-if="!sedes.length" class="text-amber-600">No hay sedes registradas. </span>
                        <Link href="/estructura/sedes/crear" class="font-medium text-[#2E75B6] hover:underline">+ Agregar sede</Link>
                    </div>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre <span class="text-red-500">*</span></label>
                <input v-model="form.nombre" type="text" required
                       :class="form.errors.nombre ? 'border-red-400 ring-1 ring-red-300' : 'border-slate-300'"
                       class="w-full rounded-md text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.activo" type="checkbox" class="rounded border-slate-300 text-[#2E75B6]" /> Activo
            </label>

            <div class="flex gap-3 border-t border-slate-200 pt-4">
                <button type="submit" :disabled="form.processing"
                        class="rounded-md bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    {{ editando ? 'Guardar cambios' : 'Registrar' }}
                </button>
                <Link href="/estructura/facultades" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            </div>
        </form>
    </div>
</template>
