# Paquete de entrega — Sistema de Convalidaciones USIL

Convalidación de cursos por traslado externo. Laravel 11 + Vue 3 (Inertia) sobre MySQL 8.

## Por dónde empezar

1. **`deploy/RUNBOOK.md`** — instalación, actualización y reversión. Empieza por su §1: hay un requisito (PHP 8.2) que, si no se cumple, detiene todo lo demás.
2. **`docs/Auditoria-Entrega-TI-2026-08-18.md`** — estado técnico y de seguridad al cierre, con lo corregido y lo que queda abierto.
3. **`docs/Documentación_Final/`** — la documentación funcional y técnica del sistema: Documento Funcional, Documento Técnico, Diccionario de Datos y Manual de Usuario. Se entrega por separado del paquete (ver «Contenido»).

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

## Documentación

La documentación del sistema son cuatro documentos, en `docs/Documentación_Final/`:

| Documento | Audiencia | Contenido |
|---|---|---|
| Documento Funcional | Área usuaria y Gestión Académica | Alcance, actores, 20 requerimientos funcionales, 17 reglas de negocio, 7 casos de uso y matriz de trazabilidad. |
| Documento Técnico | Desarrollo y sistemas | Arquitectura, modelo de datos, requerimientos no funcionales, pruebas y despliegue. |
| Diccionario de Datos | Desarrollo y sistemas | Las 27 tablas de negocio campo a campo, con relaciones, índices y restricciones. |
| Manual de Usuario | Usuarios finales | Guía de uso pantalla por pantalla, por perfil, con capturas. |

**No viajan dentro del paquete `.tar.gz`**: son 21 MB de documentos e imágenes que se entregan aparte.

Los diagramas de proceso AS-IS y TO-BE y el diagrama de casos de uso quedan pendientes: requieren validación del proceso con el área usuaria.
