<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ ia: Object, modelos: Object });

// --- Pestañas ---
const TABS = [
    { id: 'ia', label: 'Motor de IA', icon: '✨' },
];
const tab = ref('ia');


const form = useForm({
    proveedor: props.ia.proveedor ?? 'gemini',
    gemini_model: props.ia.gemini_model ?? 'gemini-2.5-flash',
    openai_model: props.ia.openai_model ?? 'gpt-4o',
    gemini_api_key: '',
    openai_api_key: '',
    limpiar_gemini: false,
    limpiar_openai: false,
});

const verClave = ref(false);
const prueba = ref(null);      // { ok, mensaje }
const probando = ref(false);

const esGemini = computed(() => form.proveedor === 'gemini');
const claveGuardada = computed(() => esGemini.value ? props.ia.gemini_key_set : props.ia.openai_key_set);
const modelosProveedor = computed(() => props.modelos[form.proveedor] ?? []);
const campoClave = computed(() => esGemini.value ? 'gemini_api_key' : 'openai_api_key');
const campoLimpiar = computed(() => esGemini.value ? 'limpiar_gemini' : 'limpiar_openai');
const campoModelo = computed(() => esGemini.value ? 'gemini_model' : 'openai_model');

const guardar = () => form.put('/configuracion', {
    preserveScroll: true,
    onSuccess: () => { form.gemini_api_key = ''; form.openai_api_key = ''; },
});

const probar = async () => {
    probando.value = true;
    prueba.value = null;
    try {
        const { data } = await window.axios.post('/configuracion/probar', {
            proveedor: form.proveedor,
            modelo: form[campoModelo.value],
            api_key: form[campoClave.value],
        });
        prueba.value = data;
    } catch (e) {
        prueba.value = { ok: false, mensaje: e.response?.data?.message || 'No se pudo probar la conexión.' };
    } finally {
        probando.value = false;
    }
};
</script>

<template>
    <div class="mx-auto max-w-4xl pb-16">
        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#0036DC] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Ajustes Generales</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Configuración del Sistema
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                            Administra las integraciones con servicios externos, parámetros globales y el motor de Inteligencia Artificial para homologaciones.
                        </p>
                    </div>
                </div>

                <!-- Estado del motor IA -->
                <div class="pt-6 flex items-center gap-3">
                    <span :class="ia.disponible ? 'bg-emerald-400/20 text-emerald-100 border-emerald-400/30' : 'bg-white/10 text-slate-200 border-white/20'"
                          class="inline-flex items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold border backdrop-blur-md">
                        <span :class="ia.disponible ? 'bg-emerald-400' : 'bg-slate-400'" class="h-2 w-2 rounded-full"></span>
                        {{ ia.disponible ? 'Motor IA Operativo (Conectado)' : 'Motor IA Inactivo / No Configurado' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Pestañas -->
        <div class="mb-6 flex flex-wrap gap-1 border-b border-slate-200">
            <button v-for="t in TABS" :key="t.id" @click="tab = t.id" type="button"
                    :class="tab === t.id ? 'border-[#00205B] text-[#00205B]' : 'border-transparent text-slate-500 hover:text-[#0036DC]'"
                    class="-mb-px flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-xs font-bold transition-colors">
                <span>{{ t.icon }}</span> {{ t.label }}
            </button>
        </div>

        <!-- ===================== Motor de IA ===================== -->
        <form v-show="tab === 'ia'" @submit.prevent="guardar" class="space-y-6">
            <section class="rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-8 shadow-sm">
                <div class="mb-6 flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h2 class="text-base font-bold text-[#00205B]">Motor de IA para Homologaciones y Análisis de Sílabos</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Configura la API Key y el modelo de lenguaje utilizado para convalidar asignaturas automáticamente.</p>
                    </div>
                </div>

                <!-- Aviso Legal de Protección de Datos -->
                <div class="mb-6 rounded-2xl border border-amber-200/80 bg-amber-50/70 p-5 text-xs">
                    <div class="flex items-center gap-2 font-bold text-amber-900 mb-1.5">
                        <svg class="h-4 w-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <span>Requiere autorización previa</span>
                    </div>
                    <p class="text-amber-800 leading-relaxed">
                        Activar la IA envía el récord académico del postulante —nombre, documento de identidad y notas— al proveedor externo seleccionado fuera del país. Es una transferencia internacional de datos personales sujeta a la <strong>Ley N.° 29733</strong>: no la habilites sin la autorización escrita del área legal de USIL. Mientras esté apagada, el sistema propone las equivalencias por similitud de nombres o reglas de catálogo local.
                    </p>
                </div>

                <!-- Proveedor -->
                <div class="mb-6">
                    <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-700">Proveedor de Inteligencia Artificial</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <button type="button" @click="form.proveedor = 'gemini'"
                                :class="esGemini ? 'border-[#0036DC] bg-blue-50/60 ring-2 ring-[#0036DC]/20 text-[#00205B]' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                class="rounded-2xl border p-4 text-left transition-all">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-sm">Google Gemini</span>
                                <span class="rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5">Recomendado</span>
                            </div>
                            <span class="mt-1 block text-xs text-slate-500 leading-relaxed">Gratis vía Google AI Studio con Gemini 2.5 Flash de alta velocidad y precisión.</span>
                        </button>
                        <button type="button" @click="form.proveedor = 'openai'"
                                :class="!esGemini ? 'border-[#0036DC] bg-blue-50/60 ring-2 ring-[#0036DC]/20 text-[#00205B]' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                class="rounded-2xl border p-4 text-left transition-all">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-sm">OpenAI GPT</span>
                                <span class="rounded-full bg-slate-100 text-slate-600 text-[10px] font-medium px-2 py-0.5">De pago</span>
                            </div>
                            <span class="mt-1 block text-xs text-slate-500 leading-relaxed">Requiere saldo / API Key con créditos en OpenAI Platform (GPT-4o).</span>
                        </button>
                    </div>
                </div>

                <!-- API key -->
                <div class="mb-6">
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                        {{ esGemini ? 'Google Gemini API Key' : 'OpenAI API Key' }}
                        <span v-if="esGemini" class="text-slate-400 font-normal">(gratis)</span>
                    </label>
                    <div class="relative">
                        <input v-model="form[campoClave]" :type="verClave ? 'text' : 'password'"
                               :placeholder="claveGuardada ? '•••••••••••••••••••••••• (clave guardada — escribe para reemplazar)' : 'Pega aquí tu API key…'"
                               :disabled="form[campoLimpiar]"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pr-10 pl-4 py-2.5 text-xs text-slate-800 focus:border-[#0036DC] focus:bg-white focus:outline-hidden focus:ring-3 focus:ring-[#0036DC]/20 disabled:bg-slate-100 transition-all duration-200" />
                        <button type="button" @click="verClave = !verClave"
                                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600"
                                :title="verClave ? 'Ocultar' : 'Mostrar'">
                            {{ verClave ? '🙈' : '👁️' }}
                        </button>
                    </div>
                    <p v-if="form.errors[campoClave]" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors[campoClave] }}</p>

                    <label v-if="claveGuardada" class="mt-2 flex items-center gap-2 text-xs font-medium text-rose-600 cursor-pointer">
                        <input v-model="form[campoLimpiar]" type="checkbox" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                        Quitar la clave guardada (desactiva la IA)
                    </label>
                </div>

                <!-- Modelo -->
                <div class="mb-6">
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Modelo de Lenguaje</label>
                    <select v-model="form[campoModelo]"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-800 focus:border-[#0036DC] focus:bg-white focus:outline-hidden focus:ring-3 focus:ring-[#0036DC]/20 transition-all duration-200">
                        <option v-for="m in modelosProveedor" :key="m" :value="m">{{ m }}</option>
                    </select>
                    <p v-if="esGemini" class="mt-1.5 text-xs text-slate-400">Gemini 2.5 Flash ofrece la mejor combinación de velocidad, ventana de contexto y cuota gratuita.</p>
                </div>

                <!-- Probar conexión -->
                <div class="flex items-center gap-3 pt-2">
                    <button type="button" @click="probar" :disabled="probando"
                            class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2 text-xs font-bold text-slate-700 shadow-2xs transition-colors disabled:opacity-60">
                        {{ probando ? 'Probando conexión…' : 'Probar conexión con IA' }}
                    </button>
                    <span v-if="prueba" :class="prueba.ok ? 'text-emerald-600 font-bold' : 'text-rose-600 font-semibold'" class="text-xs">
                        {{ prueba.ok ? '✓' : '✕' }} {{ prueba.mensaje }}
                    </span>
                </div>
            </section>

            <!-- Ayuda y Guía de Claves -->
            <section class="rounded-3xl border border-slate-200/80 bg-slate-50/70 p-6 text-xs text-slate-600">
                <h3 class="mb-1.5 font-bold uppercase tracking-wider text-slate-700">Formatos y documentos compatibles</h3>
                <p class="mb-4 text-slate-500">Imágenes (PNG, JPG, WEBP), Documentos PDF, Microsoft Word (DOCX), Excel (XLSX, CSV) y texto plano.</p>
                <template v-if="esGemini">
                    <h3 class="mb-1.5 font-bold uppercase tracking-wider text-slate-700">Pasos para obtener tu Google Gemini API Key gratuita</h3>
                    <ol class="list-decimal space-y-1.5 pl-5 text-slate-600">
                        <li>Ingresa a <a href="https://aistudio.google.com/apikey" target="_blank" class="text-[#0036DC] font-bold hover:underline">aistudio.google.com/apikey</a></li>
                        <li>Inicia sesión con tu cuenta Google institucional o personal</li>
                        <li>Haz clic en el botón <strong>Create API Key</strong></li>
                        <li>Copia el valor generado y pégalo en el campo superior</li>
                    </ol>
                </template>
                <template v-else>
                    <h3 class="mb-1.5 font-bold uppercase tracking-wider text-slate-700">Pasos para obtener tu OpenAI API Key</h3>
                    <ol class="list-decimal space-y-1.5 pl-5 text-slate-600">
                        <li>Ingresa a <a href="https://platform.openai.com/api-keys" target="_blank" class="text-[#0036DC] font-bold hover:underline">platform.openai.com/api-keys</a></li>
                        <li>Haz clic en <strong>Create new secret key</strong></li>
                        <li>Copia el valor y pégalo en el campo superior</li>
                    </ol>
                </template>
                <p class="mt-4 text-[11px] text-slate-400 flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <span>La clave se almacena cifrada con AES-256 en la base de datos y nunca se expone al cliente.</span>
                </p>
            </section>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="submit" :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] px-6 py-2.5 text-xs font-bold text-white shadow-md transition-all duration-200 hover:shadow-lg disabled:opacity-60">
                    <svg v-if="form.processing" class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Guardar Configuración</span>
                </button>
            </div>
        </form>
    </div>
</template>
