<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import VolverA from '../../Components/VolverA.vue';
import { computed } from 'vue';

const props = defineProps({ usuario: Object, roles: Array, carreras: Array, facultades: { type: Array, default: () => [] } });

const editando = computed(() => !!props.usuario);

const form = useForm({
    nombre: props.usuario?.nombre ?? '',
    email: props.usuario?.email ?? '',
    rol_id: props.usuario?.rol_id ?? '',
    carreras: props.usuario?.carreras ?? [],
    facultades: props.usuario?.facultades ?? [],
    activo: props.usuario?.activo ?? true,
});

// Alcance del rol seleccionado: carrera | facultad | global.
const alcanceRol = computed(() => props.roles.find((r) => r.id == form.rol_id)?.alcance ?? 'global');

const enviar = () => {
    editando.value
        ? form.put(`/usuarios/${props.usuario.id}`)
        : form.post('/usuarios');
};
</script>

<template>
    <div class="w-full pb-16">
        <VolverA href="/usuarios" texto="Volver a Usuarios" class="mb-4" />

        <!-- HERO HEADER BANNER -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#0036DC] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-8">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 mb-2 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                    <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Seguridad · Cuentas de Acceso</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                    {{ editando ? 'Editar Usuario' : 'Registrar Nuevo Usuario' }}
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                    {{ editando ? 'Modifica los privilegios, rol y alcance académico del usuario.' : 'Crea una cuenta institucional asignando sus responsabilidades en el sistema.' }}
                </p>
            </div>
        </div>

        <!-- FORMULARIO -->
        <div class="max-w-3xl mx-auto">
            <form @submit.prevent="enviar" class="space-y-6 rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-10 shadow-sm">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Nombre completo <span class="text-red-500">*</span></label>
                        <input v-model="form.nombre" type="text" required placeholder="Ej. Juan Pérez"
                               :class="form.errors.nombre ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/20'"
                               class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all duration-200" />
                        <p v-if="form.errors.nombre" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.nombre }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Correo institucional <span class="text-red-500">*</span></label>
                        <input v-model="form.email" type="email" required placeholder="ejemplo@usil.edu.pe"
                               :class="form.errors.email ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/20'"
                               class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all duration-200" />
                        <p v-if="form.errors.email" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.email }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Rol del Sistema <span class="text-red-500">*</span></label>
                    <select v-model="form.rol_id" required
                            :class="form.errors.rol_id ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-200 focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/20'"
                            class="w-full rounded-xl border bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:outline-hidden transition-all duration-200">
                        <option value="" disabled>Seleccione un rol de acceso</option>
                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nombre }}</option>
                    </select>
                    <p v-if="form.errors.rol_id" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.rol_id }}</p>
                </div>

                <!-- Alcance por carrera (Coordinador / Director de Carrera) -->
                <div v-if="alcanceRol === 'carrera'" class="rounded-2xl bg-slate-50 p-4 border border-slate-200/60 space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Carreras a cargo</label>
                    <p class="text-[11px] text-slate-500">El usuario solo verá y evaluará las convalidaciones de estas carreras asignadas.</p>
                    <div class="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-2">
                        <label v-for="c in carreras" :key="c.id" class="flex items-center gap-2 text-xs text-slate-700 hover:text-[#00205B] cursor-pointer">
                            <input type="checkbox" :value="c.id" v-model="form.carreras"
                                   class="rounded border-slate-300 text-[#0036DC] focus:ring-[#0036DC]" />
                            <span>{{ c.nombre }}</span>
                        </label>
                    </div>
                </div>

                <!-- Alcance por facultad (Decano) -->
                <div v-else-if="alcanceRol === 'facultad'" class="rounded-2xl bg-slate-50 p-4 border border-slate-200/60 space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Facultades a cargo</label>
                    <p class="text-[11px] text-slate-500">El usuario gestionará todas las carreras de estas facultades.</p>
                    <div class="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-2">
                        <label v-for="f in facultades" :key="f.id" class="flex items-center gap-2 text-xs text-slate-700 hover:text-[#00205B] cursor-pointer">
                            <input type="checkbox" :value="f.id" v-model="form.facultades"
                                   class="rounded border-slate-300 text-[#0036DC] focus:ring-[#0036DC]" />
                            <span>{{ f.nombre }}</span>
                        </label>
                    </div>
                </div>

                <!-- Estado Activo Toggle -->
                <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 border border-slate-200/60">
                    <div>
                        <p class="text-xs font-bold text-slate-800">Estado de la cuenta</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Al estar activo, el usuario podrá ingresar al sistema con sus credenciales.</p>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input v-model="form.activo" type="checkbox" class="peer sr-only" />
                        <div class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-[#0036DC] peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none"></div>
                    </label>
                </div>

                <!-- Botones de Acción -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-6">
                    <Link href="/usuarios" class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-5 py-2.5 text-xs font-bold text-slate-600 transition-colors">
                        Cancelar
                    </Link>
                    <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] px-6 py-2.5 text-xs font-bold text-white shadow-md transition-all duration-200 hover:shadow-lg disabled:opacity-60">
                        <svg v-if="form.processing" class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ editando ? 'Guardar Cambios' : 'Crear Usuario' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
