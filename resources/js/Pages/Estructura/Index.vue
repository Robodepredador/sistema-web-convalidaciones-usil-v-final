<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({ conteos: Object });

const ICON = {
    building: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    cap: 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
    briefcase: 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z',
    desktop: 'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25',
    stack: 'M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122',
};

const cards = [
    {
        label: 'Sedes',
        desc: 'Administra los campus y locales físicos de la institución.',
        href: '/estructura/sedes',
        key: 'sedes',
        unidad: 'sedes registradas',
        icon: ICON.building,
        gradient: 'from-blue-600 to-indigo-700',
        badgeBg: 'bg-blue-50 text-blue-700 border-blue-200',
        footBg: 'bg-blue-50/70 hover:bg-blue-100/80 text-[#1F3864]',
    },
    {
        label: 'Facultades',
        desc: 'Gestiona las divisiones académicas principales por cada sede.',
        href: '/estructura/facultades',
        key: 'facultades',
        unidad: 'facultades registradas',
        icon: ICON.cap,
        gradient: 'from-emerald-600 to-teal-700',
        badgeBg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        footBg: 'bg-emerald-50/70 hover:bg-emerald-100/80 text-emerald-800',
    },
    {
        label: 'Programas Académicos',
        desc: 'Administra las carreras profesionales y programas formativos.',
        href: '/estructura/programas',
        key: 'programas',
        unidad: 'programas registrados',
        icon: ICON.briefcase,
        gradient: 'from-violet-600 to-purple-700',
        badgeBg: 'bg-violet-50 text-violet-700 border-violet-200',
        footBg: 'bg-violet-50/70 hover:bg-violet-100/80 text-violet-800',
    },
    {
        label: 'Modalidades',
        desc: 'Gestiona las modalidades de estudio (Presencial, Semipresencial, Virtual).',
        href: '/estructura/modalidades',
        key: 'modalidades',
        unidad: 'modalidades disponibles',
        icon: ICON.desktop,
        gradient: 'from-amber-500 to-orange-600',
        badgeBg: 'bg-amber-50 text-amber-700 border-amber-200',
        footBg: 'bg-amber-50/70 hover:bg-amber-100/80 text-amber-800',
    },
    {
        label: 'Mallas Curriculares',
        desc: 'Administra los planes de asignaturas, ciclos, créditos y prerrequisitos de cada carrera.',
        href: '/mallas',
        key: 'mallas',
        unidad: 'mallas registradas',
        icon: ICON.stack,
        gradient: 'from-sky-600 to-[#2E75B6]',
        badgeBg: 'bg-sky-50 text-[#1F3864] border-sky-200',
        footBg: 'bg-sky-50/70 hover:bg-sky-100/80 text-[#1F3864]',
    },
];

const num = (n) => String(n ?? 0).padStart(2, '0');
</script>

<template>
    <div class="w-full pb-16">
        <!-- ======================= HERO HEADER BANNER USIL ======================= -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1F3864] via-[#214378] to-[#2E75B6] shadow-xl text-white">
            <!-- Decorative blur background -->
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-white opacity-5 rounded-full blur-3xl mix-blend-overlay"></div>
            <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-[#2E75B6] opacity-25 rounded-full blur-2xl"></div>

            <div class="relative z-10 p-6 sm:p-10">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 pb-6 border-b border-white/10">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 mb-3 rounded-full bg-white/10 border border-white/20 backdrop-blur-md shadow-xs">
                            <svg class="w-3.5 h-3.5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <span class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Catálogos Maestros</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                            Gestión de la Estructura Institucional
                        </h1>
                        <p class="mt-2 text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-xl">
                            Administra la jerarquía académica oficial que organiza las carreras, facultades y mallas curriculares utilizadas en las convalidaciones.
                        </p>
                    </div>

                    <!-- Botón directo a Mallas -->
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <Link href="/mallas"
                              class="inline-flex items-center gap-2 rounded-2xl bg-white hover:bg-slate-50 px-4 py-2.5 text-xs font-bold text-[#1F3864] shadow-md transition-all duration-300">
                            <svg class="h-4 w-4 text-[#2E75B6]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            <span>Ir a Mallas Curriculares</span>
                        </Link>
                    </div>
                </div>

                <!-- Jerarquía Visualizada -->
                <div class="pt-6">
                    <p class="text-[11px] font-bold tracking-wider text-blue-200 uppercase mb-3">Jerarquía Académica Integrada</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/15 px-3 py-1.5 backdrop-blur-md text-xs font-medium text-white shadow-xs">
                            <span class="grid h-5 w-5 place-items-center rounded-lg bg-white/20 text-[10px] font-bold">1</span>
                            Sedes
                        </div>
                        <span class="text-blue-300 font-bold">→</span>
                        <div class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/15 px-3 py-1.5 backdrop-blur-md text-xs font-medium text-white shadow-xs">
                            <span class="grid h-5 w-5 place-items-center rounded-lg bg-white/20 text-[10px] font-bold">2</span>
                            Facultades
                        </div>
                        <span class="text-blue-300 font-bold">→</span>
                        <div class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/15 px-3 py-1.5 backdrop-blur-md text-xs font-medium text-white shadow-xs">
                            <span class="grid h-5 w-5 place-items-center rounded-lg bg-white/20 text-[10px] font-bold">3</span>
                            Programas Académicos
                        </div>
                        <span class="text-blue-300 font-bold">→</span>
                        <div class="flex items-center gap-2 rounded-xl bg-white/10 border border-white/15 px-3 py-1.5 backdrop-blur-md text-xs font-medium text-white shadow-xs">
                            <span class="grid h-5 w-5 place-items-center rounded-lg bg-white/20 text-[10px] font-bold">4</span>
                            Modalidades
                        </div>
                        <span class="text-blue-300 font-bold">→</span>
                        <div class="flex items-center gap-2 rounded-xl bg-emerald-400/20 border border-emerald-400/30 px-3 py-1.5 backdrop-blur-md text-xs font-semibold text-emerald-100 shadow-xs">
                            <span class="grid h-5 w-5 place-items-center rounded-lg bg-emerald-500/40 text-[10px] font-bold">5</span>
                            Mallas Curriculares
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================= TARJETAS DE MÓDULOS DE ESTRUCTURA ======================= -->
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Link v-for="c in cards" :key="c.key" :href="c.href"
                  class="group relative flex flex-col justify-between overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:border-slate-300">
                <div>
                    <!-- Top Icon and Arrow -->
                    <div class="flex items-center justify-between">
                        <div :class="`bg-gradient-to-br ${c.gradient}`"
                             class="grid h-13 w-13 place-items-center rounded-2xl text-white shadow-md transition-transform duration-300 group-hover:scale-105">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                                 stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path :d="c.icon" />
                            </svg>
                        </div>
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-50 text-slate-400 border border-slate-200/60 transition-all duration-300 group-hover:bg-[#1F3864] group-hover:text-white group-hover:border-[#1F3864]">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </span>
                    </div>

                    <!-- Header and Description -->
                    <h2 class="mt-5 text-lg font-bold text-[#1F3864] tracking-tight group-hover:text-[#2E75B6] transition-colors">
                        {{ c.label }}
                    </h2>
                    <p class="mt-1.5 text-xs text-slate-500 leading-relaxed">
                        {{ c.desc }}
                    </p>

                    <!-- Metric Counter -->
                    <div class="mt-6 flex items-baseline gap-2">
                        <span class="text-4xl font-extrabold tracking-tight text-slate-900">{{ num(conteos[c.key]) }}</span>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">{{ c.unidad }}</span>
                    </div>
                </div>

                <!-- Footer Action Button -->
                <div :class="c.footBg"
                     class="mt-6 -mx-6 -mb-6 flex items-center justify-between px-6 py-3.5 border-t border-slate-100 text-xs font-bold transition-colors">
                    <span>Administrar {{ c.label.toLowerCase() }}</span>
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </div>
            </Link>
        </div>
    </div>
</template>
