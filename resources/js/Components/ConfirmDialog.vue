<script setup>
import { nextTick, ref, useId, watch } from 'vue';

/**
 * Diálogo de confirmación para acciones difíciles de deshacer.
 * El slot por defecto recibe el contexto de la acción (qué se está confirmando),
 * para que el usuario no tenga que recordarlo desde la pantalla de atrás.
 */
const props = defineProps({
    open: Boolean,
    titulo: { type: String, default: '' },
    mensaje: { type: String, default: '' },
    textoConfirmar: { type: String, default: 'Confirmar' },
    textoCancelar: { type: String, default: 'Cancelar' },
    tono: { type: String, default: 'primario' }, // primario | exito | aviso
    procesando: Boolean,
});
const emit = defineEmits(['confirmar', 'cancelar']);

const TONO = {
    primario: 'bg-[#1F3864] hover:bg-[#2E75B6]',
    exito: 'bg-green-700 hover:bg-green-800',
    aviso: 'bg-orange-600 hover:bg-orange-700',
};

const id = useId();
const caja = ref(null);
const btnConfirmar = ref(null);
let origen = null; // quién abrió el diálogo, para devolverle el foco al cerrarlo

watch(() => props.open, async (abierto) => {
    if (abierto) {
        origen = document.activeElement;
        await nextTick();
        btnConfirmar.value?.focus();

        return;
    }
    // Sin esto el foco vuelve al inicio del documento y se pierde el hilo de navegación.
    origen?.focus?.();
    origen = null;
});

const cancelar = () => { if (! props.procesando) emit('cancelar'); };

// El fondo oculta el contenido para la vista, pero no para el tabulador:
// sin esta trampa el foco se escapa a la página de atrás.
const atraparTab = (e) => {
    const focusables = caja.value?.querySelectorAll('button:not([disabled]), a[href], input, select, textarea');
    if (! focusables?.length) return;

    const primero = focusables[0];
    const ultimo = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === primero) {
        e.preventDefault();
        ultimo.focus();
    } else if (! e.shiftKey && document.activeElement === ultimo) {
        e.preventDefault();
        primero.focus();
    }
};
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <!-- Clic fuera = cancelar: siempre hay una salida sin consecuencias. -->
            <div class="absolute inset-0 bg-slate-900/40" @click="cancelar"></div>

            <div ref="caja" role="dialog" aria-modal="true" tabindex="-1"
                 :aria-labelledby="`${id}-titulo`" :aria-describedby="mensaje ? `${id}-desc` : undefined"
                 @keydown.esc.prevent="cancelar" @keydown.tab="atraparTab"
                 class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h2 :id="`${id}-titulo`" class="text-lg font-semibold text-[#1F3864]">{{ titulo }}</h2>
                <p v-if="mensaje" :id="`${id}-desc`" class="mt-2 text-sm text-slate-600">{{ mensaje }}</p>

                <div v-if="$slots.default" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                    <slot />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="cancelar" :disabled="procesando"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
                        {{ textoCancelar }}
                    </button>
                    <button ref="btnConfirmar" type="button" @click="emit('confirmar')" :disabled="procesando"
                            :class="TONO[tono] ?? TONO.primario"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-60">
                        <svg v-if="procesando" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                        </svg>
                        {{ procesando ? 'Procesando…' : textoConfirmar }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
