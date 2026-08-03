<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

defineProps({
    usuariosDemo: { type: Array, default: () => [] },
});

const form = useForm({ email: '', password: '', remember: false });
const mostrarPassword = ref(false);
const enviar = () => form.post('/login');
const anio = new Date().getFullYear();

// Accesos rápidos de prueba: completa las credenciales y entra.
const usar = (u) => {
    form.email = u.email;
    form.password = u.password;
    form.post('/login');
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white lg:flex-row">
        <!-- Panel izquierdo: imagen institucional -->
        <div class="relative hidden min-h-[16rem] overflow-hidden lg:flex lg:w-1/2">
            <div
                class="absolute inset-0 bg-cover bg-center"
                style="background-image: linear-gradient(135deg, #1F3864, #2E75B6)"
            ></div>

            <!-- Marca de agua USIL -->
            <span class="pointer-events-none absolute right-10 top-1/2 -translate-y-1/2 select-none text-[9rem] font-black leading-none text-white/10">
                USIL
            </span>

            <!-- Texto sobre la imagen -->
            <div class="relative z-10 flex w-full flex-col justify-end p-10 xl:p-14">
                <h2 class="max-w-md text-4xl font-bold leading-tight text-white xl:text-5xl">
                    Excelencia Académica y Transformación Digital
                </h2>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-white/80">
                    Formando líderes globales con tecnología de vanguardia y valores institucionales sólidos.
                </p>
                <div class="mt-6 flex gap-2">
                    <span class="h-1.5 w-8 rounded-full bg-white"></span>
                    <span class="h-1.5 w-2 rounded-full bg-white/40"></span>
                    <span class="h-1.5 w-2 rounded-full bg-white/40"></span>
                </div>
            </div>
        </div>

        <!-- Panel derecho: formulario -->
        <div class="flex w-full flex-col lg:w-1/2">
            <div class="flex flex-1 items-center justify-center px-6 py-12 sm:px-12">
                <div class="w-full max-w-md">
                    <!-- Logo + marca -->
                    <div class="mb-8 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-md bg-[#1F3864] text-[11px] font-bold leading-none text-white">
                            USIL
                        </div>
                        <span class="text-xl font-semibold text-[#1F3864]">USIL Convalidaciones</span>
                    </div>

                    <h1 class="text-2xl font-bold text-slate-800">Bienvenido al Simulador de Convalidaciones</h1>
                    <p class="mb-8 mt-2 text-sm leading-relaxed text-slate-500">
                        Ingresa tus credenciales para acceder a la gestión inteligente de convalidaciones.
                    </p>

                    <form @submit.prevent="enviar" class="space-y-5">
                        <!-- Correo -->
                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Correo Institucional</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75A2.25 2.25 0 014.5 4.5h15a2.25 2.25 0 012.25 2.25v10.5A2.25 2.25 0 0119.5 19.5h-15a2.25 2.25 0 01-2.25-2.25V6.75z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9 6 9-6" />
                                    </svg>
                                </span>
                                <input id="email" v-model="form.email" type="email" autocomplete="username"
                                       placeholder="ejemplo@usil.edu.pe"
                                       class="w-full rounded-lg border-slate-300 py-2.5 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                            </div>
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                        </div>

                        <!-- Contraseña -->
                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Contraseña</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.5a4.5 4.5 0 00-9 0v3M6.75 10.5h10.5a1.5 1.5 0 011.5 1.5v6a1.5 1.5 0 01-1.5 1.5H6.75a1.5 1.5 0 01-1.5-1.5v-6a1.5 1.5 0 011.5-1.5z" />
                                    </svg>
                                </span>
                                <input id="password" v-model="form.password" :type="mostrarPassword ? 'text' : 'password'"
                                       autocomplete="current-password" placeholder="••••••••"
                                       class="w-full rounded-lg border-slate-300 py-2.5 pl-10 pr-10 text-sm text-slate-700 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                                <button type="button" @click="mostrarPassword = !mostrarPassword"
                                        :aria-label="mostrarPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                                    <svg v-if="!mostrarPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.5 10.5 0 002.25 12s3.75 7.5 9.75 7.5a9.7 9.7 0 005.02-1.38M6.7 6.7A9.7 9.7 0 0112 4.5c6 0 9.75 7.5 9.75 7.5a18.5 18.5 0 01-2.16 3.19M3 3l18 18" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                        </div>

                        <!-- Recordarme + olvidaste -->
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-[#2E75B6] focus:ring-[#2E75B6]" />
                                Recordarme
                            </label>
                            <a href="/password/olvide" class="text-sm font-medium text-[#2E75B6] hover:underline">¿Olvidaste tu contraseña?</a>
                        </div>

                        <!-- Botón -->
                        <button type="submit" :disabled="form.processing"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#1F3864] py-3 text-sm font-semibold text-white transition hover:bg-[#2E75B6] disabled:opacity-60">
                            Iniciar Sesión
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                    </form>

                    <!-- Divisor -->
                    <div class="my-6 flex items-center gap-3">
                        <span class="h-px flex-1 bg-slate-200"></span>
                        <span class="text-xs text-slate-400">o</span>
                        <span class="h-px flex-1 bg-slate-200"></span>
                    </div>

                    <!-- Portal del postulante -->
                    <a href="/portal/login"
                       class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border border-[#2E75B6]/40 bg-[#2E75B6]/5 py-3 text-sm font-medium text-[#1F3864] transition hover:bg-[#2E75B6]/10">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 19.5a7.5 7.5 0 0 1 15 0v.75H4.5v-.75Z" />
                        </svg>
                        ¿Eres postulante? Seguimiento de tu solicitud →
                    </a>

                    <!-- Accesos rápidos por perfil (solo entorno local) -->
                    <div v-if="usuariosDemo.length" class="mt-6 border-t border-dashed border-slate-200 pt-4">
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">
                            Acceso rápido por perfil
                        </p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <button v-for="u in usuariosDemo" :key="u.email" type="button"
                                    @click="usar(u)" :disabled="form.processing"
                                    class="flex flex-col rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-left hover:border-[#2E75B6] hover:bg-white disabled:opacity-60">
                                <span class="text-sm font-medium text-slate-700">{{ u.label }}</span>
                                <span class="truncate text-xs text-slate-400">{{ u.email }}</span>
                            </button>
                        </div>
                        <p class="mt-2 text-center text-xs text-slate-400">Contraseña: <span class="font-mono">Demo#1234</span></p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="border-t border-slate-100 px-6 py-5 sm:px-12">
                <div class="flex flex-col items-center justify-between gap-2 text-xs text-slate-400 sm:flex-row">
                    <div>
                        <span class="font-semibold text-slate-500">USIL Convalidaciones</span>
                        <span class="ml-3">© {{ anio }} Universidad San Ignacio de Loyola. Todos los derechos reservados.</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>
