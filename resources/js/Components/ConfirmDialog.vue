<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue';

/**
 * Diálogo de confirmación para acciones críticas o dictámenes.
 */
const props = defineProps({
    open: Boolean,
    abierto: Boolean,
    titulo: { type: String, default: '' },
    mensaje: { type: String, default: '' },
    detalle: { type: String, default: null },
    textoConfirmar: { type: String, default: 'Confirmar' },
    textoCancelar: { type: String, default: 'Cancelar' },
    tono: { type: String, default: 'primario' }, // primario | exito | aviso | peligro
    procesando: Boolean,
});
const emit = defineEmits(['confirmar', 'cancelar']);

const TONO = {
    primario: 'bg-[#1F3864] hover:bg-[#2E75B6] text-white',
    exito: 'bg-emerald-600 hover:bg-emerald-700 text-white',
    aviso: 'bg-amber-600 hover:bg-amber-700 text-white',
    peligro: 'bg-rose-600 hover:bg-rose-700 text-white',
};

const id = useId();
const caja = ref(null);
const btnConfirmar = ref(null);
let origen = null;

const estaVisible = computed(() => props.open || props.abierto);

watch(estaVisible, async (visible) => {
    if (visible) {
        origen = document.activeElement;
        await nextTick();
        btnConfirmar.value?.focus();
        return;
    }
    origen?.focus?.();
    origen = null;
});

const cancelar = () => { if (!props.procesando) emit('cancelar'); };

const atraparTab = (e) => {
    const focusables = caja.value?.querySelectorAll('button:not([disabled]), a[href], input, select, textarea');
    if (!focusables?.length) return;

    const primero = focusables[0];
    const ultimo = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === primero) {
        e.preventDefault();
        ultimo.focus();
    } else if (!e.shiftKey && document.activeElement === ultimo) {
        e.preventDefault();
        primero.focus();
    }
};
</script>

<template>
    <Teleport to="body">
        <div v-if="estaVisible" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="cancelar"></div>

            <div ref="caja" role="dialog" aria-modal="true" tabindex="-1"
                 :aria-labelledby="`${id}-titulo`" :aria-describedby="mensaje ? `${id}-desc` : undefined"
                 @keydown.esc.prevent="cancelar" @keydown.tab="atraparTab"
                 class="relative w-full max-w-lg rounded-3xl bg-white p-6 sm:p-8 shadow-2xl border border-slate-100 z-10 animate-in fade-in zoom-in-95 duration-200">
                
                <div class="flex items-start gap-4">
                    <div :class="[
                        tono === 'exito' ? 'bg-emerald-100 text-emerald-600' :
                        tono === 'peligro' ? 'bg-rose-100 text-rose-600' :
                        tono === 'aviso' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-[#1F3864]'
                    ]" class="h-11 w-11 rounded-2xl flex items-center justify-center shrink-0 shadow-xs">
                        <svg v-if="tono === 'exito'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <svg v-else-if="tono === 'peligro'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <svg v-else-if="tono === 'aviso'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M12 18.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <h2 :id="`${id}-titulo`" class="text-base sm:text-lg font-black text-slate-900 leading-snug">{{ titulo }}</h2>
                        <p v-if="mensaje" :id="`${id}-desc`" class="mt-1.5 text-xs sm:text-sm text-slate-600 leading-relaxed">{{ mensaje }}</p>
                    </div>
                </div>

                <div v-if="detalle || $slots.default" class="mt-5 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-xs">
                    <p v-if="detalle" class="font-medium text-slate-700 whitespace-pre-wrap leading-relaxed">{{ detalle }}</p>
                    <slot />
                </div>

                <div class="mt-7 flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="cancelar" :disabled="procesando"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors disabled:opacity-50 cursor-pointer">
                        {{ textoCancelar }}
                    </button>
                    <button ref="btnConfirmar" type="button" @click="emit('confirmar')" :disabled="procesando"
                            :class="TONO[tono] ?? TONO.primario"
                            class="inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-xs font-extrabold shadow-md hover:shadow-lg transition-all disabled:opacity-60 cursor-pointer">
                        <svg v-if="procesando" class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                        </svg>
                        <span>{{ procesando ? 'Procesando…' : textoConfirmar }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
