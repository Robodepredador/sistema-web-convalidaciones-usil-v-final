# Sistema de Convalidaciones USIL

Backend del Simulador Web de Convalidaciones de Cursos (Laravel 11 + Inertia + Vue 3, MySQL 8).

**Estado:** construcción completa. Pendiente: UAT y puesta en producción (ver
[`deploy/RUNBOOK.md`](deploy/RUNBOOK.md)).

**El alcance cambió durante agosto de 2026 y este README refleja el sistema tal como se
entrega, no el plan original.** Cuatro módulos que sí llegaron a construirse salieron
después, por decisión del cliente o de TI:

| Módulo retirado | Cuándo | Qué implica |
|---|---|---|
| Memorándum oficial y anulación de convalidaciones | 10/08 | El acto formal se gestiona **fuera** del sistema. La pantalla «Convalidaciones» sobrevive como historial de preconvalidaciones |
| Reportes e indicadores | 10/08 | Sin pantalla de reportes ni exportación agregada |
| Mallas externas | 15/08 | Ya no se registra la malla de la institución de origen como documento propio |
| Motor de IA | 15/08 | Ver «Motor de IA» al final: **se entrega apagado** |

El detalle de lo vigente está más abajo, en las tablas de trazabilidad.

## Decisiones técnicas confirmadas
- **Base de datos:** MySQL 8 (motor InnoDB).
- **Entorno local:** PHP, MySQL y Node instalados en la máquina. Se usaba Docker (Sail);
  se retiró en agosto de 2026 al confirmarse que el entorno de destino no lo tiene.
- **Colas y caché:** MySQL. Se usaba Redis; sobraba para el volumen real del sistema.
- **Auth:** sesión (Inertia) con tabla `usuarios` y hashing bcrypt en `password_hash`.

## Contenido entregado
- `database/migrations/` — 87 migraciones (esquema completo en 3FN, FKs, índices, soft deletes).
  Incluye la corrección del defecto **TIESTAMP → TIMESTAMP** en `auditoria_log`.
- `app/Models/` — 25 modelos Eloquent con relaciones.
- `app/Http/Controllers/Auth/` — `LoginController` (RF-38/41/42) y `PasswordController`.
- `app/Http/Middleware/EnsurePermission.php` — control de acceso por permiso granular (RF-39).
- `app/Services/AlcanceService.php` — alcance por carrera/facultad (RF-40).
- `app/Services/AuditoriaService.php` — registro de auditoría (RNF-08).
- `database/seeders/` — roles base y usuario administrador inicial.
- `app/Http/Controllers/UsuarioController.php` — CU-10 (gestión de usuarios y permisos).
- `app/Http/Controllers/MallaController.php` — CU-01 (alta manual de mallas con ciclos/cursos).
- `app/Http/Middleware/HandleInertiaRequests.php` — comparte usuario/flash con el frontend.
- `resources/js/` — frontend Vue 3 + Inertia: autenticación, Dashboard, Usuarios, Estructura
  (sedes/facultades/programas/modalidades), Mallas, Instituciones, Mapeo de Mallas,
  Postulantes, Simulaciones, Convalidaciones y el Portal del postulante.
- `tests/` — 34 archivos, 168 pruebas (ver «Cómo correr las pruebas»).

## Puesta en marcha

El repositorio es un proyecto Laravel completo: se clona y se ejecuta, no hay que
crear un esqueleto aparte ni copiar archivos dentro.

**Requisitos:** PHP 8.2+, Composer 2, Node 20+, MySQL 8 en la propia máquina.

```bash
git clone https://github.com/Robodepredador/sistema-web-convalidaciones-usil-v-final.git
cd sistema-web-convalidaciones-usil-v-final

# 1. Dependencias
composer install
npm install

# 2. Entorno (el .env NO se versiona: cada quien tiene el suyo)
cp .env.example .env
php artisan key:generate

# 3. Base de datos — ajustar DB_HOST/DB_USERNAME/DB_PASSWORD en .env antes de migrar
php artisan migrate
php artisan db:seed     # crea roles, catálogos y admin@usil.edu.pe
                        # La contraseña se genera al azar y SE IMPRIME UNA SOLA VEZ: anótela.
                        # Si se pierde: php artisan usuario:password admin@usil.edu.pe

# 4. Arrancar
npm run dev             # Vite, en una terminal aparte
php artisan serve       # o iniciar.bat en Windows (abre http://127.0.0.1:8080/login)
```

> Crear antes la base vacía: `CREATE DATABASE convalidaciones_usil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
> Para las pruebas hace falta además `convalidaciones_test` (ver `phpunit.xml`).

## Trabajo en equipo

`main` es la rama estable. Los cambios entran por Pull Request, nunca con push directo a `main`.

```bash
git checkout main && git pull origin main
git checkout -b fix/descripcion-corta
# ...trabajar y commitear...
git push -u origin fix/descripcion-corta   # luego abrir el PR en GitHub
```

Antes de abrir el PR: `./vendor/bin/pint` (estilo) y `php artisan test` (168 pruebas) en verde.
CI (`.github/workflows/ci.yml`) vuelve a correr ambos, más `composer audit` y `npm audit`.

## Despliegue

**Apache 2.4 + PHP 8.2 + MySQL 8, sin contenedores.** El procedimiento completo está en
[`deploy/RUNBOOK.md`](deploy/RUNBOOK.md); `DESPLIEGUE.md` resume qué lleva el paquete.

La app necesita disco persistente para `storage/app` y un worker de colas vivo (por cron o
systemd): sin ellos se pierden los archivos subidos y la carga masiva (RF-11) nunca procesa.
Eso descarta las plataformas *serverless* (Vercel, Netlify), que no ofrecen ninguno de los dos.

## Trazabilidad con la documentación
| Requisito | Implementación |
|-----------|----------------|
| RF-38 | Hashing bcrypt en `password_hash` |
| RF-39 | Permisos granulares + `EnsurePermission` middleware |
| RF-40 | `permisos_carrera` + `AlcanceService` |
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
| CU-03 | MapeoMallasController — catálogo de equivalencias curso↔curso (RF-20..23). El Especialista declara de antemano qué curso externo vale por cuál de USIL. El registro de **mallas externas** como documento propio se retiró el 15/08; el prefijo de ruta `equivalencias` quedó de aquel módulo (ver `routes/web.php`). |
| RF-08..12 | MallaImportController + Job ImportarMallaExcel + cargas_masivas + Mallas/Importar.vue, CargaEstado.vue |

**Carga masiva (RF-08..12):** valida estructura antes de procesar (RF-08), corre en
background vía colas sobre MySQL (RF-11), normaliza y distribuye en ciclos/cursos (RF-10) y
registra logs de éxito/fallo por línea (RF-12). La vista de estado consulta el progreso por sondeo.

> Formato del Excel: encabezados `ciclo`, `codigo`, `nombre`, `creditos`.

## Sprint 3 — incluido en este paquete
| Caso de uso / RF | Implementación |
|------------------|----------------|
| CU-04 | SimulacionController + SimulacionService (RF-24/25/26: tabla comparativa automática) |
| RF-27 | toggleDetalle: excluir/incluir cursos por excepción |
| CU-05 | generarPdf + pdf/simulacion.blade.php (RF-28/29) |
| CU-06 | ConvalidacionController::index — historial de preconvalidaciones, **solo lectura** |
| — | Descarga en Excel sobre las plantillas institucionales (`storage/app/plantillas/`) |

Vistas: `Simulaciones/Index.vue`, `Simular.vue`, `Detalle.vue`, `Historico.vue`;
`Convalidaciones/Index.vue`. PDFs con DomPDF y formato institucional USIL.

> **RF-30/31/33 y RF-46/47 (memorándum oficial y anulación) no forman parte de la entrega.**
> Se construyeron y se retiraron el 10/08 por decisión del cliente: el acto formal de
> convalidación se gestiona fuera del sistema. La tabla `convalidaciones` se conserva con su
> historial, pero ya no se escribe.

## Portal del postulante
| Funcionalidad | Implementación |
|---|---|
| Acceso con credenciales propias | `Portal/AccesoController` + `Portal/Login.vue` |
| Seguimiento del expediente por fases | `Portal/SeguimientoController` + `SeguimientoTimeline` |
| Cambio obligatorio en el primer acceso (RF-42) | `Portal/PasswordController` + middleware `postulante.cambiar` |

Las credenciales se entregan por correo y, si no hay SMTP, por pantalla
(`ModalCredenciales.vue` + `EntregaCredenciales`): el sistema **dice la verdad** sobre si el
correo salió o no, en vez de dar por bueno un envío que nunca ocurrió.

## Cobertura del alcance
| Módulo | Estado |
|--------|--------|
| 1. Mallas curriculares (manual + Excel) | ✅ |
| 2. Instituciones y catálogo de equivalencias | ✅ |
| 3. Simulación de convalidaciones + PDF y Excel | ✅ |
| 4. Historial de preconvalidaciones | ✅ (solo lectura) |
| 5. Portal del postulante | ✅ |
| 6. Seguridad, RBAC y gestión de usuarios | ✅ |
| — Memorándum oficial, anulación y reportes | ❌ retirados del alcance (10/08) |
| — Mallas externas | ❌ retirado del alcance (15/08) |
| — Motor de IA | ⏸️ se entrega apagado (ver abajo) |

## Cómo correr las pruebas
Las pruebas corren contra **MySQL real**, no SQLite: las migraciones usan sintaxis propia de
MySQL (`ALTER TABLE ... MODIFY`). `phpunit.xml` fuerza la base `convalidaciones_test`, que hay
que crear una sola vez para no tocar la base de desarrollo:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS convalidaciones_test"
php artisan test
```

**168 pruebas.** Cubren: seguridad (login, bloqueo por intentos, primer acceso), mallas
(duplicadas, importación), RBAC y alcance por carrera, carga masiva (job en cola), simulación
y equivalencias, expediente documental, portal del postulante, exportaciones a Excel,
integridad del esquema y auditoría de extremo a extremo.

## Motor de IA — se entrega APAGADO

El sistema **no llama a ningún proveedor externo de IA y no envía datos fuera**. El motor se
retiró del flujo el 15/08: el Especialista declara de antemano todas las equivalencias válidas
tras comparar sílabos, y el Administrativo escoge dentro de esa lista. No queda nada que
sugerir automáticamente.

Lo que queda en el repositorio es el andamiaje, inerte y sin ruta que lo alcance:
`app/Models/Configuracion.php`, `resources/js/Pages/Configuracion/Index.vue`,
`app/Services/Seudonimizador.php`, el bloque `openai` de `config/services.php` y las variables
`IA_PROVEEDOR` / `GEMINI_API_KEY` / `OPENAI_API_KEY` de los `.env.example`. **Se conserva a
propósito**, para poder reactivarlo sin reconstruirlo.

> **Para TI:** las variables de IA de `deploy/.env.production.example` se dejan **vacías**. No
> hay que conseguir ninguna clave. Encender la IA no es un cambio de configuración: exige
> volver a cablear el módulo y, antes de eso, **autorización escrita del área legal de USIL**,
> porque implica transferir internacionalmente datos personales del postulante —nombre,
> documento y notas— (Ley N.° 29733).
