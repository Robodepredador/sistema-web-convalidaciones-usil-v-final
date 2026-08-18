<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    options: { type: Array, default: () => [] }, // strings o {value,label}
    placeholder: { type: String, default: 'Escribe para buscar…' },
    disabled: { type: Boolean, default: false },
    allowFree: { type: Boolean, default: false }, // permite valores libres (no solo de la lista)
    creatable: { type: Boolean, default: false }, // permite crear un ítem nuevo con el texto escrito
});
const emit = defineEmits(['update:modelValue', 'create']);

const crear = () => {
    const texto0 = texto.value.trim();
    if (texto0) { emit('create', texto0); abierto.value = false; }
};

const opciones = computed(() => props.options.map((o) => (typeof o === 'object' ? o : { value: o, label: o })));
const labelDe = (val) => opciones.value.find((o) => String(o.value) === String(val))?.label ?? (props.allowFree ? (val ?? '') : '');

const texto = ref(labelDe(props.modelValue));
const abierto = ref(false);
const activo = ref(-1);

// Con la lista abierta no se pisa lo que el usuario está escribiendo, pero un vaciado
// desde fuera (el padre resetea tras dar de alta) sí tiene que limpiar el campo: si no,
// conserva el texto anterior y «puedes añadir varios seguidos» obliga a borrar a mano.
// Vaciar tecleando también pasa por aquí y es inocuo: labelDe('') ya es ''.
watch(() => props.modelValue, (v) => { if (!abierto.value || v === '' || v == null) texto.value = labelDe(v); });
watch(opciones, () => { if (!abierto.value) texto.value = labelDe(props.modelValue); });

const filtradas = computed(() => {
    const q = texto.value.trim().toLowerCase();
    const base = q ? opciones.value.filter((o) => o.label.toLowerCase().includes(q)) : opciones.value;
    return base.slice(0, 60);
});

// Muestra "+ Agregar «texto»" cuando se puede crear y el texto no coincide exactamente con una opción.
const mostrarCrear = computed(() => {
    if (!props.creatable) return false;
    const q = texto.value.trim();
    if (!q) return false;
    return !opciones.value.some((o) => o.label.toLowerCase() === q.toLowerCase());
});

const onInput = (e) => {
    texto.value = e.target.value;
    abierto.value = true;
    activo.value = -1;
    if (props.allowFree) emit('update:modelValue', texto.value);
};
const seleccionar = (o) => { emit('update:modelValue', o.value); texto.value = o.label; abierto.value = false; };
const onFocus = () => { if (!props.disabled) abierto.value = true; };
const onBlur = () => {
    setTimeout(() => {
        abierto.value = false;
        if (!props.allowFree) texto.value = labelDe(props.modelValue); // revierte a una opción válida
    }, 150);
};
const onKeydown = (e) => {
    if (!abierto.value) return;
    if (e.key === 'ArrowDown') {
        activo.value = Math.min(filtradas.value.length - 1, activo.value + 1);
        e.preventDefault();
    } else if (e.key === 'ArrowUp') {
        activo.value = Math.max(0, activo.value - 1);
        e.preventDefault();
    } else if (e.key === 'Enter') {
        if (activo.value >= 0 && filtradas.value[activo.value]) {
            seleccionar(filtradas.value[activo.value]);
            e.preventDefault();
        } else if (mostrarCrear.value) {
            crear();
            e.preventDefault();
        }
    } else if (e.key === 'Escape') {
        abierto.value = false;
    }
};
const limpiar = () => { texto.value = ''; emit('update:modelValue', ''); abierto.value = true; };
</script>

<template>
    <div class="relative">
        <input :value="texto" @input="onInput" @focus="onFocus" @blur="onBlur" @keydown="onKeydown"
               :disabled="disabled" :placeholder="placeholder" autocomplete="off"
               class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#2E75B6] focus:ring-3 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-50 transition-all" />
        <button v-if="texto && !disabled" type="button" @mousedown.prevent="limpiar"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-300 hover:text-slate-500">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>

        <div v-if="abierto && (filtradas.length || mostrarCrear || (texto.trim() && !allowFree))"
             class="absolute z-40 mt-1.5 max-h-60 w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white py-1.5 text-xs shadow-xl animate-in fade-in zoom-in-95 duration-100">
            
            <!-- Botón destacado superior si no hay coincidencias directas -->
            <button v-if="mostrarCrear && !filtradas.length" type="button" @mousedown.prevent="crear"
                    class="flex w-full items-center gap-2.5 px-3.5 py-3 text-left font-bold text-[#1F3864] bg-blue-50/90 hover:bg-blue-100 border-b border-blue-100 transition-colors">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-lg bg-[#2E75B6] text-white text-xs font-black shadow-2xs">+</span>
                <span class="truncate">Registrar «<strong class="text-[#2E75B6]">{{ texto.trim() }}</strong>» como nueva carrera</span>
            </button>

            <ul v-if="filtradas.length">
                <li v-for="(o, i) in filtradas" :key="String(o.value)" @mousedown.prevent="seleccionar(o)"
                    :class="[i === activo ? 'bg-blue-50/80 text-[#1F3864]' : 'hover:bg-slate-50', String(o.value) === String(modelValue) ? 'font-bold text-[#1F3864] bg-blue-50/40' : 'text-slate-700']"
                    class="cursor-pointer px-3.5 py-2 transition-colors flex items-center justify-between">
                    <span class="truncate">{{ o.label }}</span>
                    <span v-if="String(o.value) === String(modelValue)" class="text-[#2E75B6] text-xs font-bold">✓</span>
                </li>
            </ul>

            <!-- Botón al pie si hay coincidencias parciales pero se quiere registrar como nuevo -->
            <button v-if="mostrarCrear && filtradas.length" type="button" @mousedown.prevent="crear"
                    class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left font-bold text-[#1F3864] bg-blue-50/80 hover:bg-blue-100/90 border-t border-slate-100 transition-colors">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-lg bg-[#2E75B6] text-white text-xs font-black shadow-2xs">+</span>
                <span class="truncate">Registrar «<strong class="text-[#2E75B6]">{{ texto.trim() }}</strong>» como nueva carrera</span>
            </button>

            <p v-else-if="!filtradas.length && !mostrarCrear && !allowFree" class="px-3.5 py-3 text-center text-slate-400 font-medium">
                No se encontraron opciones.
            </p>
        </div>
    </div>
</template>
