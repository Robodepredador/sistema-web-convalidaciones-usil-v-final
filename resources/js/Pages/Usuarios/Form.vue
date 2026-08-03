<script setup>
import { useForm, Link } from '@inertiajs/vue3';
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
    <div class="max-w-2xl">
        <h1 class="mb-6 text-2xl font-semibold text-[#1F3864]">
            {{ editando ? 'Editar usuario' : 'Nuevo usuario' }}
        </h1>

        <form @submit.prevent="enviar" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre completo <span class="text-red-500">*</span></label>
                <input v-model="form.nombre" type="text" required
                       :class="form.errors.nombre ? 'border-red-400 ring-1 ring-red-300' : 'border-slate-300'"
                       class="w-full rounded-md text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Correo institucional <span class="text-red-500">*</span></label>
                <input v-model="form.email" type="email" required
                       :class="form.errors.email ? 'border-red-400 ring-1 ring-red-300' : 'border-slate-300'"
                       class="w-full rounded-md text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Rol <span class="text-red-500">*</span></label>
                <select v-model="form.rol_id"
                        class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                    <option value="" disabled>Seleccione un rol</option>
                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nombre }}</option>
                </select>
                <p v-if="form.errors.rol_id" class="mt-1 text-xs text-red-600">{{ form.errors.rol_id }}</p>
            </div>

            <!-- Alcance por carrera (Coordinador / Director de Carrera) -->
            <div v-if="alcanceRol === 'carrera'">
                <label class="mb-1 block text-sm font-medium text-slate-700">Carreras a cargo</label>
                <p class="mb-2 text-xs text-slate-500">El usuario solo verá y evaluará estas carreras (una o más).</p>
                <div class="grid max-h-48 grid-cols-1 gap-1 overflow-y-auto rounded-md border border-slate-200 p-3 sm:grid-cols-2">
                    <label v-for="c in carreras" :key="c.id" class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" :value="c.id" v-model="form.carreras"
                               class="rounded border-slate-300 text-[#2E75B6]" />
                        {{ c.nombre }}
                    </label>
                </div>
            </div>

            <!-- Alcance por facultad (Decano) -->
            <div v-else-if="alcanceRol === 'facultad'">
                <label class="mb-1 block text-sm font-medium text-slate-700">Facultades a cargo</label>
                <p class="mb-2 text-xs text-slate-500">El decano verá todas las carreras de estas facultades.</p>
                <div class="grid max-h-48 grid-cols-1 gap-1 overflow-y-auto rounded-md border border-slate-200 p-3 sm:grid-cols-2">
                    <label v-for="f in facultades" :key="f.id" class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" :value="f.id" v-model="form.facultades"
                               class="rounded border-slate-300 text-[#2E75B6]" />
                        {{ f.nombre }}
                    </label>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.activo" type="checkbox" class="rounded border-slate-300 text-[#2E75B6]" />
                Usuario activo
            </label>

            <div class="flex gap-3 pt-2">
                <button type="submit" :disabled="form.processing"
                        class="rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    {{ editando ? 'Guardar cambios' : 'Crear usuario' }}
                </button>
                <Link href="/usuarios" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    Cancelar
                </Link>
            </div>
        </form>
    </div>
</template>
