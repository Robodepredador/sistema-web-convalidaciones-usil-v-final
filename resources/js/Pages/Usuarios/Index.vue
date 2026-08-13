<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({ usuarios: Object, activos: Number, roles: Array, filtros: Object });

const filtro = reactive({
    q: props.filtros?.q ?? '',
    rol_id: props.filtros?.rol_id ?? '',
    estado: props.filtros?.estado ?? '',
});
const aplicar = () => router.get('/usuarios', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => { filtro.q = ''; filtro.rol_id = ''; filtro.estado = ''; router.get('/usuarios', {}, { preserveScroll: true, replace: true }); };
const cambiarEstado = (u) => router.patch(`/usuarios/${u.id}/estado`, {}, { preserveScroll: true });
const resetear = (u) => { if (confirm(`¿Restablecer la contraseña de "${u.nombre}"? Se generará una temporal.`)) router.patch(`/usuarios/${u.id}/reset-password`, {}, { preserveScroll: true }); };

const rolBadge = (r) =>
    r === 'Administrador' ? 'bg-violet-50 text-violet-700 ring-violet-200' : 'bg-sky-50 text-sky-700 ring-sky-200';
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">Usuarios</h1>
                <p class="mt-1 text-sm text-slate-500">Administra las cuentas, roles y accesos del sistema.</p>
            </div>
            <Link href="/usuarios/create"
                  class="inline-flex items-center gap-2 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#2E75B6]">
                <span class="text-base leading-none">+</span> Nuevo usuario
            </Link>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-[1fr_auto]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
                        <input v-model="filtro.q" type="text" placeholder="Nombre o correo…" @keyup.enter="aplicar"
                               class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Rol</label>
                        <select v-model="filtro.rol_id" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Todos</option>
                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Estado</label>
                        <select v-model="filtro.estado" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Todos</option>
                            <option value="activo">Activos</option>
                            <option value="inactivo">Inactivos</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button @click="aplicar" class="rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864]">Filtrar</button>
                    <button @click="limpiar" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Limpiar</button>
                </div>
            </div>
            <div class="flex min-w-[160px] flex-col justify-center rounded-xl bg-gradient-to-br from-[#1F3864] to-[#2E75B6] p-5 text-white shadow-sm">
                <span class="text-xs font-medium uppercase tracking-wide text-blue-100">Usuarios activos</span>
                <span class="mt-1 text-4xl font-bold leading-none">{{ activos }}</span>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Nombre</th>
                            <th class="px-4 py-3 font-semibold">Correo</th>
                            <th class="px-4 py-3 font-semibold">Rol</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="u in usuarios.data" :key="u.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <span class="font-medium text-slate-800">{{ u.nombre }}</span>
                                <span v-if="u.primer_acceso" class="ml-2 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                                    Cambio de contraseña pendiente
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ u.email }}</td>
                            <td class="px-4 py-3">
                                <span :class="rolBadge(u.rol)" class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">{{ u.rol }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="u.activo ? 'bg-green-50 text-green-700 ring-green-200' : 'bg-slate-100 text-slate-500 ring-slate-200'"
                                      class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    <span :class="u.activo ? 'bg-green-500' : 'bg-slate-400'" class="h-1.5 w-1.5 rounded-full"></span>
                                    {{ u.activo ? 'Activo' : 'Inactivo' }}
                                </span>

                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/usuarios/${u.id}/edit`"
                                          class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-[#2E75B6] hover:border-[#2E75B6] hover:bg-slate-50">Editar</Link>
                                    <button @click="resetear(u)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-amber-700 hover:border-amber-300 hover:bg-amber-50">Resetear clave</button>
                                    <button @click="cambiarEstado(u)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">{{ u.activo ? 'Inactivar' : 'Activar' }}</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!usuarios.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">No se encontraron usuarios.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="usuarios.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">Mostrando {{ usuarios.from }}–{{ usuarios.to }} de {{ usuarios.total }}</p>
                <nav v-if="usuarios.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, i) in usuarios.links" :key="i">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-100'"
                              class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm" v-html="link.label" />
                        <span v-else class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm text-slate-300" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
