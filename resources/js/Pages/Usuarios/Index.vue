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
</script><template>
    <div class="w-full pb-16">
        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1E3F] via-[#00205B] to-[#012085] shadow-xl text-white">
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#0036DC] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Seguridad y Accesos</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Gestión de Usuarios y Roles
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                            Administra las cuentas de usuario institucionales, asignación de roles, facultades y alcance académico en el sistema.
                        </p>
                    </div>

                    <!-- Botón Crear -->
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <Link href="/usuarios/create"
                              class="inline-flex items-center gap-2 rounded-2xl bg-white hover:bg-slate-50 px-5 py-3 text-xs font-bold text-[#00205B] shadow-md transition-all duration-300 hover:shadow-lg hover:scale-[1.02]">
                            <svg class="h-4 w-4 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Nuevo usuario</span>
                        </Link>
                    </div>
                </div>

                <!-- KPIs en Header -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-6">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-200 uppercase tracking-wider">Total Cuentas</div>
                        <div class="text-2xl font-extrabold text-white mt-1">{{ usuarios.total || 0 }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Usuarios registrados</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10">
                        <div class="text-[11px] font-semibold text-blue-200 uppercase tracking-wider">Usuarios Activos</div>
                        <div class="text-2xl font-extrabold text-emerald-300 mt-1">{{ activos }}</div>
                        <div class="text-[10px] text-blue-200/80 mt-0.5">Con acceso vigente</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= FILTROS ======================= -->
        <div class="mb-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Buscar usuario</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                        <input v-model="filtro.q" type="text" placeholder="Nombre o correo institucional…" @keyup.enter="aplicar"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-10 pr-4 py-2.5 text-xs text-slate-800 focus:border-[#0036DC] focus:bg-white focus:outline-hidden focus:ring-3 focus:ring-[#0036DC]/20 transition-all duration-200" />
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Rol asignado</label>
                    <select v-model="filtro.rol_id" @change="aplicar"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-800 focus:border-[#0036DC] focus:bg-white focus:outline-hidden focus:ring-3 focus:ring-[#0036DC]/20 transition-all duration-200">
                        <option value="">Todos los roles</option>
                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nombre }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Estado operativo</label>
                    <select v-model="filtro.estado" @change="aplicar"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs text-slate-800 focus:border-[#0036DC] focus:bg-white focus:outline-hidden focus:ring-3 focus:ring-[#0036DC]/20 transition-all duration-200">
                        <option value="">Todos los estados</option>
                        <option value="activo">Solo Activos</option>
                        <option value="inactivo">Solo Inactivos</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                <div class="flex items-center gap-2">
                    <button @click="aplicar" class="inline-flex items-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#0036DC] px-4 py-2 text-xs font-bold text-white shadow-xs transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        Filtrar usuarios
                    </button>
                    <button v-if="hayFiltrosActivos" @click="limpiar" class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 transition-colors">
                        Limpiar filtros
                    </button>
                </div>
                <span class="text-xs text-slate-400 font-medium">Mostrando {{ usuarios.data.length }} de {{ usuarios.total }} registros</span>
            </div>
        </div>

        <!-- ======================= TABLA DE USUARIOS ======================= -->
        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50/80 text-left uppercase tracking-wider text-slate-500 font-bold">
                        <tr>
                            <th class="px-5 py-4">Nombre Completo</th>
                            <th class="px-5 py-4">Correo Institucional</th>
                            <th class="px-5 py-4">Rol Asignado</th>
                            <th class="px-5 py-4 text-center">Estado</th>
                            <th class="px-5 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="u in usuarios.data" :key="u.id" class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="grid h-8 w-8 place-items-center rounded-xl bg-slate-100 text-xs font-bold text-[#00205B]">
                                        {{ u.nombre ? u.nombre.charAt(0).toUpperCase() : 'U' }}
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-900 text-sm block">{{ u.nombre }}</span>
                                        <span v-if="u.primer_acceso" class="inline-block mt-0.5 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 ring-1 ring-amber-200">
                                            Clave provisional pendiente
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 font-medium text-slate-600">{{ u.email }}</td>
                            <td class="px-5 py-4">
                                <span :class="rolBadge(u.rol)" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ring-1">
                                    {{ u.rol }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span :class="u.activo ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200'"
                                      class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold">
                                    <span :class="u.activo ? 'bg-emerald-500' : 'bg-slate-400'" class="h-1.5 w-1.5 rounded-full"></span>
                                    {{ u.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/usuarios/${u.id}/edit`"
                                          class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-[#0036DC] shadow-2xs hover:border-[#0036DC] hover:bg-blue-50/50 transition-all">
                                        Editar
                                    </Link>
                                    <button @click="resetear(u)"
                                            class="rounded-xl border border-amber-200 bg-amber-50/50 px-3 py-1.5 text-xs font-bold text-amber-700 shadow-2xs hover:bg-amber-100 transition-all">
                                        Resetear clave
                                    </button>
                                    <button @click="cambiarEstado(u)"
                                            class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-2xs hover:bg-slate-50 transition-all">
                                        {{ u.activo ? 'Inactivar' : 'Activar' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!usuarios.data.length">
                            <td colspan="5" class="px-5 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="h-10 w-10 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                    </svg>
                                    <p class="text-sm font-semibold text-slate-600">No se encontraron usuarios</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Intenta modificar los filtros de búsqueda.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="usuarios.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-6 py-4 bg-slate-50/50">
                <p class="text-xs text-slate-500 font-medium">Mostrando {{ usuarios.from }}–{{ usuarios.to }} de {{ usuarios.total }} usuarios</p>
                <nav v-if="usuarios.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, i) in usuarios.links" :key="i">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#00205B] text-white shadow-xs font-bold' : 'text-slate-600 hover:bg-white bg-slate-100/70'"
                              class="min-w-[32px] rounded-lg px-2.5 py-1.5 text-center text-xs transition-colors" v-html="link.label" />
                        <span v-else class="min-w-[32px] rounded-lg px-2.5 py-1.5 text-center text-xs text-slate-300" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
