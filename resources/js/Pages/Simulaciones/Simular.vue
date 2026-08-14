<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import VolverA from '../../Components/VolverA.vue';

const props = defineProps({
    postulante: Object,
    cursosMalla: Array,
    documentos: Array,
    tieneMalla: Boolean,
    edicion: { type: Object, default: null },
    simulacionesPrevias: Array,
});

const editando = !!props.edicion;
const observaciones = ref(props.edicion?.observaciones ?? '');
const procesando = ref(false);
const mensaje = ref(null);

let mensajeTimer = null;
watch(mensaje, (m) => {
    if (mensajeTimer) { clearTimeout(mensajeTimer); mensajeTimer = null; }
    if (m) mensajeTimer = setTimeout(() => { mensaje.value = null; }, m.tipo === 'ok' ? 4000 : 6000);
});

// seleccion holds the mapping: curso_usil_id -> curso_externo_id
const seleccion = reactive({});
props.cursosMalla.forEach(c => {
    seleccion[c.id] = null;
});

if (props.edicion?.filas?.length) {
    props.edicion.filas.forEach(f => {
        if (f.curso_usil_id) {
            seleccion[f.curso_usil_id] = f.curso_externo_id || null;
        }
    });
}

const gruposUsil = computed(() => {
    const porCiclo = {};
    for (const c of props.cursosMalla) {
        (porCiclo[c.ciclo ?? 0] ??= []).push(c);
    }
    return Object.keys(porCiclo).map(Number).sort((a, b) => a - b)
        .map((n) => ({ numero: n, cursos: porCiclo[n] }));
});

const convalidados = computed(() => {
    return props.cursosMalla.filter(c => seleccion[c.id]);
});

const creditosValidados = computed(() => {
    return convalidados.value.reduce((s, c) => s + (Number(c.creditos) || 0), 0);
});

const guardar = () => {
    if (!props.tieneMalla) return;
    
    const filas = props.cursosMalla.map(c => {
        const extId = seleccion[c.id];
        const externo = extId ? c.opciones?.find(o => o.id === extId) : null;
        
        return {
            curso_usil_id: c.id,
            curso_externo_id: extId || null,
            curso_origen_nombre: externo ? externo.nombre : null,
            creditos_origen: externo ? externo.creditos : null,
            clasificacion: extId ? 'convalidable' : null,
            origen: 'manual'
        };
    });

    const payload = {
        postulante_id: props.postulante.id,
        carrera_usil_id: props.postulante.carrera_destino_id,
        metodo: 'manual',
        observaciones: observaciones.value,
        filas
    };

    procesando.value = true;
    mensaje.value = null;
    const peticion = editando
        ? window.axios.put(`/simulaciones/${props.edicion.id}`, payload)
        : window.axios.post('/simulaciones', payload);
        
    peticion
        .then(({ data }) => {
            router.get(`/simulaciones/${data.id}`);
        })
        .catch((e) => {
            procesando.value = false;
            const errs = e.response?.data?.errors;
            mensaje.value = { tipo: 'error', texto: errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'No se pudo guardar. Revisa los datos.') };
        })
        .finally(() => { procesando.value = false; });
};

const eliminarSimulacion = (s) => {
    const motivo = window.prompt(`Motivo para eliminar la simulación #${s.id} (quedará registrado en la base de datos):`);
    if (motivo === null) return;
    if (motivo.trim().length < 5) { alert('El motivo debe tener al menos 5 caracteres.'); return; }
    router.delete(`/simulaciones/${s.id}`, { data: { motivo: motivo.trim() }, preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-4xl pb-12">
        <VolverA href="/simulaciones" texto="Simulaciones" class="mb-4" />
        
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="font-heading text-xs font-bold uppercase tracking-wide text-[#2E75B6]">
                    {{ editando ? `Editar simulación #${edicion.id}` : 'Simulación de convalidación' }}
                </p>
                <h1 class="mt-0.5 font-heading text-2xl font-extrabold text-[#1F3864]">
                    {{ postulante.institucion || postulante.carrera_externa || '—' }}
                    <span class="font-semibold text-slate-400">→</span>
                    {{ postulante.carrera_destino || '— sin carrera —' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500"><span class="font-medium text-slate-700">{{ postulante.nombre }}</span> · {{ postulante.documento }}</p>
            </div>
            
            <div v-if="documentos?.length" class="inline-flex min-w-0 max-w-[17rem] shrink-0 items-center gap-2.5 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <svg class="h-5 w-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                <span class="min-w-0">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Récord académico</span>
                    <a v-if="documentos[0].url" :href="documentos[0].url" target="_blank" rel="noopener"
                        class="block truncate text-sm font-medium text-[#2E75B6] hover:underline">{{ documentos[0].nombre }}</a>
                    <span v-else class="block truncate text-sm font-medium text-slate-700">{{ documentos[0].nombre }}</span>
                </span>
            </div>
        </div>

        <Transition
            enter-active-class="transition duration-300 ease-out" enter-from-class="translate-x-8 opacity-0" enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in" leave-from-class="translate-x-0 opacity-100" leave-to-class="translate-x-8 opacity-0">
            <div v-if="mensaje" role="alert"
                 class="fixed right-4 top-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] items-start gap-3 rounded-xl border bg-white p-4 shadow-lg"
                 :class="mensaje.tipo === 'ok' ? 'border-emerald-200' : 'border-red-200'">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white"
                      :class="mensaje.tipo === 'ok' ? 'bg-emerald-500' : 'bg-red-500'">
                    <svg v-if="mensaje.tipo === 'ok'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold" :class="mensaje.tipo === 'ok' ? 'text-emerald-800' : 'text-red-800'">{{ mensaje.tipo === 'ok' ? 'Listo' : 'Atención' }}</p>
                    <p class="mt-0.5 break-words text-sm text-slate-600">{{ mensaje.texto }}</p>
                </div>
                <button type="button" @click="mensaje = null" title="Cerrar" class="shrink-0 text-slate-300 hover:text-slate-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </Transition>

        <div v-if="!postulante.carrera_destino_id" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            El postulante no tiene carrera destino USIL. Edítalo antes de simular.
        </div>
        <div v-else-if="!tieneMalla" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            La carrera destino no tiene un plan de estudios (malla) cargado. Carga la malla para poder mapear cursos.
        </div>
        <div v-else-if="!cursosMalla.length" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            El plan de estudios de <strong>{{ postulante.carrera_destino }}</strong> no tiene cursos cargados, por lo que no hay a qué convalidar. Carga los cursos de la malla en <strong>Estructura → Mallas</strong>.
        </div>

        <template v-if="tieneMalla && cursosMalla.length">
            <div class="mb-4 grid grid-cols-2 gap-3 text-center sm:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"><p class="text-2xl font-bold text-[#1F3864]">{{ cursosMalla.length }}</p><p class="text-xs text-slate-500">Cursos USIL</p></div>
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"><p class="text-2xl font-bold text-emerald-600">{{ convalidados.length }}</p><p class="text-xs text-slate-500">Cursos Convalidados</p></div>
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"><p class="text-2xl font-bold text-[#2E75B6]">{{ creditosValidados }}</p><p class="text-xs text-slate-500">Créditos Reconocidos</p></div>
            </div>
            
            <div class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="flex items-center gap-2 bg-[#1F3864] px-4 py-2.5 text-white">
                    <span class="font-heading text-sm font-bold">Malla USIL</span>
                    <span class="ml-auto rounded bg-white/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">Equivalencias</span>
                </div>
                
                <div v-for="grupo in gruposUsil" :key="grupo.numero">
                    <p class="sticky top-0 z-[1] bg-slate-50 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500 border-y border-slate-200/50">Ciclo {{ grupo.numero }}</p>
                    <div v-for="curso in grupo.cursos" :key="curso.id" class="flex flex-col sm:flex-row gap-3 py-3 px-4 border-b border-slate-100 last:border-0 hover:bg-slate-50/50">
                        <div class="sm:w-5/12 min-w-0 pt-1">
                            <p class="truncate font-mono text-[11px] text-slate-400">{{ curso.codigo }}</p>
                            <p class="text-sm font-medium text-slate-800">{{ curso.curso }}</p>
                            <p class="text-xs text-slate-500">{{ curso.creditos }} cr.</p>
                        </div>
                        <div class="sm:w-7/12 flex flex-col justify-center">
                            <select v-if="curso.opciones?.length" v-model="seleccion[curso.id]" class="w-full text-sm border-slate-300 rounded-md focus:border-[#2E75B6] focus:ring-[#2E75B6]" :class="seleccion[curso.id] ? 'bg-emerald-50 border-emerald-300 text-emerald-800 font-semibold' : ''">
                                <option :value="null">— Selecciona el curso externo aprobado —</option>
                                <option v-for="opc in curso.opciones" :key="opc.id" :value="opc.id">{{ opc.nombre }}</option>
                            </select>
                            <div v-else class="rounded-md border border-dashed border-slate-200 bg-slate-50/50 px-3 py-2 text-xs text-slate-400 flex items-center">
                                Sin equivalencias autorizadas en el catálogo.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                <textarea v-model="observaciones" rows="3" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6] shadow-sm"></textarea>
            </div>

            <div class="flex gap-3">
                <button @click="guardar" :disabled="procesando"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-semibold text-white hover:bg-[#2E75B6] disabled:opacity-50 transition">
                    {{ procesando ? 'Guardando…' : 'Guardar Convalidación' }}
                </button>
                <Link href="/simulaciones" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 bg-white transition">Cancelar</Link>
            </div>
        </template>

        <div v-if="simulacionesPrevias?.length" class="mt-12">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-400">Simulaciones previas</h2>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm"><tbody class="divide-y divide-slate-100">
                    <tr v-for="s in simulacionesPrevias" :key="s.id" class="hover:bg-slate-50/70">
                        <td class="px-4 py-2 text-slate-600">#{{ s.id }} · {{ s.fecha }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ s.carrera }}</td>
                        <td class="px-4 py-2 text-right">
                            <Link :href="`/simulaciones/${s.id}/editar`" class="mr-3 text-[#2E75B6] hover:underline">Editar</Link>
                            <Link :href="`/simulaciones/${s.id}`" class="mr-3 text-slate-500 hover:underline">Ver</Link>
                            <button type="button" @click="eliminarSimulacion(s)" class="text-red-600 hover:underline">Eliminar</button>
                        </td>
                    </tr>
                </tbody></table>
            </div>
        </div>
    </div>
</template>
