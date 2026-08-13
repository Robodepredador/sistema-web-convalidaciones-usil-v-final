<script setup>
import { Link, router } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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

// «Continuar» en el listado abre esta misma pantalla con el par ya elegido en la query.
// El controlador lo envía como `preseleccion`, pero nadie lo aplicaba: la vista se abría
// vacía y era indistinguible de «Nuevo mapeo». Aquí se resuelve el par y se entra directo
// al paso 4, que es lo que el usuario pidió al pulsar Continuar.
const continuando = ref(false);

const aplicarPreseleccion = () => {
    const mallaId = props.preseleccion?.malla_externa_id;
    const usilId = props.preseleccion?.carrera_usil_id;
    if (!mallaId || !usilId) return;

    // La malla identifica a su carrera de origen, y esta a su institución.
    for (const inst of props.instituciones) {
        const carrera = inst.carreras?.find((c) => c.mallas?.some((m) => String(m.id) === String(mallaId)));
        if (carrera) {
            institucionId.value = inst.id;
            carreraExternaId.value = carrera.id;
            break;
        }
    }
    const facultad = props.facultades.find((f) => f.carreras?.some((c) => String(c.id) === String(usilId)));
    if (facultad) {
        facultadId.value = facultad.id;
        carreraUsilId.value = facultad.carreras.find((c) => String(c.id) === String(usilId)).id;
    }

    if (carreraExternaId.value && carreraUsilId.value) {
        continuando.value = true;
        empezarMapeo();
        return;
    }

    // Solo se cargan las mallas externas vigentes, así que un mapeo hecho sobre un plan
    // ya reemplazado no se puede reabrir tal cual. Se dice, en vez de dejar al usuario
    // ante un asistente vacío sin explicación.
    error.value = 'Ese mapeo se declaró sobre un plan de origen que ya no está vigente. '
        + 'Elige la malla actual para declarar el criterio sobre ella.';
};
aplicarPreseleccion();

// Índices para pintar lo ya asignado sin recorrer la lista en cada celda.
const parPorUsil = computed(() => Object.fromEntries(pares.value.map((p) => [p.curso_usil_id, p])));
const externoPorId = computed(() => Object.fromEntries(cursosExternos.value.map((c) => [c.id, c])));

const parPorExterno = computed(() => Object.fromEntries(pares.value.map((p) => [p.curso_externo_id, p])));
const usilPorId = computed(() => Object.fromEntries(cursosUsil.value.map((c) => [c.id, c])));

// Se puede empezar por cualquiera de los dos lados. Obligar a elegir primero el curso
// USIL forzaba a dar la vuelta a quien venía leyendo la malla de origen, y dejaba media
// pantalla inerte hasta que elegías. El par se arma a la vista y se confirma aparte.
const usilSeleccionado = ref(null);
const externoSeleccionado = ref(null);
const buscarUsil = ref('');
const buscarExterno = ref('');

// ---- Agrupación del lado USIL (destino) por ciclo ----
const gruposUsil = computed(() => {
    const q = buscarUsil.value.trim().toLowerCase();
    const porCiclo = {};
    for (const c of cursosUsil.value) {
        if (q && !c.curso.toLowerCase().includes(q) && !(c.codigo || '').toLowerCase().includes(q)) continue;
        (porCiclo[c.ciclo ?? 0] ??= []).push(c);
    }
    return Object.keys(porCiclo).map(Number).sort((a, b) => a - b)
        .map((n) => ({ numero: n, cursos: porCiclo[n] }));
});

// Los externos NO tienen ciclo en la BD, se muestran como lista plana filtrada.
const externosVisibles = computed(() => {
    const t = buscarExterno.value.trim().toLowerCase();
    return t ? cursosExternos.value.filter((c) => (c.nombre ?? '').toLowerCase().includes(t)
        || (c.codigo ?? '').toLowerCase().includes(t)) : cursosExternos.value;
});

// Un curso ya declarado queda bloqueado para seleccionar: deshacer se hace con Quitar,
// para que un clic suelto no pueda tirar trabajo sin querer.
const clicUsil = (c) => {
    if (parPorUsil.value[c.id]) return;
    usilSeleccionado.value = usilSeleccionado.value?.id === c.id ? null : c;
};
const clicExterno = (c) => {
    if (parPorExterno.value[c.id]) return;
    externoSeleccionado.value = externoSeleccionado.value?.id === c.id ? null : c;
};

const puedeConfirmar = computed(() => !!usilSeleccionado.value && !!externoSeleccionado.value);
const pasoTexto = computed(() => {
    if (puedeConfirmar.value) return 'Revisa el par y confirma la equivalencia.';
    if (usilSeleccionado.value) return 'Paso 2 · elige el curso de origen equivalente.';
    if (externoSeleccionado.value) return 'Paso 2 · elige el curso USIL al que equivale.';
    return '';
});
const cancelarSeleccion = () => { usilSeleccionado.value = null; externoSeleccionado.value = null; };

const confirmar = async () => {
    if (!puedeConfirmar.value) return;
    error.value = '';
    const usil = usilSeleccionado.value;
    const externo = externoSeleccionado.value;
    try {
        const { data } = await window.axios.post('/mapeo-mallas', {
            carrera_usil_id: carreraUsilId.value,
            curso_externo_id: externo.id,
            curso_usil_id: usil.id,
        });
        // Guardado par a par: mapear 40 cursos y perderlos por un cierre de navegador
        // no es una opción, y así el duplicado se detecta al instante.
        pares.value.push({ id: data.id, curso_externo_id: externo.id, curso_usil_id: usil.id });
        usilSeleccionado.value = null;
        externoSeleccionado.value = null;
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudo guardar la equivalencia.';
    }
};

const marcarNoConvalidable = async () => {
    const externo = externoSeleccionado.value;
    if (!externo) return;

    const motivo = prompt(`¿Motivo por el cual "${externo.nombre}" no es convalidable en esta carrera? (opcional)`);
    if (motivo === null) return; // cancelado

    error.value = '';
    try {
        await window.axios.post('/mapeo-mallas/no-convalidable', {
            carrera_usil_id: carreraUsilId.value,
            curso_externo_id: externo.id,
            motivo: motivo,
        });
        alert(`Se guardó "${externo.nombre}" en la base de conocimiento de cursos no convalidables.`);
        externoSeleccionado.value = null;
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudo marcar como no convalidable.';
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
const progresoPct = computed(() => (cursosUsil.value.length ? Math.round((declarados.value / cursosUsil.value.length) * 100) : 0));
const plural = (n, singular, plural_) => (n === 1 ? singular : plural_);

// Pares confirmados como lista de objetos ricos (para la bandeja inferior).
const paresConfirmados = computed(() => pares.value.map((p) => ({
    ...p,
    externo: externoPorId.value[p.curso_externo_id],
    usil: usilPorId.value[p.curso_usil_id],
})).filter((p) => p.externo && p.usil));

// ---- Colores de las líneas de conexión ----
const COLOR_PENDIENTE = '#2E75B6';   // azul USIL
const COLOR_CONFIRMADO = '#059669';  // emerald-600

// ---- Líneas de conexión SVG ----
const gridEl = ref(null);
const destinoListEl = ref(null);
const origenListEl = ref(null);
const rowRefs = new Map();
const setRowRef = (key, el) => { if (el) rowRefs.set(key, el); else rowRefs.delete(key); };

const lines = ref([]);
const pendingLine = ref(null);

const trazar = (usilKey, origenKey, rects) => {
    const a = rowRefs.get(usilKey);
    const b = rowRefs.get(origenKey);
    if (!a || !b) return null;
    const ar = a.getBoundingClientRect();
    const br = b.getBoundingClientRect();
    if (ar.bottom <= rects.d.top + 2 || ar.top >= rects.d.bottom - 2) return null;
    if (br.bottom <= rects.o.top + 2 || br.top >= rects.o.bottom - 2) return null;
    const x1 = ar.right - rects.g.left, y1 = ar.top - rects.g.top + ar.height / 2;
    const x2 = br.left - rects.g.left, y2 = br.top - rects.g.top + br.height / 2;
    const mx = (x1 + x2) / 2;
    return { path: `M ${x1} ${y1} C ${mx} ${y1}, ${mx} ${y2}, ${x2} ${y2}`, x1, y1, x2, y2 };
};

const recomputeLines = () => {
    if (!gridEl.value || !destinoListEl.value || !origenListEl.value) return;
    const rects = {
        g: gridEl.value.getBoundingClientRect(),
        d: destinoListEl.value.getBoundingClientRect(),
        o: origenListEl.value.getBoundingClientRect(),
    };
    const nuevas = [];
    pares.value.forEach((par) => {
        const l = trazar('usil:' + par.curso_usil_id, 'externo:' + par.curso_externo_id, rects);
        if (l) nuevas.push({ key: par.curso_usil_id + ':' + par.curso_externo_id, ...l });
    });
    lines.value = nuevas;

    // Par en curso: línea discontinua azul entre ambas selecciones.
    pendingLine.value = (usilSeleccionado.value && externoSeleccionado.value)
        ? trazar('usil:' + usilSeleccionado.value.id, 'externo:' + externoSeleccionado.value.id, rects)
        : null;
};

let onResize;
onMounted(() => {
    nextTick(recomputeLines);
    onResize = () => recomputeLines();
    window.addEventListener('resize', onResize);
});
onBeforeUnmount(() => window.removeEventListener('resize', onResize));
watch(() => [pares.value.length, buscarUsil.value, buscarExterno.value,
    gruposUsil.value.length, externosVisibles.value.length,
    usilSeleccionado.value, externoSeleccionado.value],
    () => nextTick(recomputeLines));
</script>

<template>
    <div>
        <div class="mb-6">
            <VolverA href="/mapeo-mallas" texto="Mapeo de equivalencias" />
            <!-- Al continuar no se está creando nada: el título tiene que decir lo mismo
                 que el botón que trajo aquí, o el usuario cree que se equivocó de sitio. -->
            <h1 class="text-2xl font-semibold text-[#1F3864]">
                {{ continuando ? 'Continuar mapeo de equivalencias' : 'Nuevo mapeo de equivalencias' }}
            </h1>
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
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-400">Que un curso no tenga equivalente es normal: no hace falta completarlos todos.</p>
                <button @click="mapeando = false" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Cambiar mallas
                </button>
            </div>

            <!-- Guía inicial, mientras no hay nada elegido -->
            <p v-if="!usilSeleccionado && !externoSeleccionado" class="mb-3 text-xs text-slate-500">
                Para emparejar: elige un curso de un lado y su equivalente del otro. Verás la conexión formarse y podrás confirmarla.
            </p>

            <!-- Barra de acción sticky: el par se arma a la vista y confirmar es un acto aparte -->
            <div v-else class="sticky top-2 z-20 mb-3 rounded-xl border bg-white px-4 py-3 shadow-md transition"
                 :class="puedeConfirmar ? 'border-emerald-300 ring-1 ring-emerald-200' : 'border-[#2E75B6]/40 ring-1 ring-[#2E75B6]/10'">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex flex-1 flex-wrap items-center gap-2 text-sm">
                        <span v-if="externoSeleccionado" class="inline-flex max-w-[18rem] items-center gap-1.5 rounded-lg bg-blue-50 py-1 pl-2.5 pr-1.5 font-medium text-[#1F3864] ring-1 ring-[#2E75B6]/30">
                            <span class="truncate">{{ externoSeleccionado.nombre }}</span>
                            <button type="button" @click="externoSeleccionado = null" title="Quitar selección" class="shrink-0 rounded-full p-0.5 text-[#2E75B6] hover:bg-white hover:text-red-600">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </span>
                        <span v-else class="rounded-lg border border-dashed border-slate-300 px-2.5 py-1 text-slate-400">Curso de origen</span>
                        <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        <span v-if="usilSeleccionado" class="inline-flex max-w-[18rem] items-center gap-1.5 rounded-lg bg-blue-50 py-1 pl-2.5 pr-1.5 font-medium text-[#1F3864] ring-1 ring-[#2E75B6]/30">
                            <span class="truncate">{{ usilSeleccionado.curso }}</span>
                            <button type="button" @click="usilSeleccionado = null" title="Quitar selección" class="shrink-0 rounded-full p-0.5 text-[#2E75B6] hover:bg-white hover:text-red-600">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </span>
                        <span v-else class="rounded-lg border border-dashed border-slate-300 px-2.5 py-1 text-slate-400">Curso USIL</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="cancelarSeleccion" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100">Cancelar</button>
                        <button v-if="externoSeleccionado && !usilSeleccionado" type="button" @click="marcarNoConvalidable" class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-100">
                            Marcar como no convalidable
                        </button>
                        <button type="button" @click="confirmar" :disabled="!puedeConfirmar"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none">
                            Confirmar equivalencia
                        </button>
                    </div>
                </div>
                <p v-if="pasoTexto" class="mt-2 text-xs font-medium" :class="puedeConfirmar ? 'text-emerald-600' : 'text-[#2E75B6]'">{{ pasoTexto }}</p>
            </div>

            <!-- Panel doble con líneas SVG de conexión -->
            <div ref="gridEl" class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <!-- Líneas de conexión: verdes confirmadas + azul discontinua para el par en curso -->
                <svg class="pointer-events-none absolute inset-0 z-10 hidden h-full w-full overflow-visible md:block">
                    <template v-for="line in lines" :key="line.key">
                        <path :d="line.path" fill="none" :stroke="COLOR_CONFIRMADO" stroke-width="2" />
                        <circle :cx="line.x1" :cy="line.y1" r="3.5" :fill="COLOR_CONFIRMADO" />
                        <circle :cx="line.x2" :cy="line.y2" r="3.5" :fill="COLOR_CONFIRMADO" />
                    </template>
                    <template v-if="pendingLine">
                        <path :d="pendingLine.path" fill="none" :stroke="COLOR_PENDIENTE" stroke-width="2" stroke-dasharray="5 4" />
                        <circle :cx="pendingLine.x1" :cy="pendingLine.y1" r="4" fill="white" :stroke="COLOR_PENDIENTE" stroke-width="2" />
                        <circle :cx="pendingLine.x2" :cy="pendingLine.y2" r="4" fill="white" :stroke="COLOR_PENDIENTE" stroke-width="2" />
                    </template>
                </svg>

                <div class="grid md:grid-cols-2">
                    <!-- ============ DESTINO: plan USIL ============ -->
                    <div class="flex flex-col border-slate-200 md:border-r">
                        <div class="flex items-center gap-2 bg-[#1F3864] px-4 py-2.5 text-white">
                            <span class="font-heading text-sm font-bold">Malla USIL</span>
                            <span class="rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-medium">{{ cursosUsil.length }} {{ plural(cursosUsil.length, 'curso', 'cursos') }}</span>
                            <span class="ml-auto rounded bg-white/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">Destino</span>
                        </div>
                        <div class="border-b border-slate-100 px-3.5 py-2.5">
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                                <input v-model="buscarUsil" type="text" placeholder="Buscar por nombre o código…" class="w-full rounded-md border-slate-300 py-1.5 pl-8 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                            </div>
                        </div>
                        <div ref="destinoListEl" @scroll="recomputeLines" class="max-h-[460px] overflow-y-auto">
                            <div v-for="grupo in gruposUsil" :key="grupo.numero">
                                <p class="sticky top-0 z-[1] bg-slate-50 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Ciclo {{ grupo.numero }}</p>
                                <button v-for="c in grupo.cursos" :key="c.id" :ref="(el) => setRowRef('usil:' + c.id, el)" type="button" @click="clicUsil(c)"
                                        :style="parPorUsil[c.id] ? { borderLeftColor: COLOR_CONFIRMADO, backgroundColor: COLOR_CONFIRMADO + '0d' } : {}"
                                        :class="parPorUsil[c.id] ? 'cursor-default border-l-transparent'
                                            : (usilSeleccionado?.id === c.id ? 'relative z-[2] border-l-[#2E75B6] bg-blue-50 ring-2 ring-inset ring-[#2E75B6]' : 'border-l-transparent hover:bg-slate-50')"
                                        class="flex min-h-[3.75rem] w-full items-center justify-between gap-2 border-b border-l-[3px] border-slate-100 px-3.5 py-2.5 text-left transition">
                                    <div class="min-w-0">
                                        <p class="truncate font-mono text-[11px] text-slate-400">{{ c.codigo }}</p>
                                        <p class="text-sm font-medium text-slate-800">{{ c.curso }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span v-if="parPorUsil[c.id]" class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold" style="color:#047857;background:#05966915" :title="'Convalida: ' + (externoPorId[parPorUsil[c.id].curso_externo_id]?.nombre || '')">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                        </span>
                                        <span class="text-xs font-medium text-slate-400">{{ c.creditos }} cr.</span>
                                    </div>
                                </button>
                            </div>
                            <p v-if="!gruposUsil.length" class="py-6 text-center text-sm text-slate-400">Ningún curso coincide con la búsqueda.</p>
                        </div>
                    </div>

                    <!-- ============ ORIGEN: malla externa ============ -->
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2 bg-[#1F3864] px-4 py-2.5 text-white">
                            <span class="font-heading text-sm font-bold">Malla de origen</span>
                            <span class="rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-medium">{{ cursosExternos.length }} {{ plural(cursosExternos.length, 'curso', 'cursos') }}</span>
                            <span class="ml-auto rounded bg-white/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">Origen</span>
                        </div>
                        <div class="border-b border-slate-100 px-3.5 py-2.5">
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                                <input v-model="buscarExterno" type="text" placeholder="Buscar curso…" class="w-full rounded-md border-slate-300 py-1.5 pl-8 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                            </div>
                        </div>
                        <div ref="origenListEl" @scroll="recomputeLines" class="max-h-[460px] overflow-y-auto">
                            <div v-for="c in externosVisibles" :key="c.id" class="relative">
                                <button :ref="(el) => setRowRef('externo:' + c.id, el)" type="button" @click="clicExterno(c)"
                                        :style="parPorExterno[c.id] ? { borderLeftColor: COLOR_CONFIRMADO, backgroundColor: COLOR_CONFIRMADO + '0d' } : {}"
                                        :class="parPorExterno[c.id] ? 'cursor-default border-l-transparent'
                                            : (externoSeleccionado?.id === c.id ? 'relative z-[2] border-l-[#2E75B6] bg-blue-50 ring-2 ring-inset ring-[#2E75B6]' : 'border-l-transparent hover:bg-slate-50')"
                                        class="flex min-h-[3.75rem] w-full items-center justify-between gap-2 border-b border-l-[3px] border-slate-100 px-3.5 py-2.5 text-left transition">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-800">{{ c.nombre }}</p>
                                        <p v-if="parPorExterno[c.id]" class="mt-0.5 flex items-center gap-1 truncate text-xs font-medium" style="color:#047857">
                                            <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                            {{ usilPorId[parPorExterno[c.id].curso_usil_id]?.curso }}
                                        </p>
                                        <p v-else class="mt-0.5 text-xs text-slate-400">
                                            {{ c.codigo || 'sin código' }}<template v-if="c.creditos"> · {{ c.creditos }} cr.</template>
                                        </p>
                                    </div>
                                    <span v-if="parPorExterno[c.id]" class="shrink-0 inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold" style="color:#047857;background:#05966915">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    </span>
                                </button>
                            </div>
                            <p v-if="!externosVisibles.length" class="px-6 py-10 text-center text-sm text-slate-400">Ningún curso coincide con la búsqueda.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bandeja de equivalencias confirmadas -->
            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <span class="h-2 w-2 rounded-full" style="background:#059669"></span>
                    Equivalencias confirmadas ({{ paresConfirmados.length }})
                </p>
                <p v-if="!paresConfirmados.length" class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-400">
                    Aún no hay equivalencias. Elige un curso de cada lado y pulsa «Confirmar equivalencia»; aparecerán aquí.
                </p>
                <div v-else class="space-y-1.5">
                    <div v-for="par in paresConfirmados" :key="par.id"
                         class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm">
                        <!-- Origen -->
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-slate-700">{{ par.externo?.nombre }}</p>
                            <p class="text-xs text-slate-400">{{ par.externo?.creditos || '—' }} cr.</p>
                        </div>
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#059669"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        <!-- Destino USIL -->
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-slate-800">{{ par.usil?.curso }}</p>
                            <p class="text-xs text-slate-400">
                                <span v-if="par.usil?.codigo" class="font-mono">{{ par.usil?.codigo }} · </span>{{ par.usil?.creditos || '—' }} cr.
                            </p>
                        </div>
                        <button type="button" @click="quitar(par)" class="shrink-0 text-slate-300 hover:text-red-600" title="Quitar equivalencia">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resumen -->
            <div class="mt-3 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm">
                <span class="whitespace-nowrap font-medium text-slate-700">
                    {{ declarados }} de {{ cursosUsil.length }} {{ plural(cursosUsil.length, 'curso', 'cursos') }} con equivalencia
                </span>
                <div class="h-[7px] max-w-[220px] flex-1 overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-[#2E75B6] transition-all" :style="{ width: progresoPct + '%' }"></div>
                </div>
                <span v-if="cursosUsil.length - declarados > 0" class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                    {{ cursosUsil.length - declarados }} sin asignar
                </span>
                <span class="ml-auto text-xs text-slate-400">Se guarda sola cada vez que confirmas.</span>
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
