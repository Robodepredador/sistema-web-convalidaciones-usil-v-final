<script setup>
import { Link } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({ cargaId: Number });
const estado = ref({ estado: 'pendiente', total: 0, procesados: 0, errores: 0, porcentaje: 0, detalle: [] });
let timer = null;

const consultar = async () => {
    const { data } = await window.axios.get(`/mallas/importar/${props.cargaId}/progreso`);
    estado.value = data;
    if (['completado', 'fallido'].includes(data.estado)) clearInterval(timer);
};

onMounted(() => { consultar(); timer = setInterval(consultar, 1500); });
onUnmounted(() => clearInterval(timer));

const color = () => ({
    pendiente: 'bg-slate-400', procesando: 'bg-[#2E75B6]',
    completado: 'bg-green-600', fallido: 'bg-red-600',
}[estado.value.estado]);
</script>

<template>
    <div class="max-w-2xl">
        <VolverA href="/mallas" texto="Mallas curriculares" />
        <h1 class="mb-2 text-2xl font-semibold text-[#1F3864]">Procesando carga</h1>
        <p class="mb-6 text-sm text-slate-500">El procesamiento se ejecuta en segundo plano. Esta vista se actualiza sola.</p>

        <div class="rounded-lg border border-slate-200 bg-white p-6">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium capitalize text-slate-700">{{ estado.estado }}</span>
                <span class="text-slate-500">{{ estado.procesados }} / {{ estado.total }} ({{ estado.porcentaje }}%)</span>
            </div>
            <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full transition-all" :class="color()" :style="{ width: estado.porcentaje + '%' }"></div>
            </div>

            <div v-if="estado.errores > 0" class="mt-5">
                <h2 class="mb-2 text-sm font-medium text-red-700">{{ estado.errores }} errores</h2>
                <ul class="max-h-48 space-y-1 overflow-y-auto rounded-md bg-red-50 p-3 text-xs text-red-700">
                    <li v-for="(e, i) in estado.detalle" :key="i">Línea {{ e.linea }}: {{ e.mensaje }}</li>
                </ul>
            </div>

            <div v-if="['completado','fallido'].includes(estado.estado)" class="mt-6">
                <Link href="/mallas" class="rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E75B6]">
                    Ver mallas
                </Link>
            </div>
        </div>
    </div>
</template>
