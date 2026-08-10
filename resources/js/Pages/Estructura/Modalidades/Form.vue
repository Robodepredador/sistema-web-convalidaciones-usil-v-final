<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import VolverA from '../../../Components/VolverA.vue';

const props = defineProps({ modalidad: Object });
const editando = !!props.modalidad;

const form = useForm({
    codigo: props.modalidad?.codigo ?? '',
    nombre: props.modalidad?.nombre ?? '',
    activo: props.modalidad?.activo ?? true,
});

const enviar = () => {
    if (editando) form.put(`/estructura/modalidades/${props.modalidad.id}`);
    else form.post('/estructura/modalidades');
};
</script>

<template>
    <div class="max-w-2xl">
        <VolverA href="/estructura/modalidades" texto="Modalidades" />
        <h1 class="mb-6 text-2xl font-semibold text-[#1F3864]">{{ editando ? 'Editar modalidad' : 'Nueva modalidad' }}</h1>

        <form @submit.prevent="enviar" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Código</label>
                    <input v-model="form.codigo" type="text" maxlength="20"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.codigo" class="mt-1 text-xs text-red-600">{{ form.errors.codigo }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
                    <input v-model="form.nombre" type="text" placeholder="Presencial, Semipresencial, Virtual…"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
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
                <Link href="/estructura/modalidades" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            </div>
        </form>
    </div>
</template>
