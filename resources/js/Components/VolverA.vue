<script setup>
import { Link } from '@inertiajs/vue3';

// Retroceso de una vista de detalle/formulario a su listado padre.
//
// Va siempre en el mismo sitio —primera línea de la página, sobre el <h1>— porque el
// valor de un control de navegación está en que se encuentre sin buscarlo. Antes cada
// vista lo resolvía a su manera: unas como texto pequeño arriba, otras como enlace
// suelto al pie y la mayoría no lo tenía.
//
// Nombra el destino en vez de decir «Volver»: el usuario puede haber llegado desde un
// enlace directo y no recordar de dónde venía. Decir a dónde lleva no exige recordar.
defineProps({
    href: { type: String, required: true },
    texto: { type: String, required: true },
});
</script>

<template>
    <!-- -ml-2 compensa el padding para que el texto quede alineado con el <h1> de debajo,
         sin renunciar a un área de clic cómoda. -->
    <Link :href="href"
          class="group -ml-2 mb-1.5 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-[#00205B] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0036DC]">
        <svg class="h-4 w-4 shrink-0 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        {{ texto }}
    </Link>
</template>
