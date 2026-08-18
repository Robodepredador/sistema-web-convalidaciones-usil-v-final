<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import logoImg from '../../../images/usil_logo.jpg';

const form = useForm({ password: '', password_confirmation: '' });
const mostrar = ref(false);
const enviar = () => form.post('/password/cambiar');
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

            <h1 class="text-xl font-bold text-slate-800 leading-snug">Define tu contraseña</h1>
            <p class="mb-6 mt-1.5 text-xs leading-relaxed text-slate-500 font-medium">
                Por seguridad institucional, actualiza tu contraseña provisional antes de continuar.
            </p>

            <form @submit.prevent="enviar" class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Nueva Contraseña</label>
                    <div class="relative">
                        <input v-model="form.password" :type="mostrar ? 'text' : 'password'" autocomplete="new-password"
                               placeholder="••••••••" required
                               class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pl-3.5 pr-10 text-xs text-slate-800 placeholder-slate-400 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/20 transition-all" />
                        <button type="button" @click="mostrar = !mostrar"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 text-xs font-semibold">
                            {{ mostrar ? 'Ocultar' : 'Ver' }}
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="mt-1 text-xs font-bold text-rose-600">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Confirmar Contraseña</label>
                    <input v-model="form.password_confirmation" :type="mostrar ? 'text' : 'password'" autocomplete="new-password"
                           placeholder="••••••••" required
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 px-3.5 text-xs text-slate-800 placeholder-slate-400 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/20 transition-all" />
                </div>

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-[11px] text-slate-500 leading-relaxed">
                    🔒 Debe tener al menos 8 caracteres con letras y números.
                </div>

                <button type="submit" :disabled="form.processing"
                        class="w-full rounded-xl bg-[#00205B] hover:bg-[#0036DC] py-3 text-xs font-bold text-white shadow-md hover:shadow-lg transition-all disabled:opacity-60 cursor-pointer">
                    <span>{{ form.processing ? 'Guardando…' : 'Guardar Contraseña y Continuar' }}</span>
                </button>
            </form>
        </div>
    </div>
</template>
