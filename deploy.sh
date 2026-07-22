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
# composer.json/.lock sind auf einer Instanz dauerhaft geaendert - dort stehen die
# per composer require dazugeholten Module, die der generische Core nicht kennt.
# Das ist der Normalzustand und kein Hindernis. Alles andere schon.
#
# core.fileMode=false nur fuer DIESEN Aufruf: Auf einem Server muss der Webserver
# storage/ und bootstrap/cache/ beschreiben duerfen, dafuer laeuft dort einmal ein
# chmod. Das aendert die Rechte-Bits auch der mitversionierten .gitignore-Dateien,
# und Git meldet einen Rechte-Wechsel genauso als "M" wie eine inhaltliche
# Aenderung. Wir wollen hier aber nur echte Inhalte sehen.
aenderungen="$(git -c core.fileMode=false status --porcelain --untracked-files=no \
    | grep -vE '^.. composer\.(json|lock)$' || true)"

if [ -n "$aenderungen" ]; then
    echo "FEHLER: Es gibt unerwartete lokale Aenderungen im Arbeitsverzeichnis:" >&2
    echo "$aenderungen" >&2
    echo "Bitte erst klaeren, dann erneut deployen." >&2
    exit 1
fi

# --- Los ------------------------------------------------------------------
schritt "Wartungsmodus an"
artisan down --retry=15 || true
# Egal wie das Skript endet: die Seite kommt wieder hoch.
trap 'echo; echo "==> Wartungsmodus aus"; $PHP artisan up || true' EXIT

schritt "Code holen"
vorher_deploy="$(git rev-parse "HEAD:$(basename "$0")" 2>/dev/null || true)"
git pull --ff-only

# Hat der Pull DIESES Skript veraendert, mit der neuen Fassung NEU starten - sonst
# liefe der Rest des Deploys noch mit der alten Logik (etwa einem alten composer-
# Schritt). So genuegt EIN ./deploy.sh, auch wenn sich das Deploy-Skript selbst
# aendert. Der Blob-Vergleich vermeidet eine Endlosschleife: nach dem exec ist
# nichts mehr zu ziehen, vorher == nachher, und es laeuft normal durch.
if [ -n "$vorher_deploy" ] \
    && [ "$vorher_deploy" != "$(git rev-parse "HEAD:$(basename "$0")" 2>/dev/null || true)" ]; then
    schritt "deploy.sh wurde aktualisiert - Neustart mit der neuen Fassung"
    exec "$0" "$@"
fi

schritt "PHP-Abhaengigkeiten"
# update statt install: Die eigenen Module (do1emu/*) sollen bei jedem Deploy
# automatisch auf die neueste per Constraint erlaubte Version ziehen (^1.0 =
# neuestes 1.x), ohne dass jemand sie auf dem Server einzeln benennt. Alles Fremde
# (Laravel ...) bleibt exakt auf dem Lock-Stand - nur die gematchten Pakete bewegen
# sich. Setzt voraus, dass nur getaggt wird, was live gehen soll.
#
# Kein --prefer-dist: Instanzen setzen preferred-install teils bewusst auf "source"
# (private VCS-Repos ohne API). Das Flag wuerde diese Einstellung ueberstimmen.
$COMPOSER update "do1emu/*" --no-dev --optimize-autoloader --no-interaction

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

schritt "Module abgleichen"
# ZWINGEND NACH migrate, nie davor: Eine Migration haengt Menuepunkte um (etwa
# wenn ein Modul aufgeteilt wird). Liefe der Abgleich zuerst, saehe er den
# Menuepunkt als verwaist an und wuerde ihn samt der daran haengenden Rollen
# loeschen - die waeren dann von Hand nachzutragen.
artisan modules:sync

schritt "Caches neu aufbauen"
artisan optimize:clear
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache

schritt "Aufraeumen"
# Nur beim allerersten Deploy noetig; danach steht der Link und artisan wuerde
# bei jedem Lauf einen roten ERROR ausgeben, der keiner ist.
if [ ! -e public/storage ]; then
    artisan storage:link
else
    echo "public/storage steht bereits."
fi

echo
echo "Fertig. Aktueller Stand: $(git log --oneline -1)"
