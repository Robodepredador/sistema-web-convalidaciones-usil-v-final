<script setup>
import { ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    credenciales: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update:modelValue', 'cerrar']);

const copiadoTotal = ref(false);
const copiadoCampo = ref('');

const copiarTexto = async (texto, idCampo = '') => {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(texto);
        } else {
            const textArea = document.createElement('textarea');
            textArea.value = texto;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            document.execCommand('copy');
            textArea.remove();
        }

        if (idCampo) {
            copiadoCampo.value = idCampo;
            setTimeout(() => {
                copiadoCampo.value = '';
            }, 2000);
        } else {
            copiadoTotal.value = true;
            setTimeout(() => {
                copiadoTotal.value = false;
            }, 2500);
        }
    } catch (err) {
        console.error('Error al copiar:', err);
    }
};

const copiarTodo = () => {
    const c = props.credenciales || {};
    const texto = [
        'USIL - CREDENCIALES DE ACCESO',
        '────────────────────────────────────────',
        `Nombre: ${c.nombre || '—'}`,
        c.identificador ? `Código / Rol: ${c.identificador}` : null,
        `Usuario / Correo: ${c.email || '—'}`,
        `Contraseña Temporal: ${c.password_temporal || '—'}`,
        `Enlace de Acceso: ${c.login_url || window.location.origin + '/portal/login'}`,
        '────────────────────────────────────────',
        'Nota: En el primer inicio de sesión se solicitará cambiar esta contraseña temporal.',
    ].filter(Boolean).join('\n');

    copiarTexto(texto);
};

const cerrar = () => {
    emit('update:modelValue', false);
    emit('cerrar');
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-98"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-98">
            <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs" @click.self="cerrar">
                <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl border border-slate-200">
                    <!-- HEADER MINIMALISTA -->
                    <div class="flex items-start justify-between p-5 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 border border-slate-200/60">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 leading-snug">
                                    {{ credenciales.titulo || 'Credenciales de Acceso' }}
                                </h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    Copia los datos de acceso para entregárselos al usuario.
                                </p>
                            </div>
                        </div>

                        <button type="button" @click="cerrar"
                                class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- BODY MINIMALISTA -->
                    <div class="p-5 space-y-3.5 text-xs">
                        <!-- Destinatario -->
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 text-xs">
                            <span class="text-slate-500 font-medium">Destinatario:</span>
                            <span class="font-bold text-slate-800">
                                {{ credenciales.nombre }}
                                <span v-if="credenciales.identificador" class="text-slate-400 font-normal">({{ credenciales.identificador }})</span>
                            </span>
                        </div>

                        <!-- Campos en lista limpia y sobria -->
                        <div class="space-y-2.5">
                            <!-- Correo / Usuario -->
                            <div class="flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-200/80 bg-slate-50/60">
                                <div class="min-w-0 flex-1">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Usuario / Correo</span>
                                    <span class="font-semibold text-slate-800 truncate block select-all">{{ credenciales.email }}</span>
                                </div>
                                <button type="button" @click="copiarTexto(credenciales.email, 'email')"
                                        :class="copiadoCampo === 'email' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                                        class="shrink-0 px-3 py-1 rounded-lg border text-xs font-semibold transition-all">
                                    {{ copiadoCampo === 'email' ? '✓ Copiado' : 'Copiar' }}
                                </button>
                            </div>

                            <!-- Contraseña Temporal -->
                            <div class="flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-200/80 bg-slate-50/60">
                                <div class="min-w-0 flex-1">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Contraseña Temporal</span>
                                    <span class="font-mono font-bold text-slate-900 text-sm tracking-wider block select-all">{{ credenciales.password_temporal }}</span>
                                </div>
                                <button type="button" @click="copiarTexto(credenciales.password_temporal, 'pass')"
                                        :class="copiadoCampo === 'pass' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                                        class="shrink-0 px-3 py-1 rounded-lg border text-xs font-semibold transition-all">
                                    {{ copiadoCampo === 'pass' ? '✓ Copiado' : 'Copiar Clave' }}
                                </button>
                            </div>

                            <!-- Enlace de Ingreso -->
                            <div class="flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-200/80 bg-slate-50/60">
                                <div class="min-w-0 flex-1">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Enlace de Ingreso</span>
                                    <span class="text-slate-600 truncate block text-[11px] select-all">{{ credenciales.login_url }}</span>
                                </div>
                                <button type="button" @click="copiarTexto(credenciales.login_url, 'url')"
                                        :class="copiadoCampo === 'url' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                                        class="shrink-0 px-3 py-1 rounded-lg border text-xs font-semibold transition-all">
                                    {{ copiadoCampo === 'url' ? '✓ Copiado' : 'Copiar Link' }}
                                </button>
                            </div>
                        </div>

                        <!-- Nota discreta -->
                        <p class="text-[11px] text-slate-500 leading-relaxed px-1">
                            ℹ️ En su primer inicio de sesión se le solicitará cambiar obligatoriamente la contraseña temporal.
                        </p>
                    </div>

                    <!-- FOOTER MINIMALISTA -->
                    <div class="bg-slate-50/60 border-t border-slate-100 p-4 flex items-center justify-end gap-2.5">
                        <button type="button" @click="cerrar"
                                class="px-4 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-xs font-semibold text-slate-700 transition-colors">
                            Cerrar
                        </button>
                        <button type="button" @click="copiarTodo"
                                :class="copiadoTotal ? 'bg-slate-900' : 'bg-[#00205B] hover:bg-[#0036DC]'"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-white transition-colors shadow-2xs">
                            <svg v-if="copiadoTotal" class="h-3.5 w-3.5 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v2.25A2.25 2.25 0 0 1 13.5 21.75h-6A2.25 2.25 0 0 1 5.25 19.5V8.25a2.25 2.25 0 0 1 2.25-2.25h2.25m3.75 0V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.774-.108m-3.5 0A48.532 48.532 0 0 1 12 3.75c.59 0 1.17.037 1.75.108m-5.25 3.642V18a2.25 2.25 0 0 0 2.25 2.25h6a2.25 2.25 0 0 0 2.25-2.25V7.5m-10.5 0h10.5" />
                            </svg>
                            <span>{{ copiadoTotal ? 'Copiado' : 'Copiar Credenciales' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
