# Paquete de entrega — Sistema de Convalidaciones USIL

Convalidación de cursos por traslado externo. Laravel 11 + Vue 3 (Inertia) sobre MySQL 8.

## Por dónde empezar

1. **`deploy/RUNBOOK.md`** — instalación, actualización y reversión. Empieza por su §1: hay un requisito (PHP 8.2) que, si no se cumple, detiene todo lo demás.
2. **`docs/Auditoria-Entrega-TI-2026-08-10.md`** — estado técnico y de seguridad, con lo corregido y lo que queda abierto.

## Qué necesita el servidor

Apache 2.4 · PHP 8.2 · MySQL 8. **Nada más**: no hacen falta Docker, Redis, Node ni Composer — el paquete lleva `vendor/` instalado y `public/build` compilado.

## Contenido

```
app/  database/  resources/  routes/  config/   Código de la aplicación
public/                                         Raíz web (único directorio publicado)
vendor/                                         Dependencias PHP ya instaladas
public/build/                                   Frontend ya compilado
tests/                                          189 pruebas automatizadas
deploy/RUNBOOK.md                               Procedimiento de despliegue
deploy/.env.production.example                  Plantilla de variables
.github/workflows/ci.yml                        Estilo + pruebas + build + auditoría de dependencias
docs/                                           Documentación del proyecto
```

## Tres cosas que conviene saber de entrada

- **El correo es obligatorio.** Es el único canal por el que se entregan las contraseñas. Sin SMTP el sistema avisa en pantalla, pero nadie puede iniciar sesión. Hay una salida manual para arrancar (`php artisan usuario:password`), descrita en el runbook §8.
- **El motor de IA se entrega apagado.** Encenderlo transfiere datos personales del postulante a un proveedor externo fuera del país (Ley N.° 29733) y exige autorización escrita del área legal. Apagado, el sistema propone equivalencias por similitud de nombres y no envía nada fuera.
- **El sistema no emite el memorándum oficial.** Produce la preconvalidación; el acto oficial se gestiona fuera, por decisión del área usuaria.

## Estado

Los siete módulos están construidos y la suite de pruebas pasa en su totalidad. Pendiente: UAT y pase a producción.

Los entregables documentales A1, A2, A4 y A6 y los diagramas D-03, D-04 y D-06 **no forman parte de esta entrega** (ver `docs/expediente-pase-produccion/INDICE.md`).
