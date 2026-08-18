<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import logoImg from '../../../images/usil_logo.jpg';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const mostrar = ref(false);
const enviar = () => form.post('/password/restablecer', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
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

            <h1 class="text-xl font-bold text-slate-800 leading-snug">Crea una nueva contraseña</h1>
            <p class="mb-6 mt-1.5 text-xs leading-relaxed text-slate-500 font-medium">
                Debe tener al menos 8 caracteres, incluyendo letras y números.
            </p>

            <form @submit.prevent="enviar" class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Correo Institucional</label>
                    <input v-model="form.email" type="email" autocomplete="username" readonly
                           class="w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-100/70 py-2.5 px-3.5 text-xs text-slate-500 font-medium" />
                    <p v-if="form.errors.email" class="mt-1 text-xs font-bold text-rose-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">Nueva Contraseña</label>
                    <div class="relative">
                        <input v-model="form.password" :type="mostrar ? 'text' : 'password'"
                               autocomplete="new-password" placeholder="••••••••"
                               required
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
                    <input v-model="form.password_confirmation" :type="mostrar ? 'text' : 'password'"
                           autocomplete="new-password" placeholder="••••••••"
                           required
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 px-3.5 text-xs text-slate-800 placeholder-slate-400 focus:bg-white focus:border-[#0036DC] focus:ring-3 focus:ring-[#0036DC]/20 transition-all" />
                </div>

                <button type="submit" :disabled="form.processing"
                        class="w-full rounded-xl bg-[#00205B] hover:bg-[#0036DC] py-3 text-xs font-bold text-white shadow-md hover:shadow-lg transition-all disabled:opacity-60 cursor-pointer">
                    <span>{{ form.processing ? 'Guardando…' : 'Restablecer Contraseña' }}</span>
                </button>
            </form>

            <Link href="/login" class="mt-6 flex items-center justify-center gap-1 text-xs font-bold text-[#0036DC] hover:text-[#00205B] hover:underline">
                ← Volver a iniciar sesión
            </Link>
        </div>
    </div>
</template>
