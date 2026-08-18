# Paquete de entrega — Sistema de Convalidaciones USIL

Convalidación de cursos por traslado externo. Laravel 11 + Vue 3 (Inertia) sobre MySQL 8.

## Por dónde empezar

1. **`deploy/RUNBOOK.md`** — instalación, actualización y reversión. Empieza por su §1: hay un requisito (PHP 8.2) que, si no se cumple, detiene todo lo demás.
2. **`docs/Auditoria-Entrega-TI-2026-08-18.md`** — estado técnico y de seguridad al cierre, con lo corregido y lo que queda abierto. La [auditoría del 10/08](docs/Auditoria-Entrega-TI-2026-08-10.md) queda como antecedente.

## Qué necesita el servidor

Apache 2.4 · PHP 8.2 · MySQL 8. **Nada más**: no hacen falta Docker, Redis, Node ni Composer — el paquete lleva `vendor/` instalado y `public/build` compilado.

## Contenido

```
app/  database/  resources/  routes/  config/   Código de la aplicación
public/                                         Raíz web (único directorio publicado)
vendor/                                         Dependencias PHP ya instaladas
public/build/                                   Frontend ya compilado
deploy/RUNBOOK.md                               Procedimiento de despliegue
deploy/.env.production.example                  Plantilla de variables
docs/                                           Documentación del proyecto
```

> El paquete **no lleva** `tests/`, `.github/`, `.env` ni `backups/`: los retira
> `deploy/empaquetar.sh`, que además aborta si alguno se cuela. Las 168 pruebas y el pipeline
> de CI viven en el repositorio Git, que se entrega por separado.

## Tres cosas que conviene saber de entrada

- **El correo es obligatorio.** Es el único canal por el que se entregan las contraseñas. Sin SMTP el sistema avisa en pantalla, pero nadie puede iniciar sesión. Hay una salida manual para arrancar (`php artisan usuario:password`), descrita en el runbook §8.
- **El motor de IA se entrega apagado y no hay ninguna clave que conseguir.** El sistema no llama a ningún proveedor externo ni envía datos fuera: las equivalencias las declara de antemano el Especialista y el Administrativo escoge dentro de esa lista. Encenderlo exigiría volver a cablear el módulo y autorización escrita del área legal (Ley N.° 29733).
- **El sistema no emite el memorándum oficial.** Produce la preconvalidación; el acto oficial se gestiona fuera, por decisión del área usuaria.

## Estado

El alcance se ajustó durante agosto de 2026: salieron el memorándum oficial, los reportes, las mallas externas y el motor de IA (el detalle está en el README). Lo que se entrega está construido y **las 168 pruebas pasan**. Pendiente: UAT y pase a producción.

Los entregables documentales A1, A2, A4 y A6 y los diagramas D-03, D-04 y D-06 **no forman parte de esta entrega** (ver `docs/expediente-pase-produccion/INDICE.md`).
