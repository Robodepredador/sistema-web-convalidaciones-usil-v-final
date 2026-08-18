<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import VolverA from '../../../Components/VolverA.vue';

const props = defineProps({ modalidad: Object });
const editando = !!props.modalidad;

const form = useForm({
    codigo: props.modalidad?.codigo ?? '',
    nombre: props.modalidad?.nombre ?? '',
    activo: props.modalidad?.activo ?? true,
});

const enviar = () => {
    if (editando) form.put(`/estructura/modalidades/${props.modalidad.id}`);
    else form.post('/estructura/modalidades');
};
</script>

<template>
    <div class="w-full pb-16">
        <VolverA href="/estructura/modalidades" texto="Volver a Modalidades" class="mb-4" />

        <!-- HERO HEADER BANNER -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#0036DC] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-8">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 mb-2 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                    <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Estructura Institucional · Modalidades</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                    {{ editando ? 'Editar Modalidad de Estudio' : 'Registrar Nueva Modalidad' }}
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                    {{ editando ? 'Modifica la definición del formato o modalidad formativa.' : 'Agrega una nueva modalidad de estudio (Presencial, Semipresencial, Virtual).' }}
                </p>
            </div>
        </div>

        <!-- FORMULARIO -->
        <div class="max-w-3xl mx-auto">
            <form @submit.prevent="enviar" class="rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-10 shadow-sm space-y-6">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Código de Modalidad <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.codigo" type="text" maxlength="20" placeholder="Ej. PRES, SEMIPRES, VIRTUAL…" required
                               :class="form.errors.codigo ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/20'"
                               class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all duration-200" />
                        <p v-if="form.errors.codigo" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.codigo }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Nombre Oficial de la Modalidad <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.nombre" type="text" placeholder="Ej. Presencial, Semipresencial, A Distancia…" required
                               :class="form.errors.nombre ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/20'"
                               class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all duration-200" />
                        <p v-if="form.errors.nombre" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.nombre }}</p>
                    </div>
                </div>

                <!-- Estado Activo Toggle -->
                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 border border-slate-200/60">
                    <div>
                        <p class="text-xs font-bold text-slate-800">Estado de la modalidad</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Al estar activa, se podrá seleccionar en los planes y mallas curriculares.</p>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input v-model="form.activo" type="checkbox" class="peer sr-only" />
                        <div class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-[#0036DC] peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none"></div>
                    </label>
                </div>

                <!-- Botones de Acción -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-6">
                    <Link href="/estructura/modalidades" class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-5 py-2.5 text-xs font-bold text-slate-600 transition-colors">
                        Cancelar
                    </Link>
                    <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] px-6 py-2.5 text-xs font-bold text-white shadow-md transition-all duration-200 hover:shadow-lg disabled:opacity-60">
                        <svg v-if="form.processing" class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ editando ? 'Guardar Cambios' : 'Registrar Modalidad' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
