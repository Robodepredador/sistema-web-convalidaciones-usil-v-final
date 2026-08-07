<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    poolUsil: { type: Array, required: true },
    filas: { type: Array, required: true },        // reactive; se muta por referencia (igual que el resto del workspace)
    noConvalidar: { type: String, default: '— No convalidar —' },
    procesando: { type: Boolean, default: false },
    ia: { type: Object, default: () => ({ disponible: false, proveedor: 'gemini' }) },
    soloLectura: { type: Boolean, default: false },  // true en el pipeline con IA: los datos de origen vienen extraídos, no se editan
    sinIa: { type: Boolean, default: false },        // oculta el botón de sugerencia con IA (modo manual)
    // Evidencia del histórico para el curso de origen seleccionado. Es informativa:
    // el componente nunca empareja por su cuenta a partir de ella.
    antecedentes: { type: Array, default: () => [] },
    cargandoAntecedentes: { type: Boolean, default: false },
    // Cuántos cursos USIL distintos se han usado antes para este curso de origen en esta
    // carrera. ≥2 = no hay criterio establecido. null = no se preguntó por una carrera.
    criterios: { type: Number, default: null },
});

const emit = defineEmits(['sugerir-ia', 'sugerir-similitud', 'agregar', 'quitar', 'seleccion-origen']);

const TIPO_LABEL = { convalidable: 'Convalidable', no_convalidable: 'No convalidable', desaprobado: 'Desaprobado' };

// Dos colores con significado (no una paleta arbitraria por par): azul = selección en curso,
// verde = equivalencia ya confirmada. Todo el estado visual se deriva de estos dos.
const COLOR_PENDIENTE = '#2E75B6';   // azul USIL
const COLOR_CONFIRMADO = '#059669';  // emerald-600

// Solo los cursos "convalidables" participan del emparejamiento; desaprobados y no
// convalidables se muestran igual (para poder revisarlos/reclasificarlos) pero sin poder emparejar.
const candidatos = computed(() => props.filas.filter((f) => f.clasificacion === 'convalidable'));

// ---- Agrupación del lado USIL (destino) por ciclo ----
const buscarUsil = ref('');
const gruposUsil = computed(() => {
    const q = buscarUsil.value.trim().toLowerCase();
    const porCiclo = {};
    for (const c of props.poolUsil) {
        if (q && !c.curso.toLowerCase().includes(q) && !(c.codigo || '').toLowerCase().includes(q)) continue;
        (porCiclo[c.ciclo ?? 0] ??= []).push(c);
    }
    return Object.keys(porCiclo).map(Number).sort((a, b) => a - b)
        .map((n) => ({ numero: n, cursos: porCiclo[n] }));
});

// ---- Búsqueda del lado origen (incluye todas las clasificaciones) ----
const buscarOrigen = ref('');
const filasVisibles = computed(() => {
    const q = buscarOrigen.value.trim().toLowerCase();
    return q ? props.filas.filter((f) => f.curso_origen_nombre.toLowerCase().includes(q)) : props.filas;
});

// Los cursos externos se agrupan y ordenan por ciclo igual que la Malla USIL (encabezados
// "Ciclo N" pegajosos). Sin código de curso (los externos no lo tienen); los que no traen
// ciclo se agrupan al final en "Sin ciclo".
const gruposOrigen = computed(() => {
    const porCiclo = {};
    for (const f of filasVisibles.value) {
        const c = f.ciclo_origen;
        const clave = (c === '' || c == null) ? '__sin__' : String(c);
        (porCiclo[clave] ??= []).push(f);
    }
    return Object.keys(porCiclo).sort((a, b) => {
        if (a === '__sin__') return 1;
        if (b === '__sin__') return -1;
        const na = parseFloat(a), nb = parseFloat(b);
        const aNum = !Number.isNaN(na), bNum = !Number.isNaN(nb);
        if (aNum && bNum) return na - nb;
        if (aNum) return -1;
        if (bNum) return 1;
        return a.localeCompare(b);
    }).map((k) => ({
        clave: k,
        label: k === '__sin__' ? 'Sin ciclo' : (/^\d+$/.test(k) ? `Ciclo ${k}` : k),
        cursos: porCiclo[k],
    }));
});

// ---- Estado de emparejamiento ----
const matchDeUsil = (usilId) => candidatos.value.find((f) => Number(f.curso_usil_id) === usilId) ?? null;
const usilDeFila = (fila) => props.poolUsil.find((c) => Number(c.id) === Number(fila.curso_usil_id)) ?? null;
const estaEmparejada = (fila) => fila.clasificacion === 'convalidable' && !!fila.curso_usil_id;

// Selección explícita en cada lado, a la espera de confirmar el par con el botón "Confirmar equivalencia".
const seleccionUsil = ref(null);
const seleccionOrigen = ref(null);

const emparejar = (fila, usil) => {
    // Regla 1 a 1: si el destino ya estaba tomado por otra fila, se libera.
    const otro = matchDeUsil(usil.id);
    if (otro && otro !== fila) { otro.curso_usil_id = ''; otro.confianza = null; }
    fila.curso_usil_id = usil.id;
    fila.confianza = null;
    fila.origen = 'manual';
};
const desemparejar = (fila) => { fila.curso_usil_id = ''; fila.confianza = null; };

// Un curso ya emparejado queda bloqueado para seleccionar: no se puede reasignar por
// accidente con un clic; para deshacerlo se usa el botón ✕ de esa fila o de la bandeja.
const clicUsil = (usil) => {
    if (matchDeUsil(usil.id)) return;
    seleccionUsil.value = seleccionUsil.value === usil ? null : usil;
};
const clicOrigen = (fila) => {
    if (fila.clasificacion !== 'convalidable' || fila.curso_usil_id) return;
    seleccionOrigen.value = seleccionOrigen.value === fila ? null : fila;
};

// Un solo sitio para avisar del cambio de selección: la selección se limpia desde
// cuatro caminos distintos (clic, ✕ de la píldora, Cancelar, confirmar el par) y
// con emits sueltos siempre se olvidaba alguno.
watch(seleccionOrigen, (f) => emit('seleccion-origen', f?.curso_origen_nombre?.trim() || null));

// El antecedente solo MUEVE la selección al curso USIL: confirmar sigue siendo un
// acto explícito del evaluador. Los antecedentes de otra carrera destino no están
// en este plan de estudios, así que quedan como referencia sin acción.
const usilDelAntecedente = (a) => props.poolUsil.find((c) => Number(c.id) === Number(a.curso_usil_id)) ?? null;
const antecedenteAplicable = (a) => {
    const usil = usilDelAntecedente(a);
    return !!usil && !matchDeUsil(usil.id);
};
const elegirAntecedente = (a) => {
    if (!antecedenteAplicable(a)) return;
    seleccionUsil.value = usilDelAntecedente(a);
};

const puedeConfirmarMatch = computed(() => !!seleccionUsil.value && !!seleccionOrigen.value);
const confirmarMatch = () => {
    if (!puedeConfirmarMatch.value) return;
    emparejar(seleccionOrigen.value, seleccionUsil.value);
    seleccionUsil.value = null;
    seleccionOrigen.value = null;
};
const cancelarSeleccion = () => { seleccionUsil.value = null; seleccionOrigen.value = null; };

// Se muestran los más comparables; el resto vive en la pantalla del histórico.
const antecedentesVisibles = computed(() => props.antecedentes.slice(0, 4));

// ---- Alta de curso externo en línea: la tarjeta editable aparece dentro de la propia bandeja ----
const agregando = ref(false);
const nuevoNombre = ref('');
const nuevoCreditos = ref('');
const nuevoInput = ref(null);
const iniciarNuevo = async () => { agregando.value = true; nuevoNombre.value = ''; nuevoCreditos.value = ''; await nextTick(); nuevoInput.value?.focus(); };
const cancelarNuevo = () => { agregando.value = false; nuevoNombre.value = ''; nuevoCreditos.value = ''; };
// El padre crea la fila (posee _uid y filaBase); aquí solo se emite nombre + créditos. Se mantiene
// el input abierto y enfocado para agregar varios cursos seguidos sin volver a pulsar el botón.
const confirmarNuevo = async () => {
    const n = nuevoNombre.value.trim();
    if (!n) return;
    emit('agregar', { nombre: n, creditos: nuevoCreditos.value });
    nuevoNombre.value = '';
    nuevoCreditos.value = '';
    await nextTick();
    nuevoInput.value?.focus();
};

// Guía del siguiente paso, mostrada en la barra de acción mientras se arma el par.
const pasoTexto = computed(() => {
    if (puedeConfirmarMatch.value) return 'Revisa el par y confirma la equivalencia.';
    if (seleccionOrigen.value) return 'Paso 2 · elige el curso USIL con el que convalida.';
    if (seleccionUsil.value) return 'Paso 2 · elige el curso externo equivalente.';
    return '';
});

// ---- Bandeja de equivalencias ya confirmadas ----
const paresConfirmados = computed(() => candidatos.value.filter((f) => f.curso_usil_id));

// ---- Resumen ----
const resumen = computed(() => {
    const emparejados = candidatos.value.filter((f) => f.curso_usil_id).length;
    const creditos = candidatos.value
        .filter((f) => f.curso_usil_id)
        .reduce((s, f) => s + (Number(usilDeFila(f)?.creditos) || 0), 0);
    return { total: candidatos.value.length, emparejados, creditos };
});
const progresoPct = computed(() => (resumen.value.total ? Math.round((resumen.value.emparejados / resumen.value.total) * 100) : 0));

// ---- Líneas de conexión: verdes (confirmadas) + una azul discontinua (par en curso) ----
const gridEl = ref(null);
const destinoListEl = ref(null);
const origenListEl = ref(null);
const rowRefs = new Map();
const setRowRef = (key, el) => { if (el) rowRefs.set(key, el); else rowRefs.delete(key); };
// _uid es la identidad estable asignada por el workspace; el nombre queda como último
// recurso (dos filas con el mismo nombre colisionarían en claves y líneas).
const claveOrigen = (fila) => fila._uid ?? fila.curso_externo_id ?? fila.curso_origen_nombre;

// Quitar una fila que estaba seleccionada no debe dejar la barra de selección colgada.
const quitarFila = (fila) => {
    if (seleccionOrigen.value === fila) seleccionOrigen.value = null;
    emit('quitar', fila);
};

const lines = ref([]);
const pendingLine = ref(null);

// Traza una curva entre la fila USIL y la fila origen indicadas, o null si alguna
// está fuera del área visible (scroll) o aún no tiene nodo en el DOM.
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
    paresConfirmados.value.forEach((fila) => {
        const usil = usilDeFila(fila);
        if (!usil) return;
        const l = trazar('usil:' + usil.id, 'origen:' + claveOrigen(fila), rects);
        if (l) nuevas.push({ key: usil.id + ':' + claveOrigen(fila), ...l });
    });
    lines.value = nuevas;

    // Par en curso: línea discontinua azul entre ambas selecciones.
    pendingLine.value = (seleccionUsil.value && seleccionOrigen.value)
        ? trazar('usil:' + seleccionUsil.value.id, 'origen:' + claveOrigen(seleccionOrigen.value), rects)
        : null;
};

let onResize;
onMounted(() => {
    nextTick(recomputeLines);
    onResize = () => recomputeLines();
    window.addEventListener('resize', onResize);
});
onBeforeUnmount(() => window.removeEventListener('resize', onResize));
watch(() => [paresConfirmados.value.length, buscarUsil.value, buscarOrigen.value, filasVisibles.value.length,
    gruposUsil.value.length, gruposOrigen.value.length, seleccionUsil.value, seleccionOrigen.value],
    () => nextTick(recomputeLines));
</script>

<template>
    <div>
        <!-- Barra de sugerencias -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <button v-if="!sinIa" type="button" @click="emit('sugerir-ia')" :disabled="procesando || !ia?.disponible" :title="ia?.disponible ? '' : 'Configura la API key en Configuración'"
                    class="inline-flex items-center gap-1.5 rounded-md bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50">
                ✨ Sugerir con IA
            </button>
            <button type="button" @click="emit('sugerir-similitud')" :disabled="procesando"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
                ↻ Re-sugerir por similitud
            </button>
        </div>

        <!-- Guía inicial (sin selección): cómo emparejar a mano -->
        <p v-if="!soloLectura && !seleccionUsil && !seleccionOrigen" class="mb-3 text-xs text-slate-500">
            Para emparejar a mano: elige un curso de un lado y su equivalente del otro. Verás la conexión formarse y podrás confirmarla.
        </p>

        <!-- Barra de acción: sticky, aparece al seleccionar en cualquiera de los dos lados -->
        <div v-if="!soloLectura && (seleccionUsil || seleccionOrigen)"
             class="sticky top-2 z-20 mb-3 rounded-xl border bg-white px-4 py-3 shadow-md transition"
             :class="puedeConfirmarMatch ? 'border-emerald-300 ring-1 ring-emerald-200' : 'border-[#2E75B6]/40 ring-1 ring-[#2E75B6]/10'">
            <div class="flex flex-wrap items-center gap-3">
                <!-- Par en construcción -->
                <div class="flex flex-1 flex-wrap items-center gap-2 text-sm">
                    <span v-if="seleccionOrigen" class="inline-flex max-w-[18rem] items-center gap-1.5 rounded-lg bg-blue-50 py-1 pl-2.5 pr-1.5 font-medium text-[#1F3864] ring-1 ring-[#2E75B6]/30">
                        <span class="truncate">{{ seleccionOrigen.curso_origen_nombre || 'Sin nombre' }}</span>
                        <button type="button" @click="seleccionOrigen = null" title="Quitar selección" class="shrink-0 rounded-full p-0.5 text-[#2E75B6] hover:bg-white hover:text-red-600">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </span>
                    <span v-else class="rounded-lg border border-dashed border-slate-300 px-2.5 py-1 text-slate-400">Curso externo</span>
                    <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    <span v-if="seleccionUsil" class="inline-flex max-w-[18rem] items-center gap-1.5 rounded-lg bg-blue-50 py-1 pl-2.5 pr-1.5 font-medium text-[#1F3864] ring-1 ring-[#2E75B6]/30">
                        <span class="truncate">{{ seleccionUsil.curso }}</span>
                        <button type="button" @click="seleccionUsil = null" title="Quitar selección" class="shrink-0 rounded-full p-0.5 text-[#2E75B6] hover:bg-white hover:text-red-600">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </span>
                    <span v-else class="rounded-lg border border-dashed border-slate-300 px-2.5 py-1 text-slate-400">Curso USIL</span>
                </div>
                <!-- Acciones -->
                <div class="flex items-center gap-2">
                    <button type="button" @click="cancelarSeleccion" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100">Cancelar</button>
                    <button type="button" @click="confirmarMatch" :disabled="!puedeConfirmarMatch"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none">
                        Confirmar equivalencia
                    </button>
                </div>
            </div>
            <p v-if="pasoTexto" class="mt-2 text-xs font-medium" :class="puedeConfirmarMatch ? 'text-emerald-600' : 'text-[#2E75B6]'">{{ pasoTexto }}</p>

            <!-- Antecedentes: cómo se resolvió este mismo curso en expedientes anteriores.
                 Es material de consulta. Pulsar uno solo mueve la selección al curso USIL;
                 la equivalencia la sigue confirmando el evaluador. -->
            <div v-if="seleccionOrigen" class="mt-3 border-t border-slate-100 pt-2.5">
                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                    Antecedentes en el histórico
                </p>
                <!-- Criterio dividido. Es contexto, no un error: la asignación es 1-a-1 sobre
                     el expediente entero, así que el mismo curso puede haber ido a destinos
                     distintos con toda razón. Por eso el tono es de nota y no de alarma. -->
                <p v-if="!cargandoAntecedentes && criterios >= 2"
                   class="mb-1.5 rounded-md bg-blue-50 px-2 py-1.5 text-[11px] leading-snug text-[#1F3864] ring-1 ring-inset ring-blue-100">
                    Este curso se ha resuelto de <strong>{{ criterios }} formas distintas</strong> en esta carrera.
                    Revisa cuál encaja con este expediente.
                </p>
                <p v-if="cargandoAntecedentes" class="text-xs text-slate-400">Buscando…</p>
                <p v-else-if="!antecedentes.length" class="text-xs text-slate-400">
                    Sin registros previos de este curso.
                </p>
                <ul v-else class="space-y-1.5">
                    <li v-for="(a, i) in antecedentesVisibles" :key="i">
                        <button type="button" @click="elegirAntecedente(a)" :disabled="!antecedenteAplicable(a)"
                                :title="antecedenteAplicable(a) ? 'Selecciona este curso en la malla — la equivalencia la confirmas tú' : 'De otra carrera destino: solo referencia'"
                                class="flex w-full items-center gap-2 rounded-lg border px-2.5 py-1.5 text-left text-xs transition"
                                :class="antecedenteAplicable(a) ? 'border-slate-200 hover:border-[#2E75B6] hover:bg-blue-50' : 'cursor-default border-dashed border-slate-200 opacity-70'">
                            <span class="min-w-0 flex-1 truncate font-medium text-slate-700">{{ a.curso_usil }}</span>
                            <span v-if="a.confirmadas" class="shrink-0 rounded-full bg-emerald-50 px-1.5 py-0.5 font-semibold text-emerald-700">
                                {{ a.confirmadas }} con memo
                            </span>
                            <span class="shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-600">{{ a.veces }}×</span>
                        </button>
                        <p class="mt-0.5 truncate pl-2.5 text-[11px] text-slate-400">
                            {{ a.origen_nombre }} · {{ a.institucion }} → {{ a.carrera_usil }}
                            <span v-if="a.mismo_destino && a.mismo_origen" class="font-medium text-[#2E75B6]">· mismo caso</span>
                        </p>
                    </li>
                </ul>
                <p v-if="antecedentes.length > antecedentesVisibles.length" class="mt-1.5 text-[11px] text-slate-400">
                    y {{ antecedentes.length - antecedentesVisibles.length }} más en el
                    <a href="/simulaciones/historico" target="_blank" rel="noopener" class="font-medium text-[#2E75B6] hover:underline">histórico completo</a>.
                </p>
            </div>
        </div>

        <!-- Panel doble: un solo contenedor (no dos tarjetas separadas), dividido por una línea interna -->
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
                <!-- ============ DESTINO: Malla USIL ============ -->
                <div class="flex flex-col border-slate-200 md:border-r">
                    <div class="flex items-center gap-2 bg-[#1F3864] px-4 py-2.5 text-white">
                        <span class="font-heading text-sm font-bold">Malla USIL</span>
                        <span class="rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-medium">{{ poolUsil.length }} cursos</span>
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
                                    :style="matchDeUsil(c.id) ? { borderLeftColor: COLOR_CONFIRMADO, backgroundColor: COLOR_CONFIRMADO + '0d' } : {}"
                                    :class="matchDeUsil(c.id) ? 'cursor-default border-l-transparent' : (seleccionUsil === c ? 'relative z-[2] border-l-[#2E75B6] bg-blue-50 ring-2 ring-inset ring-[#2E75B6]' : 'border-l-transparent hover:bg-slate-50')"
                                    class="flex min-h-[3.75rem] w-full items-center justify-between gap-2 border-b border-l-[3px] border-slate-100 px-3.5 py-2.5 text-left transition">
                                <div class="min-w-0">
                                    <p class="truncate font-mono text-[11px] text-slate-400">{{ c.codigo }}</p>
                                    <p class="text-sm font-medium text-slate-800">{{ c.curso }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span v-if="matchDeUsil(c.id)" class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold" style="color:#047857;background:#05966915" :title="'Convalida: ' + matchDeUsil(c.id).curso_origen_nombre">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    </span>
                                    <span class="text-xs font-medium text-slate-400">{{ c.creditos }} cr.</span>
                                </div>
                            </button>
                        </div>
                        <p v-if="!gruposUsil.length" class="py-6 text-center text-sm text-slate-400">Sin resultados.</p>
                    </div>
                </div>

                <!-- ============ ORIGEN: Cursos externos ============ -->
                <div class="flex flex-col">
                    <div class="flex items-center gap-2 bg-[#1F3864] px-4 py-2.5 text-white">
                        <span class="font-heading text-sm font-bold">Cursos externos</span>
                        <span class="rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-medium">{{ candidatos.length }} disponibles</span>
                        <span class="ml-auto rounded bg-white/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">Origen</span>
                    </div>
                    <div class="border-b border-slate-100 px-3.5 py-2.5">
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                            <input v-model="buscarOrigen" type="text" placeholder="Buscar por nombre…" class="w-full rounded-md border-slate-300 py-1.5 pl-8 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                        </div>
                    </div>
                    <div ref="origenListEl" @scroll="recomputeLines" class="max-h-[460px] overflow-y-auto">
                        <div v-for="grupo in gruposOrigen" :key="grupo.clave">
                            <p class="sticky top-0 z-[1] bg-slate-50 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ grupo.label }}</p>
                            <!-- Estructura simétrica a la Malla USIL: toda la fila ES el botón, así el
                                 hover y la selección sombrean la tarjeta completa y un clic la selecciona.
                                 La ✕ va como hermana (no se pueden anidar botones). -->
                            <div v-for="fila in grupo.cursos" :key="claveOrigen(fila)" class="relative">
                                <button :ref="(el) => setRowRef('origen:' + claveOrigen(fila), el)" type="button" @click="clicOrigen(fila)"
                                        :style="estaEmparejada(fila) ? { borderLeftColor: COLOR_CONFIRMADO, backgroundColor: COLOR_CONFIRMADO + '0d' } : {}"
                                        :class="estaEmparejada(fila) ? 'cursor-default border-l-transparent'
                                            : (fila.clasificacion !== 'convalidable' ? 'cursor-default border-l-transparent opacity-60'
                                            : (seleccionOrigen === fila ? 'relative z-[2] border-l-[#2E75B6] bg-blue-50 ring-2 ring-inset ring-[#2E75B6]' : 'border-l-transparent hover:bg-slate-50'))"
                                        class="flex min-h-[3.75rem] w-full items-center justify-between gap-2 border-b border-l-[3px] border-slate-100 px-3.5 py-2.5 pr-9 text-left transition">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-800">{{ fila.curso_origen_nombre || 'Sin nombre' }}</p>
                                        <p v-if="estaEmparejada(fila)" class="mt-0.5 flex items-center gap-1 truncate text-xs font-medium" style="color:#047857">
                                            <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                            {{ usilDeFila(fila)?.curso }}
                                            <span v-if="fila.confianza" class="text-slate-400">· {{ Number(fila.confianza).toFixed(0) }}%</span>
                                        </p>
                                        <p v-else-if="fila.clasificacion !== 'convalidable'" class="mt-0.5 text-xs text-slate-400">
                                            {{ TIPO_LABEL[fila.clasificacion] }} — no participa del emparejamiento
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-xs font-medium text-slate-400">{{ fila.creditos_origen || '—' }} cr.</span>
                                </button>
                                <button v-if="!soloLectura" type="button" @click="quitarFila(fila)" title="Quitar"
                                        class="absolute right-2.5 top-1/2 z-10 -translate-y-1/2 text-slate-300 hover:text-red-600">✕</button>
                            </div>
                        </div>
                        <p v-if="!filasVisibles.length" class="py-6 text-center text-sm text-slate-400">
                            {{ filas.length ? 'Sin resultados.' : 'Sin cursos por emparejar.' }}
                        </p>
                        <template v-if="!soloLectura">
                            <!-- Tarjeta editable en línea: se agrega el curso sin salir de la bandeja -->
                            <div v-if="agregando" class="border-b border-l-[3px] border-l-[#2E75B6] border-slate-100 bg-blue-50/40 px-3.5 py-2.5">
                                <div class="flex gap-2">
                                    <input ref="nuevoInput" v-model="nuevoNombre" type="text" placeholder="Nombre del curso externo…"
                                           @keydown.enter.prevent="confirmarNuevo" @keydown.esc="cancelarNuevo"
                                           class="min-w-0 flex-1 rounded-md border-slate-300 py-1.5 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                                    <input v-model="nuevoCreditos" type="number" step="0.5" min="0" placeholder="Créd."
                                           @keydown.enter.prevent="confirmarNuevo" @keydown.esc="cancelarNuevo"
                                           class="w-20 shrink-0 rounded-md border-slate-300 py-1.5 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <button type="button" @click="confirmarNuevo" :disabled="!nuevoNombre.trim()"
                                            class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:bg-slate-300">
                                        Agregar
                                    </button>
                                    <button type="button" @click="cancelarNuevo" class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-100">Cerrar</button>
                                    <span class="text-[11px] text-slate-400">Enter para agregar · puedes añadir varios seguidos</span>
                                </div>
                            </div>
                            <button v-else type="button" @click="iniciarNuevo"
                                    class="m-2 w-[calc(100%-1rem)] rounded-lg border border-dashed border-slate-300 py-2 text-sm font-medium text-[#2E75B6] hover:bg-slate-50">
                                + Agregar curso
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bandeja de equivalencias confirmadas -->
        <div v-if="!soloLectura || paresConfirmados.length" class="mt-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <span class="h-2 w-2 rounded-full" style="background:#059669"></span>
                Equivalencias confirmadas ({{ paresConfirmados.length }})
            </p>
            <p v-if="!paresConfirmados.length" class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-400">
                Aún no hay equivalencias. Elige un curso de cada lado y pulsa «Confirmar equivalencia»; aparecerán aquí.
            </p>
            <div v-else class="space-y-1.5">
                <div v-for="fila in paresConfirmados" :key="claveOrigen(fila)"
                     class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm">
                    <!-- Origen -->
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-slate-700">{{ fila.curso_origen_nombre }}</p>
                        <p class="text-xs text-slate-400">{{ fila.creditos_origen || '—' }} cr.</p>
                    </div>
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#059669"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    <!-- Destino USIL -->
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-slate-800">{{ usilDeFila(fila)?.curso }}</p>
                        <p class="text-xs text-slate-400">
                            <span v-if="usilDeFila(fila)?.codigo" class="font-mono">{{ usilDeFila(fila)?.codigo }} · </span>{{ usilDeFila(fila)?.creditos || '—' }} cr.
                        </p>
                    </div>
                    <button type="button" @click="desemparejar(fila)" class="shrink-0 text-slate-300 hover:text-red-600" title="Quitar equivalencia">✕</button>
                </div>
            </div>
        </div>

        <!-- Resumen -->
        <div class="mt-3 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm">
            <span class="whitespace-nowrap font-medium text-slate-700">{{ resumen.emparejados }} de {{ resumen.total }} emparejados</span>
            <div class="h-[7px] max-w-[220px] flex-1 overflow-hidden rounded-full bg-slate-200">
                <div class="h-full rounded-full bg-[#2E75B6] transition-all" :style="{ width: progresoPct + '%' }"></div>
            </div>
            <span v-if="resumen.total - resumen.emparejados > 0" class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                {{ resumen.total - resumen.emparejados }} sin asignar
            </span>
            <span class="ml-auto font-semibold text-[#1F3864]">{{ resumen.creditos.toFixed(1) }} créditos convalidables</span>
        </div>
    </div>
</template>
