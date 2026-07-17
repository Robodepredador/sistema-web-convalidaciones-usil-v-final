<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({ password: '', password_confirmation: '' });
const enviar = () => form.post('/portal/password/cambiar');
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-[#1F3864] to-[#2E75B6] px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
            <h1 class="text-lg font-semibold text-[#1F3864]">Cambia tu contraseña</h1>
            <p class="mb-6 mt-1 text-sm text-slate-500">Por seguridad, define una nueva contraseña antes de ver el estado de tu trámite.</p>

            <form @submit.prevent="enviar" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nueva contraseña</label>
                    <input v-model="form.password" type="password" autocomplete="new-password"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Confirmar contraseña</label>
                    <input v-model="form.password_confirmation" type="password" autocomplete="new-password"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>
                <p class="text-xs text-slate-500">Mínimo 8 caracteres, con mayúsculas, minúsculas, números y símbolos.</p>
                <button type="submit" :disabled="form.processing"
                        class="w-full rounded-lg bg-[#1F3864] py-2.5 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    Guardar contraseña
                </button>
            </form>
        </div>
    </div>
</template>
