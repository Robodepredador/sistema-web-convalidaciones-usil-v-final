# Runbook de Despliegue — Sistema de Convalidaciones USIL

Procedimiento para desplegar a producción y revertir en caso de falla.

## 1. Requisitos previos
- Servidor con Docker y Docker Compose (o cPanel/VPS con PHP 8.2, MySQL 8, Redis).
- DNS apuntando al servidor y certificado TLS (HTTPS obligatorio — RNF-01).
- Secretos definidos: `APP_KEY`, credenciales de BD, credenciales SMTP, API key de IA.

## 2. Preparación
```bash
git clone <repo> && cd usil-convalidaciones
git checkout v1.0.0                      # release etiquetado
cp deploy/.env.production.example .env   # completar todos los __definir__
```

> Complete **todos** los `__definir__` antes de continuar. En particular el bloque
> `MAIL_*`: sin él, `MAIL_MAILER` cae a `log` y los correos de acceso nunca se envían
> — el flujo aparenta funcionar y falla en silencio. Desde la auditoría del 3 de agosto
> el correo es el **único** canal de entrega de las contraseñas temporales (del personal
> y del postulante), así que sin SMTP no se puede dar de alta a nadie.

## 3. Primera instalación (solo una vez)
```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan key:generate
```

> **`key:generate` se ejecuta UNA SOLA VEZ, en la instalación inicial.**
> `APP_KEY` cifra las API keys guardadas en la tabla `configuraciones`.
> Regenerarla en un redespliegue las vuelve indescifrables y cierra todas las
> sesiones activas. Respalde el valor junto con los demás secretos.

## 4. Despliegue y actualizaciones
```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

> El servicio `worker` ya queda activo para la carga masiva e IA (RF-11).
> `db:seed` es idempotente y **no** crea cuentas demo cuando `APP_ENV=production`.

## 5. Verificación post-despliegue (smoke test)
- [ ] La página de login responde por HTTPS.
- [ ] Inicio de sesión con el admin inicial (`admin@usil.edu.pe`) y cambio de contraseña forzado.
- [ ] **No existe ninguna cuenta `*.demo@usil.edu.pe` activa** (ver §6).
- [ ] Registrar una malla externa oficial y verificar que sus cursos se listan.
- [ ] Generar una simulación y descargar su PDF.
- [ ] Confirmar una convalidación y verificar la numeración del memorándum.
- [ ] Registrar un postulante y confirmar que **recibe el correo** con sus credenciales.
- [ ] Crear un usuario de prueba con perfil **distinto de Superusuario**, entrar con él y
      completar el cambio de contraseña forzado (RF-42). La contraseña temporal llega por
      correo: en producción ya **no** se muestra en pantalla.
- [ ] `docker compose ... logs worker` muestra el worker activo.

## 6. Checklist de seguridad
- [ ] `APP_DEBUG=false` y `APP_ENV=production`.
- [ ] `SESSION_SECURE_COOKIE=true` y el sitio responde solo por HTTPS.
- [ ] Secretos fuera del repositorio; `APP_KEY` respaldada.
- [ ] Sin cuentas demo:
      ```sql
      SELECT email, activo FROM usuarios WHERE email LIKE '%.demo@%';
      -- Debe devolver 0 filas, o todas con activo = 0.
      ```
- [ ] Backups de BD programados y probados (restauración verificada, no solo el dump).
- [ ] Cabeceras de seguridad activas (Nginx) y TLS válido.

## 7. Rollback
```bash
# Volver a la versión anterior estable
git checkout v0.9.0
docker compose -f docker-compose.prod.yml up -d --build
# Si una migración falló:
docker compose -f docker-compose.prod.yml exec app php artisan migrate:rollback
# Restaurar respaldo de BD si fuera necesario.
```

> No ejecute `key:generate` durante un rollback.

## 8. Operación continua
- Backups diarios de BD y de los PDF (simulaciones/memorándums).
- Rotación de logs (`LOG_CHANNEL=daily`) y del worker.
- Monitoreo de disponibilidad (objetivo SLA 99,5% — RNF-07).
- `auditoria_log` crece sin purga automática: revise su tamaño trimestralmente.
