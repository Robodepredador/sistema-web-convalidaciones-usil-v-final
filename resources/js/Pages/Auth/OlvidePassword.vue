<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import logoImg from '../../../images/usil_logo.jpg';

const page = usePage();
const status = computed(() => page.props.flash?.status);
const resetUrl = computed(() => page.props.flash?.reset_url);

const form = useForm({ email: '' });
const enviar = () => form.post('/password/olvide', { preserveScroll: true });
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-[#F4F6F9] px-4 font-sans selection:bg-blue-100 selection:text-blue-900">
        <div class="w-full max-w-md rounded-3xl bg-white p-8 sm:p-10 shadow-sm border border-slate-200/80">
            <!-- Logo + marca -->
            <div class="mb-6 flex items-center gap-3">
                <img :src="logoImg" alt="USIL" class="h-11 w-auto object-contain rounded-xl shadow-2xs border border-slate-100" />
                <div>
                    <span class="block text-base font-black text-[#00205B] leading-tight">USIL Convalidaciones</span>
                    <span class="block text-[11px] text-slate-500 font-semibold">Sistema de Gestión Académica</span>
                </div>
            </div>

            <h1 class="text-xl font-bold text-slate-800 leading-snug">¿Olvidaste tu contraseña?</h1>
            <p class="mb-6 mt-1.5 text-xs leading-relaxed text-slate-500 font-medium">
                Ingresa tu correo institucional y te enviaremos un enlace para crear una nueva contraseña.
            </p>

            <!-- Mensaje de confirmación -->
            <div v-if="status" class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800 border border-emerald-200">
                {{ status }}
            </div>

            <!-- Enlace de prueba (solo entorno local) -->
            <div v-if="resetUrl" class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-900 border border-amber-200">
                <p class="font-bold">Entorno local — sin servidor de correo</p>
                <p class="mt-0.5">Usa este enlace para restablecer tu contraseña:</p>
                <Link :href="resetUrl" class="mt-1 block break-all font-bold text-[#0036DC] hover:underline">
                    {{ resetUrl }}
                </Link>
            </div>

            <form @submit.prevent="enviar" class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Correo Institucional</label>
                    <input v-model="form.email" type="email" autocomplete="username"
                           placeholder="ejemplo@usil.edu.pe"
                           required
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 px-3.5 text-xs text-slate-800 placeholder-slate-400 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-blue-100 transition-all" />
                    <p v-if="form.errors.email" class="mt-1 text-xs font-bold text-rose-600">{{ form.errors.email }}</p>
                </div>

                <button type="submit" :disabled="form.processing"
                        class="w-full rounded-xl bg-[#00205B] hover:bg-[#00195A] active:bg-[#0B1E3F] py-3 text-xs font-bold text-white shadow-md hover:shadow-lg transition-all disabled:opacity-60 cursor-pointer">
                    <span>{{ form.processing ? 'Enviando…' : 'Enviar Enlace de Recuperación' }}</span>
                </button>
            </form>

            <Link href="/login" class="mt-6 flex items-center justify-center gap-1 text-xs font-bold text-[#0036DC] hover:text-[#00195A] hover:underline">
                ← Volver a iniciar sesión
            </Link>
        </div>
    </div>
</template>
