<script setup>
import { router, Link } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

const props = defineProps({
    malla: { type: Object, default: null },
    instituciones: Array,
});

// --- Navegación del wizard ---
// Los pasos 4 (Mapeo Maestro) y 5 (Diccionario) pertenecían al catálogo de
// equivalencias, hoy desactivado. El wizard termina al crear la malla.
const paso = ref(1);

const PASOS = [
    { n: 1, label: 'Recepción' },
    { n: 2, label: 'Extracción IA' },
    { n: 3, label: 'Catálogo Extraído' },
];

// ===================== ETAPAS 1-3: CREACIÓN DE LA MALLA EXTERNA =====================
const formRecepcion = reactive({
    institucion_id: '',
    carrera_externa_id: '',
    anio: new Date().getFullYear().toString(),
    version: '1',
    pdf: null,
});

const archivoNombre = ref('');
const extrayendo = ref(false);
const errorExtraccion = ref('');
const cursosExtraidos = ref([]);
const datosExtraidos = ref({}); // institucion, carrera info

const carrerasExternasOpts = computed(() => {
    if (!formRecepcion.institucion_id) return [];
    const inst = props.instituciones.find(i => i.id == formRecepcion.institucion_id);
    return inst ? inst.carreras : [];
});

const onArchivo = (e) => { 
    formRecepcion.pdf = e.target.files[0] ?? null; 
    archivoNombre.value = formRecepcion.pdf?.name ?? '';
};

// Llama al endpoint extraerIA de MallaExternaController
const extraerConIA = async () => {
    if (!formRecepcion.pdf) { errorExtraccion.value = 'Debes subir un archivo PDF.'; return; }
    if (!formRecepcion.carrera_externa_id) { errorExtraccion.value = 'Falta ID Carrera (Demo).'; return; }

    extrayendo.value = true;
    errorExtraccion.value = '';
    paso.value = 2;

    const formData = new FormData();
    formData.append('documento', formRecepcion.pdf);

    try {
        const { data } = await window.axios.post('/mallas-externas/extraer-ia', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        datosExtraidos.value = { institucion: data.institucion, carrera: data.carrera };
        cursosExtraidos.value = data.cursos || [];
        
        if (cursosExtraidos.value.length === 0) {
            errorExtraccion.value = 'La IA no pudo encontrar cursos en el PDF.';
            paso.value = 1;
        } else {
            paso.value = 3;
        }
    } catch (e) {
        errorExtraccion.value = e.response?.data?.message || 'Error al comunicarse con la IA.';
        paso.value = 1;
    } finally {
        extrayendo.value = false;
    }
};

// --- Carga sin IA -------------------------------------------------------------
// La lista de cursos sale de la plantilla transcrita en vez de la extracción. Vuelca
// en `cursosExtraidos`, la MISMA variable que llena la IA, así que la revisión del
// paso 3 y el guardado no distinguen de dónde vino.
const excel = ref(null);
const omitidas = ref([]);

const onExcel = (e) => { excel.value = e.target.files[0] ?? null; };

const subirExcel = async () => {
    if (!excel.value) { errorExtraccion.value = 'Selecciona el Excel de cursos.'; return; }
    if (!formRecepcion.carrera_externa_id) { errorExtraccion.value = 'Selecciona primero la carrera externa.'; return; }

    extrayendo.value = true;
    errorExtraccion.value = '';
    omitidas.value = [];

    const formData = new FormData();
    formData.append('archivo', excel.value);

    try {
        const { data } = await window.axios.post('/mallas-externas/previsualizar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        cursosExtraidos.value = data.cursos || [];
        omitidas.value = data.omitidas || [];

        if (cursosExtraidos.value.length === 0) {
            errorExtraccion.value = 'El archivo no tiene ningún curso con nombre.';
        } else {
            // No pasa por el paso 2: leer un Excel es inmediato, no hay nada que esperar.
            paso.value = 3;
        }
    } catch (e) {
        errorExtraccion.value = e.response?.data?.message || 'No se pudo leer el archivo.';
    } finally {
        extrayendo.value = false;
    }
};

const guardarMallaOficial = () => {
    const formData = new FormData();
    formData.append('carrera_externa_id', formRecepcion.carrera_externa_id);
    formData.append('anio', formRecepcion.anio);
    formData.append('version', formRecepcion.version);
    formData.append('pdf', formRecepcion.pdf);
    formData.append('cursos', JSON.stringify(cursosExtraidos.value));

    window.axios.post('/mallas-externas', formData).then(() => {
        router.get('/equivalencias');
    }).catch(e => {
        alert('Error al guardar la malla: ' + (e.response?.data?.message || 'Revisa consola'));
    });
};
</script>

<template>
    <div class="max-w-6xl">
        <!-- Encabezado -->
        <div class="mb-5">
            <h1 class="text-2xl font-semibold text-[#1F3864]">Registrar Malla Externa</h1>
            <p class="mt-1 text-sm text-slate-500">Sube la malla oficial en PDF y extrae su catálogo de cursos con IA.</p>
        </div>

        <div class="mb-6 flex gap-6 border-b border-slate-200 text-sm font-medium">
            <Link href="/equivalencias" class="-mb-px border-b-2 border-transparent pb-2 text-slate-500 hover:text-[#2E75B6]">
                Catálogo de Mallas Externas
            </Link>
            <span class="-mb-px border-b-2 border-[#1F3864] pb-2 text-[#1F3864]">Procesar Malla</span>
        </div>

        <!-- Stepper Visual Pipeline -->
        <div class="mb-6 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-6 py-5 shadow-sm overflow-x-auto">
            <template v-for="(p, i) in PASOS" :key="p.n">
                <div class="flex items-center gap-3 shrink-0"
                     :class="p.n <= paso ? 'opacity-100' : 'opacity-40'">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold"
                          :class="paso >= p.n ? 'bg-[#1F3864] text-white' : 'bg-slate-200 text-slate-500'">{{ p.n }}</span>
                    <span class="text-sm font-medium text-[#1F3864]">{{ p.label }}</span>
                </div>
                <div v-if="i < PASOS.length - 1" class="mx-3 h-px min-w-[20px] flex-1 bg-slate-200 shrink-0" :class="paso > p.n ? 'bg-[#1F3864]' : ''"></div>
            </template>
        </div>

        <!-- Alertas de Error -->
        <div v-if="errorExtraccion" class="mb-6 rounded-md bg-red-50 p-4 text-sm text-red-700 border border-red-200">
            ⚠ {{ errorExtraccion }}
        </div>

        <!-- Layout General -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Columna Izquierda: Visor del PDF / Info de Malla -->
            <div class="col-span-1 space-y-6">
                <!-- Cuando ya hay malla creada -->
                <div v-if="malla" class="rounded-xl border border-slate-200 bg-slate-50 p-6 shadow-sm">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">Malla Externa Oficial</h3>
                    <div class="space-y-4">
                        <div><label class="text-xs text-slate-500 block">Institución</label><p class="font-medium text-slate-800">{{ malla.institucion }}</p></div>
                        <div><label class="text-xs text-slate-500 block">Carrera</label><p class="font-medium text-slate-800">{{ malla.carrera }}</p></div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-xs text-slate-500 block">Año</label><p class="font-medium text-slate-800">{{ malla.anio }}</p></div>
                            <div><label class="text-xs text-slate-500 block">Versión</label><p class="font-medium text-slate-800">{{ malla.version || 'Única' }}</p></div>
                        </div>
                        <div class="pt-2">
                            <a v-if="malla.pdf_url" :href="malla.pdf_url" target="_blank"
                               class="inline-flex w-full justify-center items-center gap-2 rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#1F3864]">
                                📄 Ver Documento Oficial (PDF)
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Visor Temporal para la Recepción -->
                <div v-else class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden min-h-[400px] flex flex-col justify-center items-center bg-slate-50 p-6">
                    <template v-if="!formRecepcion.pdf">
                        <div class="text-4xl mb-3 opacity-20">📄</div>
                        <p class="text-sm text-slate-500 text-center">Sube un archivo PDF para previsualizarlo aquí.</p>
                    </template>
                    <template v-else>
                        <div class="text-4xl mb-3 text-[#2E75B6]">📑</div>
                        <p class="text-sm font-semibold text-slate-700 text-center">{{ archivoNombre }}</p>
                        <p class="text-xs text-slate-400 mt-2">Documento listo para ser procesado por la IA.</p>
                    </template>
                </div>
            </div>

            <!-- Columna Derecha: Contenido de los Pasos -->
            <div class="col-span-1 lg:col-span-2">

                <!-- PASO 1: Recepción -->
                <section v-if="paso === 1 && !malla" class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h2 class="mb-5 text-xl font-semibold text-[#1F3864]">Recepción de Documento Oficial</h2>
                    <p class="mb-6 text-sm text-slate-500">Ingresa los datos de la malla externa oficial y sube el PDF para extraer el catálogo automáticamente.</p>

                    <div class="space-y-5 max-w-lg">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Institución Externa <span class="text-red-500">*</span></label>
                                <select v-model="formRecepcion.institucion_id" required @change="formRecepcion.carrera_externa_id = ''"
                                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6]">
                                    <option value="">Selecciona la institución...</option>
                                    <option v-for="inst in instituciones" :key="inst.id" :value="inst.id">
                                        {{ inst.nombre }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Carrera Externa <span class="text-red-500">*</span></label>
                                <select v-model="formRecepcion.carrera_externa_id" required :disabled="!formRecepcion.institucion_id"
                                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] disabled:opacity-50 disabled:bg-slate-50">
                                    <option value="">Selecciona la carrera...</option>
                                    <option v-for="carrera in carrerasExternasOpts" :key="carrera.id" :value="carrera.id">
                                        {{ carrera.nombre }}
                                    </option>
                                </select>
                                <p v-if="formRecepcion.institucion_id && carrerasExternasOpts.length === 0" class="mt-1 text-xs text-red-500">Esta institución no tiene carreras registradas.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Año de Malla <span class="text-red-500">*</span></label>
                                <input v-model="formRecepcion.anio" type="text" required
                                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6]" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Versión</label>
                                <input v-model="formRecepcion.version" type="text"
                                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6]" />
                            </div>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <label class="mb-2 block text-sm font-medium text-slate-700">Malla Oficial (Formato PDF) <span class="text-red-500">*</span></label>
                            <input type="file" accept="application/pdf" @change="onArchivo" required
                                   class="w-full text-sm text-slate-500 file:mr-4 file:rounded-md file:border-0 file:bg-[#1F3864]/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#1F3864] hover:file:bg-[#1F3864]/20" />
                            <p class="mt-1 text-xs text-slate-400">Se conserva siempre como fuente oficial.</p>
                        </div>

                        <!-- Carga sin IA: el usuario transcribe el PDF a la plantilla. -->
                        <div class="pt-2 border-t border-slate-100">
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                <label class="block text-sm font-medium text-slate-700">Cursos en Excel <span class="font-normal text-slate-400">(opcional)</span></label>
                                <a href="/mallas-externas/plantilla" class="text-xs font-medium text-[#2E75B6] hover:underline">Descargar plantilla</a>
                            </div>
                            <input type="file" accept=".xlsx,.xls,.csv" @change="onExcel"
                                   class="w-full text-sm text-slate-500 file:mr-4 file:rounded-md file:border-0 file:bg-[#2E75B6]/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#2E75B6] hover:file:bg-[#2E75B6]/20" />
                            <p class="mt-1 text-xs text-slate-400">
                                Transcribe la malla del PDF a la plantilla y súbela. Es la alternativa a la extracción con IA.
                            </p>
                        </div>
                    </div>

                    <div class="mt-10 flex flex-wrap items-center justify-end gap-3">
                        <button @click="subirExcel" :disabled="!excel || !formRecepcion.carrera_externa_id || extrayendo"
                                class="rounded-md border border-[#2E75B6] px-6 py-3 text-sm font-bold text-[#2E75B6] hover:bg-blue-50 disabled:opacity-50">
                            Cargar desde Excel →
                        </button>
                        <button @click="extraerConIA" :disabled="!formRecepcion.pdf || !formRecepcion.carrera_externa_id"
                                class="rounded-md bg-[#7030A0] px-6 py-3 text-sm font-bold text-white shadow hover:bg-purple-800 disabled:opacity-50">
                            ✨ Procesar con IA →
                        </button>
                    </div>
                </section>

                <!-- PASO 2: Extracción IA (Loading State) -->
                <section v-if="paso === 2" class="rounded-xl border border-purple-200 bg-purple-50 p-12 shadow-sm flex flex-col items-center justify-center min-h-[400px]">
                    <div class="w-16 h-16 border-4 border-purple-200 border-t-[#7030A0] rounded-full animate-spin mb-6"></div>
                    <h2 class="text-xl font-bold text-[#7030A0] mb-2">Analizando Documento...</h2>
                    <p class="text-sm text-purple-700 text-center max-w-md">La Inteligencia Artificial está leyendo el PDF y extrayendo los códigos, nombres y créditos del plan de estudios.</p>
                </section>

                <!-- PASO 3: Catálogo Extraído -->
                <section v-if="paso === 3" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-[#1F3864]">Revisión del Catálogo Extraído</h2>
                        <span class="text-xs font-medium text-white bg-green-600 px-2.5 py-1 rounded-full">{{ cursosExtraidos.length }} cursos detectados</span>
                    </div>
                    
                    <!-- Solo la IA deduce institución y carrera del documento. Con Excel no
                         hay nada que deducir, así que el bloque no se muestra en vez de
                         afirmar «desconocida» sobre datos que el usuario ya eligió arriba. -->
                    <div v-if="datosExtraidos.institucion || datosExtraidos.carrera" class="mb-4 bg-slate-50 p-4 rounded-md border border-slate-200">
                        <p class="text-xs text-slate-500 uppercase tracking-wide">Info detectada del PDF</p>
                        <p class="font-medium text-slate-800 mt-1">{{ datosExtraidos.institucion?.nombre || 'Institución desconocida' }}</p>
                        <p class="text-sm text-slate-600">{{ datosExtraidos.carrera?.nombre || 'Carrera desconocida' }}</p>
                    </div>

                    <!-- Filas que el lector descartó, con su línea para poder corregirlas. -->
                    <div v-if="omitidas.length" class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-medium text-amber-900">
                            Se omitieron {{ omitidas.length }} fila{{ omitidas.length === 1 ? '' : 's' }} del archivo
                        </p>
                        <ul class="mt-1 space-y-0.5 text-xs text-amber-800">
                            <li v-for="(o, i) in omitidas" :key="i">Línea {{ o.linea }}: {{ o.motivo }}</li>
                        </ul>
                    </div>

                    <div class="max-h-[400px] overflow-y-auto border border-slate-200 rounded-lg">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Cód.</th>
                                    <th class="px-4 py-3 font-semibold">Nombre del Curso</th>
                                    <th class="px-4 py-3 font-semibold text-center">Cr.</th>
                                    <th class="px-4 py-3 font-semibold text-center">Ciclo</th>
                                    <th class="px-4 py-3 text-right">Quitar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="(c, idx) in cursosExtraidos" :key="idx" class="hover:bg-slate-50">
                                    <td class="px-4 py-2"><input v-model="c.codigo" class="w-16 border-slate-300 rounded text-xs py-1" /></td>
                                    <td class="px-4 py-2"><input v-model="c.nombre" class="w-full border-slate-300 rounded text-xs py-1" /></td>
                                    <td class="px-4 py-2 text-center"><input v-model="c.creditos" class="w-12 border-slate-300 rounded text-xs py-1 text-center" /></td>
                                    <td class="px-4 py-2 text-center"><input v-model="c.ciclo" class="w-12 border-slate-300 rounded text-xs py-1 text-center" /></td>
                                    <td class="px-4 py-2 text-right">
                                        <button @click="cursosExtraidos.splice(idx, 1)" class="text-red-500 hover:text-red-700 text-xs">🗑</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button @click="guardarMallaOficial" 
                                class="rounded-md bg-green-600 px-6 py-3 text-sm font-bold text-white shadow hover:bg-green-700">
                            ✓ Confirmar y Crear Malla
                        </button>
                    </div>
                </section>

            </div>
        </div>
    </div>
</template>
