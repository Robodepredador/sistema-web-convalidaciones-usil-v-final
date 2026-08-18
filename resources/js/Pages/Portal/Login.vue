<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import logoImg from '../../../images/usil_logo.jpg';

const form = useForm({ email: '', password: '' });
const mostrar = ref(false);
const enviar = () => form.post('/portal/login');
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-[#0B1E3F] px-4 relative overflow-hidden font-sans selection:bg-blue-200 selection:text-blue-900">
        <!-- BACKGROUND AMBIENT GLOW -->
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-[#00195A]/60 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-[#00205B]/40 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative w-full max-w-md rounded-3xl bg-white p-8 sm:p-10 shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
            <!-- BRAND HEADER -->
            <div class="mb-8 text-center">
                <div class="mx-auto mb-3 flex items-center justify-center">
                    <img :src="logoImg" alt="USIL" class="h-14 w-auto object-contain rounded-xl p-1 bg-white shadow-xs border border-slate-100" />
                </div>
                <h1 class="text-xl font-black text-[#00205B] leading-tight">Portal del Postulante</h1>
                <p class="mt-1 text-xs text-slate-500 font-medium">Seguimiento oficial de tu convalidación académica</p>
            </div>

            <form @submit.prevent="enviar" class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Correo Electrónico</label>
                    <input v-model="form.email" type="email" autocomplete="username" placeholder="ejemplo@correo.com"
                           required
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-blue-100 focus:outline-hidden transition-all" />
                    <p v-if="form.errors.email" class="mt-1.5 text-xs font-bold text-rose-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Contraseña</label>
                        <span class="text-[10px] text-slate-400">Proporcionada por Admisión</span>
                    </div>
                    <div class="relative">
                        <input v-model="form.password" :type="mostrar ? 'text' : 'password'" autocomplete="current-password"
                               placeholder="Ingresa tu contraseña"
                               required
                               class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 pr-14 text-xs text-slate-800 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-blue-100 focus:outline-hidden transition-all" />
                        <button type="button" @click="mostrar = !mostrar"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs font-semibold text-slate-400 hover:text-slate-700">
                            {{ mostrar ? 'Ocultar' : 'Ver' }}
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="mt-1.5 text-xs font-bold text-rose-600">{{ form.errors.password }}</p>
                </div>

                <button type="submit" :disabled="form.processing"
                        class="w-full mt-2 inline-flex items-center justify-center gap-2 rounded-xl bg-[#00205B] hover:bg-[#00195A] active:bg-[#0B1E3F] py-3 text-xs font-bold text-white shadow-md hover:shadow-lg transition-all disabled:opacity-60 cursor-pointer">
                    <svg v-if="form.processing" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ form.processing ? 'Ingresando…' : 'Ingresar al Portal' }}</span>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-100 text-center space-y-2">
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    Si no recuerdas tus credenciales, comunícate con tu asesor de admisión para su restablecimiento.
                </p>
                <p class="text-xs">
                    <a href="/login" class="font-bold text-[#0036DC] hover:text-[#00195A] hover:underline">
                        Acceso para Personal Administrativo USIL →
                    </a>
                </p>
            </div>
        </div>
    </div>
</template>
