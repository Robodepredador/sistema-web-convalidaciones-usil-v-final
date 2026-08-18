<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';
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
    <div class="w-full pb-16">
        <VolverA href="/instituciones" texto="Instituciones externas" />

        <!-- Banner Header Hero -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <!-- Decorative blur background -->
            <div class="absolute -top-24 -right-24 w-80 h-80 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#0036DC] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div class="max-w-xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-sm">
                        <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Alta de Institución</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                        Nueva Institución Externa
                    </h1>
                    <p class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed">
                        Registra una nueva universidad o instituto de procedencia, su estado de licenciamiento ante SUNEDU y sus carreras de origen.
                    </p>
                </div>
            </div>
        </div>

        <!-- Alerta de Borrador Restaurado -->
        <div v-if="borradorRestaurado"
             class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50/90 p-4 text-xs font-medium text-amber-900 shadow-xs backdrop-blur-sm">
            <div class="flex items-center gap-2.5">
                <span class="grid h-6 w-6 place-items-center rounded-full bg-amber-200 text-amber-900 font-bold">!</span>
                <span>Se han restaurado datos sin guardar de una sesión anterior en este navegador.</span>
            </div>
            <button type="button" @click="descartarBorrador"
                    class="rounded-xl border border-amber-300 bg-white px-3.5 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-100 transition-colors shadow-2xs">
                Descartar borrador
            </button>
        </div>

        <form @submit.prevent="enviar" class="space-y-6">
            <!-- SECCIÓN 1: DATOS GENERALES Y SUNEDU -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs relative">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                    <span class="w-8 h-8 rounded-xl bg-blue-50 text-[#00205B] font-bold text-xs flex items-center justify-center border border-blue-100">
                        1
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Información Institucional y Licenciamiento</h2>
                        <p class="text-xs text-slate-400">Parámetros de procedencia, acreditación y régimen de gestión.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- Nombre Completo de la Institución -->
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Nombre oficial de la institución <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.nombre" type="text" required
                               placeholder="Ej. Universidad Peruana de Ciencias Aplicadas, Instituto Toulouse Lautrec…"
                               :class="form.errors.nombre ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-slate-300 focus:border-[#0036DC] focus:ring-[#0036DC]'"
                               class="w-full rounded-xl border py-2.5 text-xs font-medium text-slate-800 transition-colors" />
                        <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600 font-medium">{{ form.errors.nombre }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <!-- Tipo -->
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Tipo de Institución <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.tipo_id" required
                                    :class="form.errors.tipo_id ? 'border-red-400 ring-1 ring-red-300' : 'border-slate-300 focus:border-[#0036DC] focus:ring-[#0036DC]'"
                                    class="w-full rounded-xl border py-2.5 text-xs font-medium text-slate-800">
                                <option value="" disabled>Seleccione un tipo…</option>
                                <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                            </select>
                            <p v-if="form.errors.tipo_id" class="mt-1 text-xs text-red-600 font-medium">{{ form.errors.tipo_id }}</p>
                        </div>

                        <!-- País -->
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                País de origen
                            </label>
                            <input v-model="form.pais" type="text" placeholder="Ej. Perú, Colombia, España…"
                                   class="w-full rounded-xl border-slate-300 py-2.5 text-xs font-medium text-slate-800 focus:border-[#0036DC] focus:ring-[#0036DC]" />
                        </div>

                        <!-- Gestión -->
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Tipo de Gestión
                            </label>
                            <select v-model="form.gestion"
                                    class="w-full rounded-xl border-slate-300 py-2.5 text-xs font-medium text-slate-800 focus:border-[#0036DC] focus:ring-[#0036DC]">
                                <option value="">Sin especificar</option>
                                <option value="publica">Pública (Estatal)</option>
                                <option value="privada">Privada (Particular)</option>
                            </select>
                            <p v-if="form.errors.gestion" class="mt-1 text-xs text-red-600 font-medium">{{ form.errors.gestion }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 pt-2">
                        <!-- Licenciamiento SUNEDU -->
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Licenciamiento SUNEDU
                            </label>
                            <select v-model="form.licenciamiento"
                                    class="w-full rounded-xl border-slate-300 py-2.5 text-xs font-medium text-slate-800 focus:border-[#0036DC] focus:ring-[#0036DC]">
                                <option value="desconocido">Sin verificar / No aplica</option>
                                <option value="licenciada">Licenciada por SUNEDU</option>
                                <option value="no_licenciada">No licenciada</option>
                            </select>
                            <p v-if="form.errors.licenciamiento" class="mt-1 text-xs text-red-600 font-medium">{{ form.errors.licenciamiento }}</p>
                        </div>

                        <!-- Resolución de Licenciamiento -->
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Resolución de Licenciamiento <span class="text-slate-400 font-normal">(Opcional)</span>
                            </label>
                            <input v-model="form.licenciamiento_resolucion" type="text" maxlength="120"
                                   placeholder="Ej. Res. N.° 045-2019-SUNEDU/CD"
                                   class="w-full rounded-xl border-slate-300 py-2.5 text-xs font-medium text-slate-800 focus:border-[#0036DC] focus:ring-[#0036DC]" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: CARRERAS DE PROCEDENCIA -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-xl bg-blue-50 text-[#00205B] font-bold text-xs flex items-center justify-center border border-blue-100">
                            2
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Carreras de Procedencia</h2>
                            <p class="text-xs text-slate-400">Programas académicos cursados por postulantes en esta institución.</p>
                        </div>
                    </div>
                    <button type="button" @click="agregarCarrera"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-blue-50 text-xs font-bold text-[#0036DC] hover:bg-[#0036DC] hover:text-white transition-all shadow-2xs">
                        <span class="text-sm leading-none">+</span> Agregar carrera
                    </button>
                </div>

                <div class="space-y-3">
                    <div v-for="(c, i) in form.carreras" :key="i"
                         class="flex items-center gap-2.5 p-2 rounded-2xl bg-slate-50 border border-slate-200/70 hover:border-slate-300 transition-colors">
                        <span class="w-7 h-7 rounded-xl bg-white border border-slate-200 text-slate-500 font-mono text-[11px] font-bold flex items-center justify-center shrink-0">
                            {{ i + 1 }}
                        </span>
                        <input v-model="c.nombre" placeholder="Nombre del programa o carrera externa (ej. Administración y Negocios Internacionales)"
                               class="flex-1 rounded-xl border-slate-200 bg-white py-2 text-xs font-medium text-slate-800 focus:border-[#0036DC] focus:ring-[#0036DC]" />
                        <button type="button" @click="quitarCarrera(i)" v-if="form.carreras.length > 1"
                                 class="p-2 rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                 title="Eliminar carrera">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 3: ESTADO Y VIGENCIA -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-xs flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Estado de la Institución</h3>
                    <p class="text-xs text-slate-400">Si está activa, aparecerá disponible para convalidaciones y equivalencias.</p>
                </div>
                <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                    <input v-model="form.activa" type="checkbox"
                           class="w-5 h-5 rounded-lg border-slate-300 text-[#0036DC] focus:ring-[#0036DC] transition-colors" />
                    <span :class="form.activa ? 'text-emerald-700 font-bold' : 'text-slate-500 font-medium'" class="text-xs">
                        {{ form.activa ? 'Institución Activa' : 'Inactiva' }}
                    </span>
                </label>
            </div>

            <!-- BOTONES DE ACCIÓN -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <Link href="/instituciones"
                      class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors">
                    Cancelar
                </Link>
                <button type="submit" :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-7 py-2.5 rounded-xl bg-[#00205B] hover:bg-[#0036DC] text-xs font-bold text-white shadow-md hover:shadow-lg transition-all disabled:opacity-60">
                    <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Guardar institución
                </button>
            </div>
        </form>
    </div>
</template>

