<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    instituciones: Array,
    facultades: Array,
    preseleccion: { type: Object, default: () => ({}) },
});

// ---------------------------------------------------------------- pasos 1 y 2
const institucionId = ref('');
const carreraExternaId = ref('');
const facultadId = ref('');
const carreraUsilId = ref('');

const carrerasExternas = computed(() =>
    props.instituciones.find((i) => String(i.id) === String(institucionId.value))?.carreras ?? []);
const carrerasUsil = computed(() =>
    props.facultades.find((f) => String(f.id) === String(facultadId.value))?.carreras ?? []);

// La malla vigente de la carrera de origen. Si existe, el paso 3 no pide archivo:
// volver a subirla desactivaría esta versión y dejaría colgados los mapeos previos.
const mallaOrigen = computed(() =>
    carrerasExternas.value.find((c) => String(c.id) === String(carreraExternaId.value))?.mallas?.[0] ?? null);

const onInstitucion = () => { carreraExternaId.value = ''; reiniciarMapeo(); };
const onFacultad = () => { carreraUsilId.value = ''; reiniciarMapeo(); };

// ---------------------------------------------------------------- paso 4: mapeo
const cursosExternos = ref([]);
const cursosUsil = ref([]);
const pares = ref([]);
const cargando = ref(false);
const error = ref('');
const mapeando = ref(false);

const reiniciarMapeo = () => { mapeando.value = false; pares.value = []; error.value = ''; };

const empezarMapeo = async () => {
    if (!mallaOrigen.value || !carreraUsilId.value) return;
    cargando.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.get('/mapeo-mallas/cursos', {
            params: { malla_externa_id: mallaOrigen.value.id, carrera_usil_id: carreraUsilId.value },
        });
        cursosExternos.value = data.cursosExternos;
        cursosUsil.value = data.cursosUsil;
        pares.value = data.pares;
        mapeando.value = true;
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudieron cargar las dos mallas.';
    } finally {
        cargando.value = false;
    }
};

// Índices para pintar lo ya asignado sin recorrer la lista en cada celda.
const parPorUsil = computed(() => Object.fromEntries(pares.value.map((p) => [p.curso_usil_id, p])));
const externosUsados = computed(() => new Set(pares.value.map((p) => p.curso_externo_id)));
const externoPorId = computed(() => Object.fromEntries(cursosExternos.value.map((c) => [c.id, c])));

// Se recorre la malla USIL: es la referencia fija y es como piensa después el evaluador.
const usilSeleccionado = ref(null);
const buscarExterno = ref('');

const externosDisponibles = computed(() => {
    const q = buscarExterno.value.trim().toLowerCase();
    return cursosExternos.value.filter((c) =>
        !externosUsados.value.has(c.id) && (!q || c.nombre.toLowerCase().includes(q)));
});

const asignar = async (cursoExterno) => {
    if (!usilSeleccionado.value) return;
    error.value = '';
    try {
        const { data } = await window.axios.post('/mapeo-mallas', {
            carrera_usil_id: carreraUsilId.value,
            curso_externo_id: cursoExterno.id,
            curso_usil_id: usilSeleccionado.value.id,
        });
        // Guardado par a par: mapear 40 cursos y perderlos por un cierre de navegador
        // no es una opción, y así el duplicado se detecta al instante.
        pares.value.push({ id: data.id, curso_externo_id: cursoExterno.id, curso_usil_id: usilSeleccionado.value.id });
        usilSeleccionado.value = null;
        buscarExterno.value = '';
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudo guardar la equivalencia.';
    }
};

const quitar = async (par) => {
    error.value = '';
    try {
        await window.axios.delete(`/mapeo-mallas/${par.id}`);
        pares.value = pares.value.filter((p) => p.id !== par.id);
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudo quitar la equivalencia.';
    }
};

const declarados = computed(() => pares.value.length);
</script>

<template>
    <div>
        <div class="mb-6">
            <Link href="/mapeo-mallas" class="text-xs font-medium uppercase tracking-wide text-slate-400 hover:text-[#2E75B6]">← Equivalencias por malla</Link>
            <h1 class="mt-2 text-2xl font-semibold text-[#1F3864]">Nuevo mapeo de equivalencias</h1>
        </div>

        <p v-if="error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</p>

        <!-- Pasos 1 a 3 -->
        <div v-if="!mapeando" class="space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">1 · Institución de origen</h2>
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

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">2 · Carrera USIL destino</h2>
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
                <p class="mt-2 text-xs text-slate-400">Se usa el plan de estudios vigente de la carrera.</p>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">3 · Malla de origen</h2>

                <p v-if="!carreraExternaId" class="text-sm text-slate-400">Elige primero la carrera de origen.</p>

                <div v-else-if="mallaOrigen" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <p class="text-sm font-medium text-emerald-900">
                        Malla {{ mallaOrigen.anio }}<span v-if="mallaOrigen.version"> · versión {{ mallaOrigen.version }}</span> ya registrada
                    </p>
                    <p class="mt-0.5 text-xs text-emerald-700">
                        Se usa esta. No hace falta volver a subirla — registrar otra desactivaría esta versión
                        y dejaría los mapeos anteriores atados al plan viejo.
                    </p>
                </div>

                <div v-else class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-medium text-amber-900">Esta carrera aún no tiene malla registrada</p>
                    <p class="mt-0.5 text-xs text-amber-800">
                        Sin la malla de origen no hay cursos que emparejar.
                        <Link href="/equivalencias/crear" class="font-medium underline">Regístrala aquí</Link>
                        (puedes subirla en Excel con la plantilla, sin usar IA) y vuelve.
                    </p>
                </div>
            </section>

            <div class="flex justify-end">
                <button @click="empezarMapeo" :disabled="!mallaOrigen || !carreraUsilId || cargando"
                        class="rounded-md bg-[#2E75B6] px-6 py-3 text-sm font-semibold text-white hover:bg-[#1F3864] disabled:opacity-50">
                    {{ cargando ? 'Cargando…' : 'Empezar a mapear →' }}
                </button>
            </div>
        </div>

        <!-- Paso 4: mapeo -->
        <div v-else>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">
                    <span class="font-medium text-slate-700">{{ declarados }}</span> de {{ cursosUsil.length }} cursos USIL con equivalencia declarada.
                    <span class="text-slate-400">Que un curso no tenga equivalente es normal: no hace falta completarlos todos.</span>
                </p>
                <button @click="mapeando = false" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    Cambiar mallas
                </button>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Cursos USIL: la referencia fija -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-[#1F3864]">Plan de estudios USIL</h3>
                        <p class="text-xs text-slate-400">Elige un curso y luego su equivalente de origen.</p>
                    </div>
                    <ul class="max-h-[520px] divide-y divide-slate-100 overflow-y-auto">
                        <li v-for="c in cursosUsil" :key="c.id">
                            <button type="button" @click="usilSeleccionado = usilSeleccionado?.id === c.id ? null : c"
                                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition"
                                    :class="usilSeleccionado?.id === c.id ? 'bg-blue-50 ring-1 ring-inset ring-[#2E75B6]' : 'hover:bg-slate-50'">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-slate-800">{{ c.curso }}</span>
                                    <span class="block truncate text-xs text-slate-400">
                                        <template v-if="parPorUsil[c.id]">
                                            ≡ {{ externoPorId[parPorUsil[c.id].curso_externo_id]?.nombre }}
                                        </template>
                                        <template v-else>Sin equivalencia declarada</template>
                                    </span>
                                </span>
                                <span v-if="parPorUsil[c.id]" @click.stop="quitar(parPorUsil[c.id])"
                                      class="shrink-0 rounded px-2 py-1 text-xs font-medium text-red-500 hover:bg-red-50">Quitar</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Cursos de origen disponibles -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-[#1F3864]">Malla de origen</h3>
                        <input v-model="buscarExterno" type="search" placeholder="Buscar curso…"
                               class="mt-2 w-full rounded-md border-slate-300 py-1.5 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                    <p v-if="!usilSeleccionado" class="px-4 py-10 text-center text-sm text-slate-400">
                        Selecciona primero un curso del plan USIL.
                    </p>
                    <ul v-else class="max-h-[470px] divide-y divide-slate-100 overflow-y-auto">
                        <li v-for="c in externosDisponibles" :key="c.id">
                            <button type="button" @click="asignar(c)" class="w-full px-4 py-2.5 text-left hover:bg-emerald-50">
                                <span class="block truncate text-sm text-slate-800">{{ c.nombre }}</span>
                                <span class="block truncate text-xs text-slate-400">
                                    {{ c.codigo || 'sin código' }}<template v-if="c.creditos"> · {{ c.creditos }} créd.</template>
                                </span>
                            </button>
                        </li>
                        <li v-if="!externosDisponibles.length" class="px-4 py-10 text-center text-sm text-slate-400">
                            No queda ningún curso de origen sin asignar.
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <button @click="router.get('/mapeo-mallas')"
                        class="rounded-md bg-[#1F3864] px-6 py-3 text-sm font-semibold text-white hover:bg-[#2E75B6]">
                    Terminar
                </button>
            </div>
        </div>
    </div>
</template>
