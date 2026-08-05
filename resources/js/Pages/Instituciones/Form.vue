<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
defineProps({ tipos: Array });

const inicial = () => ({
    tipo_id: '', nombre: '', pais: '', gestion: '',
    licenciamiento: 'desconocido', licenciamiento_resolucion: '', activa: true,
    carreras: [{ nombre: '' }],
});

const form = useForm(inicial());

// Borrador local: sobrevive a un refresco de la ventana.
const BORRADOR_KEY = 'institucion:nueva';
const borradorRestaurado = ref(false);

const camposBorrador = () => ({
    tipo_id: form.tipo_id,
    nombre: form.nombre,
    pais: form.pais,
    gestion: form.gestion,
    licenciamiento: form.licenciamiento,
    licenciamiento_resolucion: form.licenciamiento_resolucion,
    activa: form.activa,
    carreras: form.carreras,
});

onMounted(() => {
    const guardado = localStorage.getItem(BORRADOR_KEY);
    if (guardado) {
        try {
            Object.assign(form, JSON.parse(guardado));
            borradorRestaurado.value = true;
        } catch {
            localStorage.removeItem(BORRADOR_KEY);
        }
    }
    watch(camposBorrador, (val) => localStorage.setItem(BORRADOR_KEY, JSON.stringify(val)), { deep: true });
});

const descartarBorrador = () => {
    localStorage.removeItem(BORRADOR_KEY);
    Object.assign(form, inicial());
    form.clearErrors();
    borradorRestaurado.value = false;
};

const agregarCarrera = () => form.carreras.push({ nombre: '' });
const quitarCarrera = (i) => form.carreras.splice(i, 1);
const enviar = () => form.post('/instituciones', {
    onSuccess: () => localStorage.removeItem(BORRADOR_KEY),
});
</script>

<template>
    <div class="max-w-2xl">
        <h1 class="mb-6 text-2xl font-semibold text-[#1F3864]">Nueva institución externa</h1>

        <div v-if="borradorRestaurado"
             class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <span>Se restauraron datos sin guardar de una sesión anterior.</span>
            <button type="button" @click="descartarBorrador"
                    class="rounded-md border border-amber-300 px-3 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100">
                Descartar borrador
            </button>
        </div>

        <form @submit.prevent="enviar" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tipo <span class="text-red-500">*</span></label>
                    <select v-model="form.tipo_id" required
                            :class="form.errors.tipo_id ? 'border-red-400 ring-1 ring-red-300' : 'border-slate-300'"
                            class="w-full rounded-md text-sm">
                        <option value="" disabled>Seleccione</option>
                        <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                    </select>
                    <p v-if="form.errors.tipo_id" class="mt-1 text-xs text-red-600">{{ form.errors.tipo_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">País</label>
                    <input v-model="form.pais" type="text" class="w-full rounded-md border-slate-300 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Gestión</label>
                    <select v-model="form.gestion" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="">Sin especificar</option>
                        <option value="publica">Pública</option>
                        <option value="privada">Privada</option>
                    </select>
                    <p v-if="form.errors.gestion" class="mt-1 text-xs text-red-600">{{ form.errors.gestion }}</p>
                </div>
                <div>
                    <!-- Condiciona los requisitos del traslado externo (créditos mínimos, certificado SUNEDU). -->
                    <label class="mb-1 block text-sm font-medium text-slate-700">Licenciamiento SUNEDU</label>
                    <select v-model="form.licenciamiento" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="desconocido">Sin verificar</option>
                        <option value="licenciada">Licenciada por SUNEDU</option>
                        <option value="no_licenciada">No licenciada</option>
                    </select>
                    <p v-if="form.errors.licenciamiento" class="mt-1 text-xs text-red-600">{{ form.errors.licenciamiento }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Resolución de licenciamiento</label>
                    <input v-model="form.licenciamiento_resolucion" type="text" maxlength="120"
                           class="w-full rounded-md border-slate-300 text-sm" />
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre de la institución <span class="text-red-500">*</span></label>
                <input v-model="form.nombre" type="text" required
                       :class="form.errors.nombre ? 'border-red-400 ring-1 ring-red-300' : 'border-slate-300'"
                       class="w-full rounded-md text-sm" />
                <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Carreras de procedencia</label>
                <div v-for="(c, i) in form.carreras" :key="i" class="mb-2 flex items-center gap-2">
                    <input v-model="c.nombre" placeholder="Nombre de la carrera externa" class="flex-1 rounded-md border-slate-300 text-sm" />
                    <button type="button" @click="quitarCarrera(i)" v-if="form.carreras.length > 1" class="text-slate-400 hover:text-red-600">✕</button>
                </div>
                <button type="button" @click="agregarCarrera" class="text-sm text-[#2E75B6] hover:underline">+ Agregar carrera</button>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.activa" type="checkbox" class="rounded border-slate-300 text-[#2E75B6]" /> Activa
            </label>

            <div class="flex gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">Guardar</button>
                <Link href="/instituciones" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            </div>
        </form>
    </div>
</template>
