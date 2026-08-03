<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const usuario = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash?.status ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
// Errores de validación compartidos por Inertia (se limpian al navegar con éxito).
const erroresValidacion = computed(() => Object.values(page.props.errors ?? {}).filter(Boolean));
const esAdmin = computed(() => usuario.value?.rol === 'Superusuario');
const urlActual = computed(() => page.url);

// RBAC: ¿el usuario tiene el permiso? ('*' = Superusuario, todos).
const permisos = computed(() => usuario.value?.permisos ?? []);
const puede = (clave) => permisos.value.includes('*') || permisos.value.includes(clave);

// Íconos (heroicons outline).
const ICON = {
    home: 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    mallas: 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25',
    building: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    swap: 'M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5',
    beaker: 'M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5',
    check: 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
    chart: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
    users: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    estructura: 'M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z',
    postulante: 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z',
    menu: 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
    cog: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
};

const nav = computed(() => {
    const items = [{ label: 'Inicio', href: '/', icon: ICON.home }];
    // Estructura Institucional (Superusuario) contiene las Mallas; el resto ve Mallas si puede.
    if (puede('estructura.gestionar')) items.push({ label: 'Estructura Institucional', href: '/estructura', icon: ICON.estructura });
    else if (puede('catalogos.gestionar')) items.push({ label: 'Mallas Curriculares', href: '/mallas', icon: ICON.mallas });

    if (puede('catalogos.gestionar')) items.push({ label: 'Instituciones Externas', href: '/instituciones', icon: ICON.building });
    if (puede('mallas_externas.gestionar')) items.push({ label: 'Mallas Externas', href: '/equivalencias', icon: ICON.swap });
    if (puede('solicitudes.ver')) items.push({ label: 'Postulantes', href: '/postulantes', icon: ICON.postulante });
    if (puede('evaluacion.ver')) items.push({ label: 'Simulaciones', href: '/simulaciones', icon: ICON.beaker });
    if (puede('convalidacion.ver')) items.push({ label: 'Convalidaciones', href: '/convalidaciones', icon: ICON.check });
    if (puede('reportes.ver')) items.push({ label: 'Reportes', href: '/reportes', icon: ICON.chart });
    if (puede('usuarios.gestionar')) items.push({ label: 'Usuarios', href: '/usuarios', icon: ICON.users });
    if (puede('configuracion.gestionar')) items.push({ label: 'Configuración', href: '/configuracion', icon: ICON.cog });
    return items;
});

const activo = (href) => (href === '/' ? urlActual.value === '/' : urlActual.value.startsWith(href));

const iniciales = computed(() => {
    const n = usuario.value?.nombre ?? '';
    const parts = n.split(' ').filter(Boolean).slice(0, 2).map((s) => s[0]);
    return parts.join('').toUpperCase() || 'U';
});

const menuMovil = ref(false);
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Barra lateral -->
        <aside :class="menuMovil ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-40 flex w-64 transform flex-col bg-[#1F3864] text-white transition-transform duration-200 md:translate-x-0">
            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
                <div class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/15 text-sm font-bold">U</div>
                <div class="leading-tight">
                    <span class="block text-sm font-semibold">Convalidaciones</span>
                    <span class="block text-xs text-blue-200">USIL · Gestión Académica</span>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                <Link v-for="item in nav" :key="item.href" :href="item.href" @click="menuMovil = false"
                      :class="activo(item.href) ? 'bg-white/15 text-white' : 'text-blue-100 hover:bg-white/10'"
                      class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6"
                         stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="item.icon" />
                    </svg>
                    {{ item.label }}
                </Link>
            </nav>

            <div class="border-t border-white/10 p-3 text-xs text-blue-200">
                Sistema de Convalidaciones · USIL
            </div>
        </aside>

        <!-- Overlay para móvil -->
        <div v-if="menuMovil" @click="menuMovil = false" class="fixed inset-0 z-30 bg-black/40 md:hidden"></div>

        <!-- Contenido -->
        <div class="md:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
                <button @click="menuMovil = true"
                        class="rounded-md p-2 text-slate-500 hover:bg-slate-100 md:hidden" aria-label="Abrir menú">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="ICON.menu" />
                    </svg>
                </button>

                <div class="ml-auto flex items-center gap-3">
                    <div class="hidden text-right leading-tight sm:block">
                        <span class="block text-sm font-medium text-slate-700">{{ usuario?.nombre }}</span>
                        <span class="block text-xs text-slate-400">{{ usuario?.rol }}</span>
                    </div>
                    <div class="grid h-9 w-9 place-items-center rounded-full bg-[#1F3864] text-sm font-semibold text-white">
                        {{ iniciales }}
                    </div>
                    <button @click="logout"
                            class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Cerrar sesión
                    </button>
                </div>
            </header>

            <main class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
                <div v-if="flash"
                     class="mb-6 flex items-start gap-2 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <span aria-hidden="true">✓</span><span>{{ flash }}</span>
                </div>

                <!-- Retroalimentación de errores (validación o mensaje del servidor) -->
                <div v-if="flashError || erroresValidacion.length"
                     class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="flex items-start gap-2 font-medium">
                        <span aria-hidden="true">⚠️</span>
                        <span>{{ flashError || `Revisa la información: ${erroresValidacion.length} campo(s) requieren corrección.` }}</span>
                    </p>
                    <ul v-if="erroresValidacion.length" class="mt-1.5 list-disc space-y-0.5 pl-8">
                        <li v-for="(e, i) in erroresValidacion" :key="i">{{ e }}</li>
                    </ul>
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
