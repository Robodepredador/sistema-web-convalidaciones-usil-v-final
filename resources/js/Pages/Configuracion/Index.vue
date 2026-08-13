<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ ia: Object, modelos: Object, noConvalidables: Array });

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
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h1 class="flex items-center gap-2 text-2xl font-semibold text-[#1F3864]">
                <span>⚙️</span> Configuración
            </h1>
            <p class="mt-1 text-sm text-slate-500">Ajustes del sistema: motor de IA y cursos no convalidables.</p>
        </div>

        <!-- Pestañas -->
        <div class="mb-6 flex flex-wrap gap-1 border-b border-slate-200">
            <button v-for="t in TABS" :key="t.id" @click="tab = t.id" type="button"
                    :class="tab === t.id ? 'border-[#1F3864] text-[#1F3864]' : 'border-transparent text-slate-500 hover:text-[#2E75B6]'"
                    class="-mb-px flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-medium">
                <span>{{ t.icon }}</span> {{ t.label }}
            </button>
        </div>

        <!-- ===================== Motor de IA ===================== -->
        <form v-show="tab === 'ia'" @submit.prevent="guardar" class="space-y-6">
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Motor de IA (convalidaciones)</h2>
                    <span :class="ia.disponible ? 'bg-violet-50 text-violet-700 ring-violet-200' : 'bg-slate-100 text-slate-500 ring-slate-200'"
                          class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset">
                        {{ ia.disponible ? '✨ IA activa' : 'IA inactiva' }}
                    </span>
                </div>

                <!-- Sin esta autorización el sistema se entrega con la IA apagada. -->
                <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-900">⚠️ Requiere autorización previa</p>
                    <p class="mt-1 text-sm text-amber-800">
                        Activar la IA envía el récord académico del postulante —nombre, documento de identidad y
                        notas— a un proveedor externo fuera del país. Es una transferencia internacional de datos
                        personales sujeta a la <strong>Ley N.° 29733</strong>: no la habilites sin la autorización
                        escrita del área legal de USIL. Mientras esté apagada, el sistema propone las equivalencias
                        por similitud de nombres, sin enviar nada fuera.
                    </p>
                </div>

                <!-- Proveedor -->
                <label class="mb-1 block text-sm font-medium text-slate-700">Proveedor</label>
                <div class="mb-5 grid grid-cols-2 gap-3">
                    <button type="button" @click="form.proveedor = 'gemini'"
                            :class="esGemini ? 'border-[#2E75B6] bg-blue-50 text-[#1F3864]' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                            class="rounded-lg border px-4 py-3 text-left text-sm font-medium">
                        Google Gemini
                        <span class="mt-0.5 block text-xs font-normal text-slate-400">Gratis vía Google AI Studio</span>
                    </button>
                    <button type="button" @click="form.proveedor = 'openai'"
                            :class="!esGemini ? 'border-[#2E75B6] bg-blue-50 text-[#1F3864]' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                            class="rounded-lg border px-4 py-3 text-left text-sm font-medium">
                        OpenAI
                        <span class="mt-0.5 block text-xs font-normal text-slate-400">Requiere plan de pago</span>
                    </button>
                </div>

                <!-- API key -->
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    {{ esGemini ? 'Google Gemini API key' : 'OpenAI API key' }}
                    <span v-if="esGemini" class="text-slate-400">(gratis)</span>
                </label>
                <div class="relative">
                    <input v-model="form[campoClave]" :type="verClave ? 'text' : 'password'"
                           :placeholder="claveGuardada ? '•••••••••••••• (clave guardada — escribe para reemplazar)' : 'Pega aquí tu API key'"
                           :disabled="form[campoLimpiar]"
                           class="w-full rounded-md border-slate-300 pr-10 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6] disabled:bg-slate-50" />
                    <button type="button" @click="verClave = !verClave"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                            :title="verClave ? 'Ocultar' : 'Mostrar'">
                        {{ verClave ? '🙈' : '👁️' }}
                    </button>
                </div>
                <p v-if="form.errors[campoClave]" class="mt-1 text-xs text-red-600">{{ form.errors[campoClave] }}</p>

                <label v-if="claveGuardada" class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                    <input v-model="form[campoLimpiar]" type="checkbox" class="rounded border-slate-300 text-red-500" />
                    Quitar la clave guardada (desactiva la IA)
                </label>

                <!-- Modelo -->
                <div class="mt-5">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Modelo</label>
                    <select v-model="form[campoModelo]"
                            class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                        <option v-for="m in modelosProveedor" :key="m" :value="m">{{ m }}</option>
                    </select>
                    <p v-if="esGemini" class="mt-1 text-xs text-slate-400">🆓 Gemini 2.5 Flash es gratis vía Google AI Studio.</p>
                </div>

                <!-- Probar conexión -->
                <div class="mt-5 flex items-center gap-3">
                    <button type="button" @click="probar" :disabled="probando"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-60">
                        {{ probando ? 'Probando…' : 'Probar conexión' }}
                    </button>
                    <span v-if="prueba" :class="prueba.ok ? 'text-green-600' : 'text-red-600'" class="text-sm">
                        {{ prueba.ok ? '✓' : '✕' }} {{ prueba.mensaje }}
                    </span>
                </div>
            </section>

            <!-- Ayuda -->
            <section class="rounded-xl border border-slate-200 bg-slate-50 p-6">
                <h3 class="mb-2 text-sm font-semibold text-slate-700">Formatos aceptados</h3>
                <p class="mb-4 text-sm text-slate-500">Imágenes, PDF, Word, Excel, texto/CSV.</p>
                <template v-if="esGemini">
                    <h3 class="mb-2 text-sm font-semibold text-slate-700">Cómo obtener la clave</h3>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600">
                        <li>Entra a <a href="https://aistudio.google.com/apikey" target="_blank" class="text-[#2E75B6] hover:underline">aistudio.google.com/apikey</a></li>
                        <li>Inicia sesión con tu cuenta Google</li>
                        <li>Pulsa <strong>Create API key</strong></li>
                        <li>Copia la clave y pégala arriba</li>
                    </ol>
                </template>
                <template v-else>
                    <h3 class="mb-2 text-sm font-semibold text-slate-700">Cómo obtener la clave</h3>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600">
                        <li>Entra a <a href="https://platform.openai.com/api-keys" target="_blank" class="text-[#2E75B6] hover:underline">platform.openai.com/api-keys</a></li>
                        <li>Pulsa <strong>Create new secret key</strong></li>
                        <li>Copia la clave y pégala arriba</li>
                    </ol>
                </template>
                <p class="mt-4 text-xs text-slate-400">🔒 La clave se guarda cifrada en la base de datos y nunca se muestra de vuelta.</p>
            </section>

            <div class="flex gap-3">
                <button type="submit" :disabled="form.processing"
                        class="rounded-md bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    Guardar configuración
                </button>
            </div>
        </form>



    </div>
</template>
