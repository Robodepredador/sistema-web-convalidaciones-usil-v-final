<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ mapeos: Array });
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">Equivalencias por malla</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">
                    El criterio que declaras entre la malla de una institución de origen y un plan de estudios USIL.
                    Aparece como primera sugerencia al evaluar, junto al histórico de lo ya convalidado.
                </p>
            </div>
            <Link href="/mapeo-mallas/crear"
                  class="rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864]">
                Nuevo mapeo
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Institución de origen</th>
                            <th class="px-4 py-3 font-semibold">Carrera USIL destino</th>
                            <th class="px-4 py-3 text-center font-semibold whitespace-nowrap">Equivalencias</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(m, i) in mapeos" :key="i" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800">{{ m.institucion }}</div>
                                <div class="text-xs text-slate-400">
                                    {{ m.carrera_externa }} · malla {{ m.anio_externa }}
                                    <span v-if="!m.malla_externa_vigente"
                                          class="ml-1 rounded-full bg-amber-50 px-1.5 py-0.5 font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                                        plan anterior
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-slate-700">{{ m.carrera_usil }}</div>
                                <div class="text-xs text-slate-400">Plan {{ m.plan_usil }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-[#1F3864] ring-1 ring-inset ring-blue-100">
                                    {{ m.equivalencias }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/mapeo-mallas/crear?malla_externa_id=${m.malla_externa_id}&carrera_usil_id=${m.carrera_usil_id}`"
                                      class="text-sm font-medium text-[#2E75B6] hover:underline">Continuar</Link>
                            </td>
                        </tr>
                        <tr v-if="!mapeos.length">
                            <td colspan="4" class="px-4 py-12 text-center">
                                <p class="text-slate-500">Todavía no hay equivalencias declaradas.</p>
                                <p class="mx-auto mt-2 max-w-lg text-xs text-slate-400">
                                    Empieza por una institución de la que recibas traslados con frecuencia.
                                    No hace falta cubrir toda la malla: lo que declares ya ayuda al evaluar.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
