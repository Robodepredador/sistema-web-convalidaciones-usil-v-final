<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import frontisImg from '../../../images/usil_frontis.jpg';
import logoImg from '../../../images/usil_logo.jpg';

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
    <div class="flex min-h-screen bg-white font-sans selection:bg-blue-100 selection:text-blue-900">
        <!-- Panel lateral izquierdo: Imagen institucional USIL Frontis -->
        <div class="relative hidden lg:flex lg:w-1/2 xl:w-[48%] min-h-screen flex-col justify-between overflow-hidden bg-[#00195A]">
            <!-- Foto del Campus USIL con encuadre óptimo para ver el edificio y el emblema -->
            <img :src="frontisImg" alt="Campus USIL" class="absolute inset-0 h-full w-full object-cover object-[42%_center]" />
            
            <!-- Degradado corporativo elegante que realza la imagen sin ocultar el campus -->
            <div class="absolute inset-0 bg-gradient-to-t from-[#00195A]/95 via-[#00205B]/40 to-[#0B1E3F]/25"></div>

            <!-- Cabecera sobre la imagen -->
            <div class="relative z-10 p-8 xl:p-12">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/20 backdrop-blur-md border border-white/25 text-xs font-bold text-white shadow-md">
                    <span class="h-2 w-2 rounded-full bg-[#FFB81C]"></span>
                    <span>Universidad San Ignacio de Loyola</span>
                </div>
            </div>

            <!-- Pie sobre la imagen -->
            <div class="relative z-10 p-8 xl:p-12 space-y-3">
                <h2 class="text-2xl xl:text-3xl font-black leading-tight text-white drop-shadow-sm">
                    Excelencia Académica y Gestión de Convalidaciones
                </h2>
                <p class="text-xs sm:text-sm leading-relaxed text-blue-100/95 font-medium max-w-lg">
                    Plataforma oficial para la homologación de mallas, dictamen de equivalencias y seguimiento de postulantes.
                </p>
                <div class="pt-2 flex items-center gap-2">
                    <span class="h-1.5 w-8 rounded-full bg-[#FFB81C]"></span>
                    <span class="h-1.5 w-2 rounded-full bg-white/40"></span>
                    <span class="h-1.5 w-2 rounded-full bg-white/40"></span>
                </div>
            </div>
        </div>

        <!-- Panel derecho: Formulario de inicio de sesión -->
        <div class="flex flex-1 flex-col justify-between bg-[#F4F6F9] lg:bg-white overflow-y-auto">
            <div class="flex flex-1 items-center justify-center p-6 sm:p-10 lg:p-12">
                <div class="w-full max-w-[420px] rounded-3xl bg-white p-7 sm:p-9 shadow-lg lg:shadow-none border border-slate-200/80 lg:border-none my-auto">
                    <!-- Logo institucional oficial + Título -->
                    <div class="mb-6 flex items-center gap-3.5">
                        <img :src="logoImg" alt="USIL" class="h-12 w-auto object-contain rounded-2xl p-1 bg-white shadow-2xs border border-slate-100" />
                        <div>
                            <span class="block text-lg font-black text-[#00205B] tracking-tight leading-tight">USIL Convalidaciones</span>
                            <span class="block text-xs text-slate-500 font-semibold">Sistema de Gestión Académica</span>
                        </div>
                    </div>

                    <h1 class="text-xl font-bold text-slate-800 leading-snug">Bienvenido al Simulador</h1>
                    <p class="mb-6 mt-1 text-xs leading-relaxed text-slate-500 font-medium">
                        Ingresa tus credenciales para acceder a la gestión de convalidaciones.
                    </p>

                    <form @submit.prevent="enviar" class="space-y-4">
                        <!-- Campo Correo -->
                        <div>
                            <label for="email" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Correo Institucional</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75A2.25 2.25 0 014.5 4.5h15a2.25 2.25 0 012.25 2.25v10.5A2.25 2.25 0 0119.5 19.5h-15a2.25 2.25 0 01-2.25-2.25V6.75z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9 6 9-6" />
                                    </svg>
                                </span>
                                <input id="email" v-model="form.email" type="email" autocomplete="username"
                                       placeholder="ejemplo@usil.edu.pe"
                                       required
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pl-10 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-blue-100 transition-all" />
                            </div>
                            <p v-if="form.errors.email" class="mt-1 text-xs font-bold text-rose-600">{{ form.errors.email }}</p>
                        </div>

                        <!-- Campo Contraseña -->
                        <div>
                            <label for="password" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Contraseña</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.5a4.5 4.5 0 00-9 0v3M6.75 10.5h10.5a1.5 1.5 0 011.5 1.5v6a1.5 1.5 0 01-1.5 1.5H6.75a1.5 1.5 0 01-1.5-1.5v-6a1.5 1.5 0 011.5-1.5z" />
                                    </svg>
                                </span>
                                <input id="password" v-model="form.password" :type="mostrarPassword ? 'text' : 'password'"
                                       autocomplete="current-password" placeholder="••••••••"
                                       required
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pl-10 pr-12 text-xs text-slate-800 placeholder-slate-400 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-blue-100 transition-all" />
                                <button type="button" @click="mostrarPassword = !mostrarPassword"
                                        :aria-label="mostrarPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs font-semibold text-slate-400 hover:text-slate-700">
                                    {{ mostrarPassword ? 'Ocultar' : 'Ver' }}
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1 text-xs font-bold text-rose-600">{{ form.errors.password }}</p>
                        </div>

                        <!-- Recordarme + Olvidaste -->
                        <div class="flex items-center justify-between text-xs pt-0.5">
                            <label class="flex items-center gap-2 text-slate-600 font-medium cursor-pointer">
                                <input v-model="form.remember" type="checkbox" class="rounded-md border-slate-300 text-[#00205B] focus:ring-[#00205B]" />
                                Recordarme
                            </label>
                            <a href="/password/olvide" class="font-bold text-[#0036DC] hover:text-[#00195A] hover:underline">¿Olvidaste tu contraseña?</a>
                        </div>

                        <!-- Botón Iniciar Sesión -->
                        <button type="submit" :disabled="form.processing"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#00195A] active:bg-[#0B1E3F] py-3 text-xs font-bold text-white shadow-md hover:shadow-lg transition-all disabled:opacity-60 cursor-pointer">
                            <span>{{ form.processing ? 'Ingresando…' : 'Iniciar Sesión' }}</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                    </form>

                    <!-- Divisor -->
                    <div class="my-5 flex items-center gap-3">
                        <span class="h-px flex-1 bg-slate-200"></span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">o</span>
                        <span class="h-px flex-1 bg-slate-200"></span>
                    </div>

                    <!-- Enlace Portal Postulante -->
                    <a href="/portal/login"
                       class="flex w-full items-center justify-center gap-2 rounded-xl border border-[#0036DC]/30 bg-[#0036DC]/5 hover:bg-[#0036DC]/10 py-2.5 text-xs font-bold text-[#00205B] transition-colors shadow-2xs">
                        <svg class="h-4 w-4 text-[#0036DC]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 19.5a7.5 7.5 0 0 1 15 0v.75H4.5v-.75Z" />
                        </svg>
                        <span>¿Eres postulante? Seguimiento de tu solicitud →</span>
                    </a>

                    <!-- Accesos rápidos Demo (solo entorno local) -->
                    <div v-if="usuariosDemo.length" class="mt-6 border-t border-dashed border-slate-200 pt-4">
                        <p class="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                            Acceso Rápido por Perfil (Demo)
                        </p>
                        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                            <button v-for="u in usuariosDemo" :key="u.email" type="button"
                                    @click="usar(u)" :disabled="form.processing"
                                    class="flex flex-col rounded-xl border border-slate-200 bg-slate-50/80 p-2 text-left hover:border-[#0036DC] hover:bg-white transition-all disabled:opacity-60 cursor-pointer shadow-2xs">
                                <span class="text-xs font-bold text-slate-800 truncate">{{ u.label }}</span>
                                <span class="truncate text-[10px] text-slate-400 font-mono">{{ u.email }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer institucional -->
            <footer class="border-t border-slate-200/80 bg-white px-6 py-4 text-center text-xs text-slate-400 shrink-0">
                <span class="font-bold text-slate-600">USIL Convalidaciones</span>
                <span class="mx-2">·</span>
                <span>© {{ anio }} Universidad San Ignacio de Loyola. Todos los derechos reservados.</span>
            </footer>
        </div>
    </div>
</template>
