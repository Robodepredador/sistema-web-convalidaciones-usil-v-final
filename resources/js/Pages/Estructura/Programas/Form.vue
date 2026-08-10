<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Autocomplete from '../../../Components/Autocomplete.vue';
import VolverA from '../../../Components/VolverA.vue';

const props = defineProps({ programa: Object, facultades: Array });
const editando = !!props.programa;
const facultadesOpts = computed(() => props.facultades.map((f) => ({ value: f.id, label: f.nombre })));

const form = useForm({
    codigo: props.programa?.codigo ?? '',
    facultad_id: props.programa?.facultad_id ?? '',
    nombre: props.programa?.nombre ?? '',
    activo: props.programa?.activo ?? true,
});

const enviar = () => {
    if (editando) form.put(`/estructura/programas/${props.programa.id}`);
    else form.post('/estructura/programas');
};
</script>

<template>
    <div class="max-w-2xl">
        <VolverA href="/estructura/programas" texto="Programas académicos" />
        <h1 class="mb-6 text-2xl font-semibold text-[#1F3864]">{{ editando ? 'Editar programa académico' : 'Nuevo programa académico' }}</h1>

        <form @submit.prevent="enviar" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Código</label>
                    <input v-model="form.codigo" type="text" maxlength="20"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.codigo" class="mt-1 text-xs text-red-600">{{ form.errors.codigo }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Facultad</label>
                    <Autocomplete v-model="form.facultad_id" :options="facultadesOpts" placeholder="Buscar facultad…" />
                    <p v-if="form.errors.facultad_id" class="mt-1 text-xs text-red-600">{{ form.errors.facultad_id }}</p>
                    <div class="mt-1 text-xs">
                        <span v-if="!facultades.length" class="text-amber-600">No hay facultades registradas. </span>
                        <Link href="/estructura/facultades/crear" class="font-medium text-[#2E75B6] hover:underline">+ Agregar facultad</Link>
                    </div>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
                <input v-model="form.nombre" type="text"
                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
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
                <Link href="/estructura/programas" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            </div>
        </form>
    </div>
</template>
