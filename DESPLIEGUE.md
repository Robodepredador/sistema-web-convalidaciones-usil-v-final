# Paquete de Despliegue — Sistema de Convalidaciones USIL

Paquete autocontenido para iniciar el despliegue. Incluye código, infraestructura y documentación.

## Contenido
```
.github/workflows/ci.yml          Pipeline CI (estilo + tests + build + auditoría de dependencias)
docker/                           Dockerfiles (dev y prod), Nginx, Supervisor
docker-compose.yml                Entorno de desarrollo (Sail)
docker-compose.prod.yml           Producción (Nginx + PHP-FPM + worker + MySQL 8 + Redis)
deploy/RUNBOOK.md                 Procedimiento de despliegue y rollback
deploy/.env.production.example    Plantilla de variables de producción
app/ database/ resources/ routes/ tests/   Código de la aplicación (Laravel + Vue/Inertia)
docs/                             Documentación del proyecto (RUP, plan, manuales, etc.)
README.md                         Guía técnica del proyecto
```

## Inicio rápido
1. Lea `deploy/RUNBOOK.md`.
2. Configure `.env` desde `deploy/.env.production.example`.
3. Ejecute el despliegue con `docker-compose.prod.yml`.

## Documentación incluida (docs/)
- Documentación RUP v2.0 (con diagramas)
- Reporte de Mejoras · Informe Técnico TI · Plan de Desarrollo
- Manual Técnico · Manual de Usuario

## Estado del producto
Construcción completa de los 7 módulos. Pendiente: ejecución de UAT y puesta en producción.
