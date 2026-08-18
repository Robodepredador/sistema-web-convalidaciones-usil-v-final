<script setup>
import { Link, router } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    instituciones: Array,
    facultades: Array,
    preseleccion: { type: Object, default: () => ({}) },
});

// Paso 1 y 2
const facultadId = ref('');
const carreraUsilId = ref('');
const institucionId = ref('');
const carreraExternaId = ref('');

const carreraUsilSeleccionada = computed(() => {
    for (const f of props.facultades) {
        const c = f.carreras?.find((car) => String(car.id) === String(carreraUsilId.value));
        if (c) return { ...c, facultad: f.nombre };
    }
    return null;
});

const carreraExternaSeleccionada = computed(() => {
    for (const inst of props.instituciones) {
        const c = inst.carreras?.find((car) => String(car.id) === String(carreraExternaId.value));
        if (c) return { ...c, institucion: inst.nombre };
    }
    return null;
});

const carrerasUsil = computed(() =>
    props.facultades.find((f) => String(f.id) === String(facultadId.value))?.carreras ?? []);
const carrerasExternas = computed(() =>
    props.instituciones.find((i) => String(i.id) === String(institucionId.value))?.carreras ?? []);

const onFacultad = () => { carreraUsilId.value = ''; reiniciarMapeo(); };
const onInstitucion = () => { carreraExternaId.value = ''; reiniciarMapeo(); };

// Estado de mapeo
const mapeando = ref(false);
const cargando = ref(false);
const error = ref('');

const cursosUsil = ref([]);
const cursosExternosDisponibles = ref([]);
const equivalencias = ref([]); 

// Filtros en pantalla de mapeo
const busqueda = ref('');
const filtroEstado = ref('todos'); // 'todos', 'mapeados', 'pendientes'
const cicloSeleccionado = ref('todos');

const reiniciarMapeo = () => {
    mapeando.value = false;
    cursosUsil.value = [];
    cursosExternosDisponibles.value = [];
    equivalencias.value = [];
    error.value = '';
    busqueda.value = '';
    filtroEstado.value = 'todos';
    cicloSeleccionado.value = 'todos';
};

const empezarMapeo = async () => {
    if (!carreraUsilId.value || !carreraExternaId.value) return;
    cargando.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/equivalencias-catalogo/cursos', {
            params: { carrera_externa_id: carreraExternaId.value, carrera_usil_id: carreraUsilId.value },
        });
        cursosUsil.value = data.mallaUsil;
        cursosExternosDisponibles.value = data.cursosExternos;
        equivalencias.value = data.equivalencias;
        mapeando.value = true;
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudieron cargar los cursos.';
    } finally {
        cargando.value = false;
    }
};

// Autocompletado y nuevos registros
const cursoAbierto = ref(null); 
const nombreExterno = ref('');
const estaEnFoco = ref(false);

const resultadosAutocompletar = computed(() => {
    const q = (nombreExterno.value || '').trim().toLowerCase();
    if (!q) return [];
    return cursosExternosDisponibles.value.filter(c => c.nombre.toLowerCase().includes(q));
});

const seleccionarAutocompletado = (cursoExterno) => {
    nombreExterno.value = cursoExterno.nombre;
    estaEnFoco.value = false;
};

const agregarOpcion = async (cursoUsilId) => {
    const nombre = (nombreExterno.value || '').trim();
    if (!nombre) return;
    
    error.value = '';
    try {
        const { data } = await axios.post('/equivalencias-catalogo', {
            carrera_usil_id: carreraUsilId.value,
            curso_usil_id: cursoUsilId,
            carrera_externa_id: carreraExternaId.value,
            nombre_externo: nombre,
        });
        
        const nuevoExterno = data.curso_externo;
        equivalencias.value.push({
            curso_usil_id: cursoUsilId,
            curso_externo_id: nuevoExterno.id,
            curso_externo: nuevoExterno
        });
        
        if (!cursosExternosDisponibles.value.some(c => c.id === nuevoExterno.id)) {
            cursosExternosDisponibles.value.push(nuevoExterno);
        }
        
        cursoAbierto.value = null;
        nombreExterno.value = '';
    } catch (e) {
        alert(e.response?.data?.message || 'Error al guardar.');
    }
};

const quitarOpcion = async (eq) => {
    if (!confirm(`¿Quitar la equivalencia de "${eq.curso_externo.nombre}"?`)) return;
    try {
        await axios.delete(`/equivalencias-catalogo/${eq.curso_usil_id}/${eq.curso_externo_id}`);
        equivalencias.value = equivalencias.value.filter(e => !(e.curso_usil_id === eq.curso_usil_id && e.curso_externo_id === eq.curso_externo_id));
    } catch (e) {
        alert(e.response?.data?.message || 'Error al quitar.');
    }
};

const getEquivalenciasDeCurso = (cursoUsilId) => equivalencias.value.filter(e => e.curso_usil_id === cursoUsilId);

const totalMapeados = computed(() => {
    const cursosConEq = new Set(equivalencias.value.map(e => e.curso_usil_id));
    return cursosConEq.size;
});
const totalPendientes = computed(() => Math.max(0, cursosUsil.value.length - totalMapeados.value));
const progresoPct = computed(() => (cursosUsil.value.length ? Math.round((totalMapeados.value / cursosUsil.value.length) * 100) : 0));

// Lista de ciclos disponibles
const ciclosDisponibles = computed(() => {
    const set = new Set(cursosUsil.value.map(c => c.ciclo ?? 0));
    return Array.from(set).sort((a, b) => a - b);
});

// Cursos filtrados y agrupados
const cursosFiltrados = computed(() => {
    const q = (busqueda.value || '').trim().toLowerCase();
    return cursosUsil.value.filter(c => {
        // Filtro ciclo
        if (cicloSeleccionado.value !== 'todos' && String(c.ciclo ?? 0) !== String(cicloSeleccionado.value)) {
            return false;
        }
        // Filtro estado
        const tieneEq = getEquivalenciasDeCurso(c.id).length > 0;
        if (filtroEstado.value === 'mapeados' && !tieneEq) return false;
        if (filtroEstado.value === 'pendientes' && tieneEq) return false;
        // Filtro texto
        if (q) {
            const matchCodigo = (c.codigo || '').toLowerCase().includes(q);
            const matchNombre = (c.curso || '').toLowerCase().includes(q);
            const matchEq = getEquivalenciasDeCurso(c.id).some(eq => (eq.curso_externo?.nombre || '').toLowerCase().includes(q));
            if (!matchCodigo && !matchNombre && !matchEq) return false;
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
        .map((n) => {
            const todosCiclo = cursosUsil.value.filter(c => (c.ciclo ?? 0) === n);
            const mapeadosCiclo = todosCiclo.filter(c => getEquivalenciasDeCurso(c.id).length > 0);
            return {
                numero: n,
                cursos: porCiclo[n],
                totalCiclo: todosCiclo.length,
                mapeadosCiclo: mapeadosCiclo.length,
            };
        });
});

// Aplicar preselección al inicio
const aplicarPreseleccion = () => {
    if (props.preseleccion?.carrera_usil_id && props.preseleccion?.carrera_externa_id) {
        const usilId = props.preseleccion.carrera_usil_id;
        const extId = props.preseleccion.carrera_externa_id;
        
        const facultad = props.facultades.find(f => f.carreras?.some(c => String(c.id) === String(usilId)));
        if (facultad) {
            facultadId.value = facultad.id;
            carreraUsilId.value = usilId;
        }
        
        for (const inst of props.instituciones) {
            const carrera = inst.carreras?.find((c) => String(c.id) === String(extId));
            if (carrera) {
                institucionId.value = inst.id;
                carreraExternaId.value = extId;
                break;
            }
        }
        
        if (carreraUsilId.value && carreraExternaId.value) {
            empezarMapeo();
        }
    }
};
aplicarPreseleccion();
</script>

<template>
    <div class="w-full pb-16">
        <VolverA href="/equivalencias-catalogo" texto="Catálogo de equivalencias" />

        <!-- HERO HEADER BANNER USIL -->
        <div class="mb-6 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] shadow-xl text-white">
            <!-- Decorative blur background -->
            <div class="absolute -top-24 -right-24 w-80 h-80 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-8">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 mb-2.5 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">
                                {{ mapeando ? 'Matriz de Homologación' : 'Configuración de Mapeo' }}
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Mapeo de equivalencias por curso
                        </h1>

                        <!-- Detalle de carreras enlazadas cuando está activo el mapeo -->
                        <div v-if="mapeando" class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                            <span class="bg-white/15 px-3 py-1 rounded-xl border border-white/20 font-semibold text-white">
                                <span class="text-blue-200">USIL:</span> {{ carreraUsilSeleccionada?.nombre || 'Carrera USIL' }}
                            </span>
                            <span class="text-blue-200 font-bold">⇄</span>
                            <span class="bg-white/15 px-3 py-1 rounded-xl border border-white/20 font-semibold text-white">
                                <span class="text-blue-200">Origen:</span> {{ carreraExternaSeleccionada?.nombre || 'Carrera Externa' }} ({{ carreraExternaSeleccionada?.institucion || 'Externa' }})
                            </span>
                        </div>
                        <p v-else class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed">
                            Selecciona la carrera de destino USIL y la carrera externa de procedencia para definir sus equivalencias curso por curso.
                        </p>
                    </div>

                    <!-- KPI Micro-strip de Progreso (cuando mapea) -->
                    <div v-if="mapeando" class="flex flex-col gap-2 min-w-[240px] bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/15">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-blue-100">Progreso de la malla</span>
                            <span class="font-mono font-bold text-white text-sm">{{ progresoPct }}%</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-white/20">
                            <div class="h-full rounded-full bg-emerald-400 transition-all duration-500" :style="{ width: progresoPct + '%' }"></div>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-blue-100/80 pt-1 border-t border-white/10">
                            <span><b>{{ totalMapeados }}</b> mapeados</span>
                            <span><b>{{ totalPendientes }}</b> pendientes</span>
                            <span><b>{{ cursosUsil.length }}</b> total</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="error" class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-medium text-rose-700 shadow-xs">{{ error }}</p>

        <!-- ======================= ESTADO 1: SELECCIÓN DE CARRERAS ======================= -->
        <div v-if="!mapeando" class="space-y-6">
            <div class="grid gap-6 md:grid-cols-2">
                <!-- SECCIÓN 1: CARRERA USIL DESTINO -->
                <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-7 shadow-xs">
                    <div class="flex items-center gap-3 mb-5 pb-3.5 border-b border-slate-100">
                        <span class="w-7 h-7 rounded-xl bg-blue-50 text-[#1F3864] font-bold text-xs flex items-center justify-center border border-blue-100">
                            1
                        </span>
                        <div>
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Carrera USIL (Destino)</h2>
                            <p class="text-[11px] text-slate-400">Plan de estudios y cursos a convalidar.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Facultad</label>
                            <select v-model="facultadId" @change="onFacultad"
                                    class="w-full rounded-xl border-slate-300 py-2.5 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                                <option value="">Seleccione una facultad…</option>
                                <option v-for="f in facultades" :key="f.id" :value="f.id">{{ f.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Carrera USIL</label>
                            <select v-model="carreraUsilId" @change="reiniciarMapeo" :disabled="!facultadId"
                                    class="w-full rounded-xl border-slate-300 py-2.5 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6] disabled:bg-slate-50 disabled:text-slate-400">
                                <option value="">Seleccione una carrera…</option>
                                <option v-for="c in carrerasUsil" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: CARRERA DE ORIGEN EXTERNA -->
                <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-7 shadow-xs">
                    <div class="flex items-center gap-3 mb-5 pb-3.5 border-b border-slate-100">
                        <span class="w-7 h-7 rounded-xl bg-blue-50 text-[#1F3864] font-bold text-xs flex items-center justify-center border border-blue-100">
                            2
                        </span>
                        <div>
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Carrera de Origen (Externo)</h2>
                            <p class="text-[11px] text-slate-400">Universidad o instituto de procedencia.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Universidad o Instituto</label>
                            <select v-model="institucionId" @change="onInstitucion"
                                    class="w-full rounded-xl border-slate-300 py-2.5 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                                <option value="">Seleccione una institución…</option>
                                <option v-for="i in instituciones" :key="i.id" :value="i.id">{{ i.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Carrera Externa</label>
                            <select v-model="carreraExternaId" @change="reiniciarMapeo" :disabled="!institucionId"
                                    class="w-full rounded-xl border-slate-300 py-2.5 text-xs font-medium text-slate-800 focus:border-[#2E75B6] focus:ring-[#2E75B6] disabled:bg-slate-50 disabled:text-slate-400">
                                <option value="">Seleccione carrera de origen…</option>
                                <option v-for="c in carrerasExternas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button @click="empezarMapeo" :disabled="!carreraUsilId || !carreraExternaId || cargando"
                        class="inline-flex items-center gap-2 px-8 py-3 rounded-2xl bg-gradient-to-r from-[#1F3864] to-[#2E75B6] text-xs font-bold text-white shadow-md hover:shadow-lg transition-all disabled:opacity-50">
                    <svg v-if="cargando" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>{{ cargando ? 'Cargando malla…' : 'Empezar a mapear →' }}</span>
                </button>
            </div>
        </div>

        <!-- ======================= ESTADO 2: MATRIZ DE MAPEO ACTIVA ======================= -->
        <div v-else class="space-y-5">
            <!-- TOOLBAR DE BÚSQUEDA Y FILTROS COMPACTOS -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-xs flex flex-wrap items-center justify-between gap-3">
                <!-- Buscador de cursos -->
                <div class="relative flex-1 min-w-[220px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input v-model="busqueda" type="text"
                           placeholder="Buscar curso USIL o equivalente (código, nombre)…"
                           class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>

                <!-- Filtros rápidos de estado -->
                <div class="flex items-center gap-1.5 bg-slate-100/80 p-1 rounded-xl border border-slate-200/60">
                    <button type="button" @click="filtroEstado = 'todos'"
                            :class="filtroEstado === 'todos' ? 'bg-white text-[#1F3864] font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all">
                        Todos ({{ cursosUsil.length }})
                    </button>
                    <button type="button" @click="filtroEstado = 'mapeados'"
                            :class="filtroEstado === 'mapeados' ? 'bg-white text-emerald-700 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all">
                        Mapeados ({{ totalMapeados }})
                    </button>
                    <button type="button" @click="filtroEstado = 'pendientes'"
                            :class="filtroEstado === 'pendientes' ? 'bg-white text-amber-700 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all">
                        Pendientes ({{ totalPendientes }})
                    </button>
                </div>

                <!-- Selector de ciclo -->
                <div class="flex items-center gap-1.5 text-xs text-slate-500 font-medium">
                    <span>Ciclo:</span>
                    <select v-model="cicloSeleccionado"
                            class="rounded-xl border-slate-200 py-1.5 pl-2.5 pr-8 text-xs font-bold text-slate-700 focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                        <option value="todos">Todos los ciclos</option>
                        <option v-for="n in ciclosDisponibles" :key="n" :value="n">Ciclo {{ n }}</option>
                    </select>
                </div>
            </div>

            <!-- MATRIZ DE CICLOS Y CURSOS COMPACTA -->
            <div class="space-y-4">
                <div v-for="grupo in gruposUsil" :key="grupo.numero"
                     class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs">
                    
                    <!-- Encabezado de Ciclo -->
                    <div class="bg-slate-50/90 px-6 py-3 border-b border-slate-200/70 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="h-2 w-2 rounded-full bg-[#2E75B6]"></span>
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-700">
                                Ciclo {{ grupo.numero }}
                            </h3>
                            <span class="text-xs text-slate-400 font-medium">({{ grupo.cursos.length }} cursos en vista)</span>
                        </div>
                        
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                             :class="grupo.mapeadosCiclo === grupo.totalCiclo ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-[#2E75B6] border border-blue-100'">
                            <span>{{ grupo.mapeadosCiclo }} de {{ grupo.totalCiclo }} mapeados</span>
                        </div>
                    </div>

                    <!-- Lista de Cursos del Ciclo en formato compacto -->
                    <div class="divide-y divide-slate-100">
                        <div v-for="c in grupo.cursos" :key="c.id"
                             class="p-4 sm:px-6 hover:bg-slate-50/60 transition-colors">
                            
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <!-- Datos del Curso USIL -->
                                <div class="flex items-start gap-3 min-w-0">
                                    <span class="font-mono text-[11px] font-bold px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 border border-slate-200 shrink-0 mt-0.5">
                                        {{ c.codigo || 'S/C' }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-xs sm:text-sm font-bold text-slate-800 truncate">{{ c.curso }}</p>
                                            <span class="text-[11px] font-semibold text-slate-400 shrink-0">{{ c.creditos }} cr.</span>
                                        </div>

                                        <!-- Opciones / Cursos Equivalentes Mapeados -->
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <template v-if="getEquivalenciasDeCurso(c.id).length > 0">
                                                <div v-for="eq in getEquivalenciasDeCurso(c.id)" :key="eq.curso_externo_id"
                                                     class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-900 text-xs font-semibold shadow-2xs group">
                                                    <span>{{ eq.curso_externo.nombre }}</span>
                                                    <span v-if="eq.curso_externo.creditos" class="text-[10px] text-emerald-700 font-normal">({{ eq.curso_externo.creditos }} cr.)</span>
                                                    <button type="button" @click="quitarOpcion(eq)"
                                                            class="ml-0.5 text-emerald-500 hover:text-rose-600 transition-colors p-0.5 rounded"
                                                            title="Eliminar equivalencia">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </template>
                                            <span v-else class="text-[11px] text-slate-400 italic font-medium">
                                                Sin equivalencia registrada
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botón Añadir Opción -->
                                <div class="shrink-0 flex items-center self-end sm:self-center">
                                    <button v-if="cursoAbierto !== c.id"
                                            @click="cursoAbierto = c.id; nombreExterno = ''; estaEnFoco = false"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-blue-50 text-[#2E75B6] hover:bg-[#2E75B6] hover:text-white transition-all text-xs font-bold shadow-2xs">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        <span>Añadir opción</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Formulario Desplegable para Añadir Opción (Inline Drawer) -->
                            <div v-if="cursoAbierto === c.id"
                                 class="mt-3 pt-3 border-t border-slate-100 relative bg-slate-50/80 p-3 rounded-2xl border border-blue-100">
                                <div class="flex flex-wrap items-center gap-2 relative">
                                    <div class="relative flex-1 min-w-[200px]">
                                        <input type="text" v-model="nombreExterno"
                                               placeholder="Escribe o selecciona el nombre del curso de origen…"
                                               @focus="estaEnFoco = true"
                                               @blur="setTimeout(() => estaEnFoco = false, 180)"
                                               @keydown.enter="agregarOpcion(c.id)"
                                               autofocus
                                               class="w-full rounded-xl border border-slate-300 bg-white py-2 px-3 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />

                                        <!-- Autocompletado Dropdown -->
                                        <div v-if="estaEnFoco && resultadosAutocompletar.length > 0"
                                             class="absolute left-0 right-0 top-full mt-1 max-h-48 overflow-y-auto rounded-2xl bg-white shadow-xl border border-slate-200 z-30 py-1">
                                            <button v-for="rc in resultadosAutocompletar" :key="rc.id"
                                                    @mousedown.prevent="seleccionarAutocompletado(rc)"
                                                    type="button"
                                                    class="w-full text-left px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-blue-50 hover:text-[#2E75B6] border-b border-slate-50 last:border-0 flex items-center justify-between">
                                                <span>{{ rc.nombre }}</span>
                                                <span v-if="rc.creditos" class="text-[10px] text-slate-400">({{ rc.creditos }} cr.)</span>
                                            </button>
                                        </div>
                                    </div>

                                    <button type="button" @click="agregarOpcion(c.id)"
                                            class="px-4 py-2 rounded-xl bg-[#1F3864] text-xs font-bold text-white hover:bg-[#2E75B6] transition-colors shadow-2xs">
                                        Guardar
                                    </button>
                                    <button type="button" @click="cursoAbierto = null"
                                            class="px-3 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                                <p class="mt-1.5 text-[11px] text-slate-400">
                                    Presiona <b>Enter</b> para guardar. Si el curso ya existe en el catálogo externo, se asociará de inmediato.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estado Vacío cuando se filtra -->
                <div v-if="gruposUsil.length === 0"
                     class="bg-white rounded-3xl border border-slate-200/80 p-12 text-center shadow-xs">
                    <p class="text-xs font-bold text-slate-600 uppercase tracking-wider">No se encontraron cursos con los filtros aplicados</p>
                    <p class="text-xs text-slate-400 mt-1">Prueba cambiando el texto de búsqueda o el filtro de estado.</p>
                </div>
            </div>

            <!-- Footer de Acciones -->
            <div class="flex items-center justify-end pt-3">
                <button type="button" @click="router.get('/equivalencias-catalogo')"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#1F3864] text-xs font-bold text-white hover:bg-[#2E75B6] transition-all shadow-xs">
                    Volver al catálogo
                </button>
            </div>
        </div>
    </div>
</template>

