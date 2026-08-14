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

const reiniciarMapeo = () => {
    mapeando.value = false;
    cursosUsil.value = [];
    cursosExternosDisponibles.value = [];
    equivalencias.value = [];
    error.value = '';
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

// Agrupación de la malla USIL
const gruposUsil = computed(() => {
    const porCiclo = {};
    for (const c of cursosUsil.value) {
        (porCiclo[c.ciclo ?? 0] ??= []).push(c);
    }
    return Object.keys(porCiclo).map(Number).sort((a, b) => a - b)
        .map((n) => ({ numero: n, cursos: porCiclo[n] }));
});

const getEquivalenciasDeCurso = (cursoUsilId) => equivalencias.value.filter(e => e.curso_usil_id === cursoUsilId);

const totalMapeados = computed(() => {
    const cursosConEq = new Set(equivalencias.value.map(e => e.curso_usil_id));
    return cursosConEq.size;
});
const progresoPct = computed(() => (cursosUsil.value.length ? Math.round((totalMapeados.value / cursosUsil.value.length) * 100) : 0));
const plural = (n, singular, plural_) => (n === 1 ? singular : plural_);

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
    <div>
        <div class="mb-6">
            <VolverA href="/equivalencias-catalogo" texto="Catálogo de equivalencias" />
            <h1 class="text-2xl font-semibold text-[#1F3864]">
                Mapeo de equivalencias por curso
            </h1>
        </div>

        <p v-if="error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</p>

        <!-- Selección de carreras -->
        <div v-if="!mapeando" class="space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">1 · Carrera USIL (Destino)</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Facultad</label>
                        <select v-model="facultadId" @change="onFacultad" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Selecciona…</option>
                            <option v-for="f in facultades" :key="f.id" :value="f.id">{{ f.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Carrera</label>
                        <select v-model="carreraUsilId" @change="reiniciarMapeo" :disabled="!facultadId"
                                class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6] disabled:bg-slate-50">
                            <option value="">Selecciona…</option>
                            <option v-for="c in carrerasUsil" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">2 · Carrera de origen (Externo)</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Universidad o instituto</label>
                        <select v-model="institucionId" @change="onInstitucion" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Selecciona…</option>
                            <option v-for="i in instituciones" :key="i.id" :value="i.id">{{ i.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Carrera de origen</label>
                        <select v-model="carreraExternaId" @change="reiniciarMapeo" :disabled="!institucionId"
                                class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6] disabled:bg-slate-50">
                            <option value="">Selecciona…</option>
                            <option v-for="c in carrerasExternas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button @click="empezarMapeo" :disabled="!carreraUsilId || !carreraExternaId || cargando"
                        class="rounded-md bg-[#2E75B6] px-6 py-3 text-sm font-semibold text-white hover:bg-[#1F3864] disabled:opacity-50">
                    {{ cargando ? 'Cargando…' : 'Empezar a mapear →' }}
                </button>
            </div>
        </div>

        <!-- Mapeo -->
        <div v-else>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm flex-1 max-w-xl">
                    <span class="whitespace-nowrap font-medium text-slate-700">
                        Mapeados: {{ totalMapeados }} de {{ cursosUsil.length }} {{ plural(cursosUsil.length, 'curso', 'cursos') }} de la malla
                    </span>
                    <div class="h-[7px] max-w-[150px] flex-1 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-[#2E75B6] transition-all" :style="{ width: progresoPct + '%' }"></div>
                    </div>
                </div>
                
                <button @click="mapeando = false" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Cambiar carreras
                </button>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-6">
                <div class="bg-[#1F3864] px-5 py-3 text-white">
                    <h2 class="font-heading font-bold text-lg">Malla de destino y opciones de origen</h2>
                </div>

                <div class="p-5">
                    <div v-for="grupo in gruposUsil" :key="grupo.numero" class="mb-6 last:mb-0">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Ciclo {{ grupo.numero }}</h3>
                        
                        <div class="space-y-3">
                            <div v-for="c in grupo.cursos" :key="c.id" class="rounded-lg border border-slate-200 p-4">
                                <div class="flex items-start justify-between">
                                    <div class="mb-3">
                                        <p class="font-mono text-[11px] text-slate-400">{{ c.codigo }}</p>
                                        <p class="text-base font-semibold text-slate-800">{{ c.curso }}</p>
                                        <p class="text-xs font-medium text-slate-500 mt-0.5">{{ c.creditos }} créditos</p>
                                    </div>
                                    <button v-if="cursoAbierto !== c.id" @click="cursoAbierto = c.id; nombreExterno = ''; estaEnFoco = false" class="rounded text-[#2E75B6] text-sm font-medium hover:underline flex items-center gap-1">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Añadir opción
                                    </button>
                                </div>

                                <!-- Opciones ya registradas -->
                                <div v-if="getEquivalenciasDeCurso(c.id).length > 0" class="mt-2 space-y-2">
                                    <div v-for="eq in getEquivalenciasDeCurso(c.id)" :key="eq.curso_externo_id" 
                                         class="flex items-center justify-between rounded bg-emerald-50 border border-emerald-100 px-3 py-2">
                                        <div>
                                            <p class="text-sm font-medium text-emerald-900">{{ eq.curso_externo.nombre }}</p>
                                            <p class="text-xs text-emerald-700" v-if="eq.curso_externo.creditos">{{ eq.curso_externo.creditos }} cr.</p>
                                        </div>
                                        <button @click="quitarOpcion(eq)" class="text-emerald-600 hover:text-red-600 ml-3" title="Quitar opción">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="mt-2 text-sm text-slate-400 italic">
                                    Ninguna opción registrada aún.
                                </div>

                                <!-- Formulario para añadir nueva opción -->
                                <div v-if="cursoAbierto === c.id" class="mt-4 border-t border-slate-100 pt-3 relative">
                                    <div class="flex gap-2 relative">
                                        <input type="text" v-model="nombreExterno" placeholder="Nombre del curso de origen..."
                                            @focus="estaEnFoco = true"
                                            @blur="setTimeout(() => estaEnFoco = false, 150)"
                                            class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]"
                                            @keydown.enter="agregarOpcion(c.id)" />
                                        <button @click="agregarOpcion(c.id)" class="rounded-md bg-[#2E75B6] px-3 py-2 text-sm font-semibold text-white hover:bg-[#1F3864]">Guardar</button>
                                        <button @click="cursoAbierto = null" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancelar</button>
                                        
                                        <!-- Autocompletado dropdown -->
                                        <div v-if="estaEnFoco && resultadosAutocompletar.length > 0" 
                                             class="absolute left-0 right-0 top-full mt-1 max-h-48 overflow-y-auto rounded-md bg-white shadow-lg border border-slate-200 z-10">
                                            <button v-for="rc in resultadosAutocompletar" :key="rc.id" @mousedown.prevent="seleccionarAutocompletado(rc)"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 border-b border-slate-100 last:border-0">
                                                {{ rc.nombre }} <span v-if="rc.creditos" class="text-xs text-slate-400 ml-1">({{rc.creditos}} cr.)</span>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-400">Si el curso ya existe en el sistema para esta carrera externa, se reutilizará automáticamente.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button @click="router.get('/equivalencias-catalogo')" class="rounded-md bg-[#1F3864] px-6 py-3 text-sm font-semibold text-white hover:bg-[#2E75B6]">
                    Volver al catálogo
                </button>
            </div>
        </div>
    </div>
</template>
