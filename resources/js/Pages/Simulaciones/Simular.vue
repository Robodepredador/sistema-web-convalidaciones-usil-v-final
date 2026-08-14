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

// Filtros y búsqueda reactivos en la matriz de cursos
const busqueda = ref('');
const cicloFiltro = ref('todos');
const estadoFiltro = ref('todos'); // 'todos' | 'con_opciones' | 'convalidados' | 'pendientes'

const listaCiclos = computed(() => {
    const set = new Set(props.cursosMalla.map(c => c.ciclo ?? 0).filter(Boolean));
    return Array.from(set).sort((a, b) => Number(a) - Number(b));
});

const hayFiltrosActivos = computed(() =>
    Boolean(busqueda.value.trim() || cicloFiltro.value !== 'todos' || estadoFiltro.value !== 'todos'));

const limpiarFiltros = () => {
    busqueda.value = '';
    cicloFiltro.value = 'todos';
    estadoFiltro.value = 'todos';
};

const cursosFiltrados = computed(() => {
    const q = busqueda.value.trim().toLowerCase();
    return props.cursosMalla.filter(c => {
        if (cicloFiltro.value !== 'todos' && Number(c.ciclo) !== Number(cicloFiltro.value)) return false;
        
        if (estadoFiltro.value === 'con_opciones' && (!c.opciones || c.opciones.length === 0)) return false;
        if (estadoFiltro.value === 'convalidados' && !seleccion[c.id]) return false;
        if (estadoFiltro.value === 'pendientes' && seleccion[c.id]) return false;

        if (q) {
            const matchNombre = (c.curso || '').toLowerCase().includes(q);
            const matchCodigo = (c.codigo || '').toLowerCase().includes(q);
            const matchOpciones = (c.opciones || []).some(o => (o.nombre || '').toLowerCase().includes(q));
            if (!matchNombre && !matchCodigo && !matchOpciones) return false;
        }
        return true;
    });
});

const gruposUsil = computed(() => {
    const porCiclo = {};
    for (const c of cursosFiltrados.value) {
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
    <div class="max-w-5xl mx-auto pb-16">
        <VolverA href="/simulaciones" texto="Volver a Simulaciones" class="mb-4" />

        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-20 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">
                                {{ editando ? `Editar simulación #${edicion.id}` : 'Simulación de convalidación' }}
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight flex flex-wrap items-center gap-2">
                            <span>{{ postulante.institucion || postulante.carrera_externa || '—' }}</span>
                            <span class="text-blue-300 font-normal">→</span>
                            <span class="text-white">{{ postulante.carrera_destino || '— sin carrera —' }}</span>
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 flex flex-wrap items-center gap-2">
                            <span class="font-bold text-white">{{ postulante.nombre }}</span>
                            <span class="text-blue-300">•</span>
                            <span class="font-mono bg-white/10 px-2 py-0.5 rounded-md border border-white/10 text-xs">{{ postulante.documento }}</span>
                        </p>
                    </div>

                    <div v-if="documentos?.length" class="shrink-0 bg-white/10 border border-white/20 backdrop-blur-md rounded-2xl p-3.5 flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl bg-white/15 flex items-center justify-center text-blue-200">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <div class="min-w-0 max-w-[200px]">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-blue-200">Récord Académico</div>
                            <a v-if="documentos[0].url" :href="documentos[0].url" target="_blank" rel="noopener"
                               class="block truncate text-xs font-bold text-white hover:underline">{{ documentos[0].nombre }}</a>
                            <span v-else class="block truncate text-xs font-bold text-white">{{ documentos[0].nombre }}</span>
                        </div>
                    </div>
                </div>

                <!-- Tira de 3 Micro-KPIs en Vivo -->
                <div v-if="tieneMalla && cursosMalla.length" class="grid grid-cols-3 gap-4 pt-6">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10 text-center sm:text-left">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Cursos USIL</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ cursosMalla.length }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Total de la malla destino</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10 text-center sm:text-left">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Cursos Convalidados</div>
                        <div class="text-2xl font-extrabold text-emerald-300 mt-1">{{ convalidados.length }}</div>
                        <div class="text-[10px] text-emerald-200/80 mt-0.5">Asignados en esta sesión</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10 text-center sm:text-left">
                        <div class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Créditos Reconocidos</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ creditosValidados }} <span class="text-xs font-normal text-blue-200">cr.</span></div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Total acumulado</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast de mensaje -->
        <Transition
            enter-active-class="transition duration-300 ease-out" enter-from-class="translate-x-8 opacity-0" enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in" leave-from-class="translate-x-0 opacity-100" leave-to-class="translate-x-8 opacity-0">
            <div v-if="mensaje" role="alert"
                 class="fixed right-6 top-6 z-50 flex w-80 max-w-[calc(100vw-2rem)] items-start gap-3 rounded-2xl border bg-white p-4 shadow-xl"
                 :class="mensaje.tipo === 'ok' ? 'border-emerald-200' : 'border-red-200'">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white"
                      :class="mensaje.tipo === 'ok' ? 'bg-emerald-500' : 'bg-red-500'">
                    <svg v-if="mensaje.tipo === 'ok'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold uppercase tracking-wider" :class="mensaje.tipo === 'ok' ? 'text-emerald-800' : 'text-red-800'">{{ mensaje.tipo === 'ok' ? 'Listo' : 'Atención' }}</p>
                    <p class="mt-0.5 break-words text-xs font-medium text-slate-600">{{ mensaje.texto }}</p>
                </div>
                <button type="button" @click="mensaje = null" title="Cerrar" class="shrink-0 text-slate-400 hover:text-slate-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </Transition>

        <!-- Estados de advertencia -->
        <div v-if="!postulante.carrera_destino_id" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-xs font-medium text-red-700">
            El postulante no tiene carrera destino USIL asignada. Configúralo antes de simular.
        </div>
        <div v-else-if="!tieneMalla" class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-medium text-amber-700">
            La carrera destino no tiene un plan de estudios (malla) activo cargado.
        </div>
        <div v-else-if="!cursosMalla.length" class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-medium text-amber-700">
            El plan de estudios de <strong>{{ postulante.carrera_destino }}</strong> no tiene cursos registrados.
        </div>

        <!-- Matriz de Convalidación y Filtros -->
        <template v-if="tieneMalla && cursosMalla.length">
            <!-- ======================= PANEL DE BÚSQUEDA Y FILTROS DE CURSOS ======================= -->
            <div class="mb-6 bg-white rounded-3xl border border-slate-200/80 p-5 shadow-xs">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Buscar curso -->
                    <div class="sm:col-span-2 lg:col-span-2">
                        <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Buscar curso en la malla</label>
                        <div class="relative">
                            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <input v-model="busqueda" type="text"
                                   placeholder="Nombre de curso, código USIL (ej. M1-01) o curso externo…"
                                   class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                        </div>
                    </div>

                    <!-- Filtro Ciclo -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Filtrar por Ciclo</label>
                        <select v-model="cicloFiltro"
                                class="w-full rounded-xl border-slate-200 py-2 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="todos">Todos los ciclos</option>
                            <option v-for="c in listaCiclos" :key="c" :value="c">Ciclo {{ c }}</option>
                        </select>
                    </div>

                    <!-- Filtro Estado de Convalidación -->
                    <div>
                        <label class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-700">Estado de Asignación</label>
                        <select v-model="estadoFiltro"
                                class="w-full rounded-xl border-slate-200 py-2 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="todos">Todos los cursos</option>
                            <option value="con_opciones">Con equivalencia en catálogo</option>
                            <option value="convalidados">Convalidados ({{ convalidados.length }})</option>
                            <option value="pendientes">Pendientes / Sin convalidar</option>
                        </select>
                    </div>
                </div>

                <!-- Resumen de Filtros Activos -->
                <div v-if="hayFiltrosActivos" class="mt-4 pt-3.5 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-slate-400 font-medium">Mostrando <b>{{ cursosFiltrados.length }}</b> de <b>{{ cursosMalla.length }}</b> cursos</span>
                        <span v-if="busqueda" class="bg-blue-50 text-[#2E75B6] px-2.5 py-0.5 rounded-lg font-semibold text-[11px]">
                            "{{ busqueda }}"
                        </span>
                        <span v-if="cicloFiltro !== 'todos'" class="bg-blue-50 text-[#2E75B6] px-2.5 py-0.5 rounded-lg font-semibold text-[11px]">
                            Ciclo {{ cicloFiltro }}
                        </span>
                        <span v-if="estadoFiltro !== 'todos'" class="bg-blue-50 text-[#2E75B6] px-2.5 py-0.5 rounded-lg font-semibold text-[11px]">
                            {{ estadoFiltro === 'con_opciones' ? 'Con equivalencia' : estadoFiltro === 'convalidados' ? 'Convalidados' : 'Pendientes' }}
                        </span>
                    </div>

                    <button type="button" @click="limpiarFiltros"
                            class="text-xs font-bold text-slate-500 hover:text-red-600 transition-colors">
                        Limpiar filtros
                    </button>
                </div>
            </div>

            <!-- Matriz de Cursos Agrupados -->
            <div class="mb-6 bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="flex items-center justify-between bg-[#1F3864] px-6 py-3.5 text-white">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                        </svg>
                        <span class="font-extrabold text-xs tracking-wider uppercase">Matriz de Homologación de Malla</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-xl bg-white/10 border border-white/20 px-3 py-1 text-[11px] font-bold text-blue-100 uppercase tracking-wider">
                            {{ cursosFiltrados.length }} cursos mostrados
                        </span>
                    </div>
                </div>
                
                <div v-for="grupo in gruposUsil" :key="grupo.numero">
                    <div class="sticky top-0 z-[1] bg-slate-50/95 backdrop-blur-xs px-6 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-y border-slate-200/60 flex items-center justify-between">
                        <span>Ciclo {{ grupo.numero }}</span>
                        <span class="text-slate-400 font-mono font-normal">{{ grupo.cursos.length }} cursos</span>
                    </div>
                    
                    <div class="divide-y divide-slate-100">
                        <div v-for="curso in grupo.cursos" :key="curso.id" 
                             class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 py-3.5 px-6 hover:bg-slate-50/70 transition-colors">
                            <div class="sm:w-5/12 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">
                                        {{ curso.codigo }}
                                    </span>
                                    <span class="text-[10px] font-semibold text-slate-400">
                                        {{ curso.creditos }} cr.
                                    </span>
                                </div>
                                <p class="text-xs font-bold text-slate-800 leading-tight">{{ curso.curso }}</p>
                            </div>
                            
                            <div class="w-full sm:w-7/12">
                                <select v-if="curso.opciones?.length" v-model="seleccion[curso.id]" 
                                        class="w-full text-xs font-medium rounded-xl border-slate-200 py-2 focus:border-[#2E75B6] focus:ring-[#2E75B6] transition-all" 
                                        :class="seleccion[curso.id] ? 'bg-emerald-50 border-emerald-300 text-emerald-800 font-bold shadow-2xs' : 'text-slate-700'">
                                    <option :value="null">— Selecciona el curso externo aprobado —</option>
                                    <option v-for="opc in curso.opciones" :key="opc.id" :value="opc.id">{{ opc.nombre }}</option>
                                </select>
                                <div v-else class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-3 py-2 text-[11px] text-slate-400 flex items-center gap-2">
                                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    <span>Sin equivalencias autorizadas en el catálogo</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estado vacío al filtrar cursos -->
                <div v-if="cursosFiltrados.length === 0" class="p-12 text-center">
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-[#2E75B6] mx-auto">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </div>
                    <p class="font-bold text-slate-700 text-xs uppercase tracking-wider">No se encontraron cursos</p>
                    <p class="text-xs text-slate-400 mt-1 mb-4">Ningún curso coincide con los términos de búsqueda o filtros seleccionados.</p>
                    <button type="button" @click="limpiarFiltros"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                        Restablecer filtros
                    </button>
                </div>
            </div>

            <!-- Observaciones -->
            <div class="mb-6 bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-6 shadow-xs">
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-700">Observaciones y Sustento Académico</label>
                <textarea v-model="observaciones" rows="3" 
                          placeholder="Añade comentarios o consideraciones especiales para esta simulación…"
                          class="w-full rounded-2xl border-slate-200 text-xs font-medium focus:border-[#2E75B6] focus:ring-[#2E75B6] p-3"></textarea>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-center gap-3">
                <button @click="guardar" :disabled="procesando" type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#1F3864] to-[#2E75B6] px-6 py-2.5 text-xs font-bold text-white shadow-md hover:shadow-lg disabled:opacity-50 transition-all">
                    <svg v-if="!procesando" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span>{{ procesando ? 'Guardando convalidación…' : 'Guardar Convalidación' }}</span>
                </button>
                <Link href="/simulaciones" 
                      class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                    Cancelar
                </Link>
            </div>
        </template>

        <!-- Historial de Simulaciones Previas -->
        <div v-if="simulacionesPrevias?.length" class="mt-10">
            <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Simulaciones registradas anteriormente</h2>
            <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xs">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="s in simulacionesPrevias" :key="s.id" class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-3.5 text-slate-700 font-medium">
                                <span class="font-bold text-[#1F3864]">#{{ s.id }}</span> · {{ s.fecha }}
                            </td>
                            <td class="px-6 py-3.5 text-slate-600 font-semibold">{{ s.carrera }}</td>
                            <td class="px-6 py-3.5 text-right space-x-3">
                                <Link :href="`/simulaciones/${s.id}/editar`" class="font-bold text-[#2E75B6] hover:underline">Editar</Link>
                                <Link :href="`/simulaciones/${s.id}`" class="font-bold text-slate-600 hover:underline">Ver</Link>
                                <button type="button" @click="eliminarSimulacion(s)" class="font-bold text-red-600 hover:underline">Eliminar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

