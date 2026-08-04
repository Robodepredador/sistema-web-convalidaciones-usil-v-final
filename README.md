# Sistema de Convalidaciones USIL

Backend del Simulador Web de Convalidaciones de Cursos (Laravel 11 + Inertia + Vue 3, MySQL 8).

**Estado:** Construcción completa — los 7 módulos del alcance implementados (Sprints 1 a 4).
Pendiente: ejecución de UAT y puesta en producción (ver `deploy/RUNBOOK.md`).

El detalle de lo entregado por sprint está más abajo, en las tablas de trazabilidad.

## Decisiones técnicas confirmadas
- **Base de datos:** MySQL 8 (motor InnoDB).
- **Entorno local:** Laravel Sail (Docker) — paridad dev/QA/prod y escalabilidad.
- **Auth:** sesión (Inertia) con tabla `usuarios` y hashing bcrypt en `password_hash`.

## Contenido entregado
- `database/migrations/` — 18 migraciones (esquema completo en 3FN, FKs, índices, soft deletes).
  Incluye la corrección del defecto **TIESTAMP → TIMESTAMP** en `auditoria_log`.
- `app/Models/` — 17 modelos Eloquent con relaciones.
- `app/Http/Controllers/Auth/` — `LoginController` (RF-38/41/42) y `PasswordController`.
- `app/Http/Middleware/EnsureRole.php` — control de acceso por rol (RF-39).
- `app/Models/Concerns/FiltraPorCarrera.php` + `app/Policies/CarreraPolicy.php` — permisos por carrera (RF-40).
- `app/Services/AuditoriaService.php` — registro de auditoría (RNF-08).
- `database/seeders/` — roles base y usuario administrador inicial.
- `app/Http/Controllers/UsuarioController.php` — CU-10 (gestión de usuarios y permisos).
- `app/Http/Controllers/MallaController.php` — CU-01 (alta manual de mallas con ciclos/cursos).
- `app/Http/Middleware/HandleInertiaRequests.php` — comparte usuario/flash con el frontend.
- `resources/js/` — frontend Vue 3 + Inertia: Login, CambiarPassword, Dashboard, Usuarios (index/form), Mallas (index/form) y AppLayout.
- `tests/Feature/` — LoginTest, MallaTest (TC-01 duplicada), RbacTest.

## Puesta en marcha

El repositorio es un proyecto Laravel completo: se clona y se ejecuta, no hay que
crear un esqueleto aparte ni copiar archivos dentro.

**Requisitos:** PHP 8.2+, Composer 2, Node 20+, MySQL 8. Con Docker basta `docker compose up -d`
(levanta app + MySQL 8 + Redis) y se omite instalar PHP/MySQL a mano.

```bash
git clone https://github.com/Robodepredador/usil_convalidaciones.git
cd usil_convalidaciones

# 1. Dependencias
composer install
npm install

# 2. Entorno (el .env NO se versiona: cada quien tiene el suyo)
cp .env.example .env
php artisan key:generate

# 3. Base de datos — ajustar DB_HOST/DB_USERNAME/DB_PASSWORD en .env antes de migrar
php artisan migrate
php artisan db:seed     # crea roles y admin@usil.edu.pe / Admin#2026

# 4. Arrancar
npm run dev             # Vite, en una terminal aparte
php artisan serve       # o iniciar.bat en Windows (abre http://127.0.0.1:8080/login)
```

> `.env.example` trae `DB_HOST=mysql` porque asume Docker. Fuera de Docker use `127.0.0.1`.

## Trabajo en equipo

`main` es la rama estable. Los cambios entran por Pull Request, nunca con push directo a `main`.

```bash
git checkout main && git pull origin main
git checkout -b fix/descripcion-corta
# ...trabajar y commitear...
git push -u origin fix/descripcion-corta   # luego abrir el PR en GitHub
```

Antes de abrir el PR: `./vendor/bin/pint` (estilo) y `php artisan test` (81 pruebas) en verde.
CI (`.github/workflows/ci.yml`) vuelve a correr ambos, más `composer audit` y `npm audit`.

## Trazabilidad con la documentación
| Requisito | Implementación |
|-----------|----------------|
| RF-38 | Hashing bcrypt en `password_hash` |
| RF-39 | Roles + `EnsureRole` middleware |
| RF-40 | `permisos_carrera` + `FiltraPorCarrera` + `CarreraPolicy` |
| RF-41 | Bloqueo tras 5 intentos (`intentos_fallidos`, `bloqueado_hasta`) |
| RF-42 | Cambio forzado en primer acceso (`primer_acceso`) |
| RNF-08 | `AuditoriaService` registra en `auditoria_log` |

## Frontend (Vue 3 + Inertia)
Las dependencias ya están declaradas en `package.json`; basta compilar con Vite:
```bash
npm run dev     # desarrollo, con recarga en caliente
npm run build   # producción
```

## Trazabilidad adicional
| Caso de uso | Implementación |
|-------------|----------------|
| CU-10 | UsuarioController + Usuarios/Index.vue + Form.vue |
| CU-01 | MallaController + Mallas/Index.vue + Form.vue (RN-01/02/03, RF-04, RF-07) |

## Sprint 2 — incluido en este paquete
| Caso de uso / RF | Implementación |
|------------------|----------------|
| CU-02 | InstitucionController + Instituciones/Index.vue, Form.vue (RF-18, RF-23) |
| CU-03 | EquivalenciaController — registro de **mallas externas** de la institución de origen (RF-20..23). El catálogo de equivalencias curso↔curso se retiró por decisión de TI; el prefijo de ruta `equivalencias` quedó del módulo anterior (ver `routes/web.php`). |
| RF-08..12 | MallaImportController + Job ImportarMallaExcel + cargas_masivas + Mallas/Importar.vue, CargaEstado.vue |

**Carga masiva (RF-08..12):** valida estructura antes de procesar (RF-08), corre en
background vía colas Redis (RF-11), normaliza y distribuye en ciclos/cursos (RF-10) y
registra logs de éxito/fallo por línea (RF-12). La vista de estado consulta el progreso por sondeo.

> Formato del Excel: encabezados `ciclo`, `codigo`, `nombre`, `creditos`.

## Sprint 3 — incluido en este paquete
| Caso de uso / RF | Implementación |
|------------------|----------------|
| CU-04 | SimulacionController + SimulacionService (RF-24/25/26: tabla comparativa automática) |
| RF-27 | toggleDetalle: excluir/incluir cursos por excepción |
| CU-05 | generarPdf + pdf/simulacion.blade.php (RF-28/29) |
| CU-06 | ConvalidacionController::confirmar (RF-30/31, 1:1) + memorándum (RF-33) |
| RF-46/47 | anular: cambia estado a 'anulada' sin eliminar, con motivo y auditoría |

Vistas: `Simulaciones/Index.vue`, `Form.vue`, `Detalle.vue`; `Convalidaciones/Index.vue`.
PDFs con DomPDF y formato institucional USIL.

## Sprint 4 — incluido en este paquete
| Caso de uso / RF | Implementación |
|------------------|----------------|
| CU-08 | ReporteController (RF-36: resumen por facultad/carrera/fechas) |
| RF-37 | exportar + ConvalidacionesExport (Excel) |
| CU-11 | SugerenciaController::sugerir + SugerenciaIAService (RF-43/44) |
| CU-12 | SugerenciaController::aceptar (RF-45: la IA no autoconfirma) |
| RNF-09 | Seudonimizador: limpia datos personales antes de llamar a la IA (Ley 29733) |
| R-03 | Fallback por historial / por nombre cuando la IA no está disponible |

## Cobertura del alcance (7 módulos)
| Módulo | Estado |
|--------|--------|
| 1. Mallas curriculares (manual + Excel) | ✅ |
| 2. Instituciones y mallas externas | ✅ |
| 3. Simulación de convalidaciones + PDF | ✅ |
| 4. Convalidación confirmada + memorándum + anulación | ✅ |
| 5. Reportes + exportación Excel | ✅ |
| 6. Seguridad y gestión de usuarios | ✅ |
| 7. Asistente de IA (seudonimizado) | ✅ |

## Cómo correr las pruebas
Las pruebas corren contra **MySQL real**, no SQLite: las migraciones usan sintaxis propia de
MySQL (`ALTER TABLE ... MODIFY`). `phpunit.xml` fuerza la base `convalidaciones_test`, que hay
que crear una sola vez para no tocar la base de desarrollo:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS convalidaciones_test"
php artisan test
```

Cubre: seguridad (login/lockout/primer acceso), mallas (duplicada), RBAC, alcance por carrera,
carga masiva (job en cola), simulación (tabla automática), convalidación (1:1 y anulación),
auditoría de extremo a extremo, seudonimización y fallback de IA.

## Configuración adicional (IA)
- El bloque `openai` ya está en `config/services.php` (`config/services_openai_snippet.php`
  se conserva solo como referencia del snippet original).
- Definir `OPENAI_API_KEY` / `GEMINI_API_KEY` y el modelo en `.env` (nunca en el código).
  Por defecto `IA_PROVEEDOR=gemini`.
