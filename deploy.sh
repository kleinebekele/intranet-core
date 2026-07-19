#!/usr/bin/env bash
#
# Deploy-Skript fuer eine Intranet-Instanz.
#
# Aufruf auf dem Server (im Projektverzeichnis):   ./deploy.sh
#
# Die serverspezifischen Pfade (PHP, Composer, npm) stehen NICHT hier, sondern
# in einer nicht versionierten deploy.env daneben. Vorlage: deploy.env.example
#
set -euo pipefail
cd "$(dirname "$0")"

# --- Umgebung -------------------------------------------------------------
if [ -f deploy.env ]; then
    # shellcheck disable=SC1091
    . ./deploy.env
else
    echo "Hinweis: keine deploy.env gefunden, benutze php/composer/npm aus dem PATH."
fi

PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
NPM="${NPM:-npm}"

artisan() { $PHP artisan "$@"; }

schritt() { echo; echo "==> $*"; }

# --- Vorbedingungen -------------------------------------------------------
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "FEHLER: Es gibt lokale Aenderungen im Arbeitsverzeichnis." >&2
    echo "Bitte erst klaeren (git status), dann erneut deployen." >&2
    exit 1
fi

# --- Los ------------------------------------------------------------------
schritt "Wartungsmodus an"
artisan down --retry=15 || true
# Egal wie das Skript endet: die Seite kommt wieder hoch.
trap 'echo; echo "==> Wartungsmodus aus"; $PHP artisan up || true' EXIT

schritt "Code holen"
git pull --ff-only

schritt "PHP-Abhaengigkeiten"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist

schritt "Assets bauen"
if command -v "${NPM%% *}" >/dev/null 2>&1; then
    $NPM ci
    $NPM run build
else
    echo "WARNUNG: '$NPM' nicht gefunden - Assets werden NICHT neu gebaut."
    echo "         Falls sich CSS/JS geaendert haben, sieht die Seite alt aus."
fi

schritt "Datenbank"
artisan migrate --force

schritt "Caches neu aufbauen"
artisan optimize:clear
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache

schritt "Aufraeumen"
artisan storage:link || true

echo
echo "Fertig. Aktueller Stand: $(git log --oneline -1)"
