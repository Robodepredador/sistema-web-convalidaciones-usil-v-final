#!/usr/bin/env bash
#
# Construye el paquete de entrega para TI.
#
# Parte de un CLON LIMPIO, no del directorio de trabajo: es la única forma de
# garantizar que no viaja nada sin versionar y que no falta nada que sí lo esté.
# Así se detectó que `VolverA.vue` y la plantilla de Excel no estaban en el
# repositorio: en la máquina de desarrollo todo funcionaba.
#
# Uso:  bash deploy/empaquetar.sh [directorio-destino]
#
set -euo pipefail

# En Windows, Composer suele instalarse fuera del PATH de Git Bash.
COMPOSER="${COMPOSER_BIN:-}"
if [ -z "$COMPOSER" ]; then
    if command -v composer >/dev/null 2>&1; then
        COMPOSER="composer"
    else
        for candidato in /c/tools/composer.bat /c/ProgramData/ComposerSetup/bin/composer.bat; do
            [ -f "$candidato" ] && COMPOSER="$candidato" && break
        done
    fi
fi
[ -n "$COMPOSER" ] || {
    echo "No se encontró Composer. Defina COMPOSER_BIN con su ruta." >&2; exit 1;
}

ORIGEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DESTINO="${1:-$ORIGEN/../paquete-convalidaciones}"
VERSION="$(cd "$ORIGEN" && git describe --tags --always 2>/dev/null || echo sin-etiqueta)"
NOMBRE="convalidaciones-usil-${VERSION}"
TRABAJO="$DESTINO/$NOMBRE"

echo "==> Clonando $VERSION en $TRABAJO"
rm -rf "$TRABAJO"
mkdir -p "$DESTINO"
git clone --quiet "$ORIGEN" "$TRABAJO"
rm -rf "$TRABAJO/.git"

cd "$TRABAJO"

echo "==> Dependencias PHP (sin las de desarrollo)"
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --quiet

echo "==> Compilando el frontend"
npm ci --silent
npm run build
rm -rf node_modules

echo "==> Retirando lo que no debe viajar"
# Nada de esto puede salir del entorno de desarrollo: .env y los cachés de
# configuración llevan credenciales; backups/ lleva datos personales reales.
rm -rf .env .env.backup backups .claude .cursor .github .phpunit.result.cache \
       public/hot storage/logs/*.log tests
rm -f bootstrap/cache/*.php
find storage/framework -type f ! -name '.gitignore' -delete 2>/dev/null || true

echo "==> Comprobación final"
FUGAS=0
for prohibido in .env backups .claude .cursor public/hot bootstrap/cache/config.php; do
    if [ -e "$prohibido" ]; then echo "   FUGA: $prohibido"; FUGAS=1; fi
done
for necesario in vendor/autoload.php public/build/manifest.json deploy/RUNBOOK.md \
                 storage/app/plantillas/formato_simulacion.xltx; do
    if [ ! -e "$necesario" ]; then echo "   FALTA: $necesario"; FUGAS=1; fi
done
[ "$FUGAS" -eq 0 ] || { echo "==> ABORTADO: el paquete no está limpio."; exit 1; }

echo "==> Comprimiendo"
cd "$DESTINO"
tar -czf "${NOMBRE}.tar.gz" "$NOMBRE"

echo
echo "Paquete listo: $DESTINO/${NOMBRE}.tar.gz"
echo "Instrucciones de instalación: deploy/RUNBOOK.md (dentro del paquete)"
