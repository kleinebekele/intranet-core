#!/usr/bin/env bash
#
# Deploy-Skript fuer eine Intranet-Instanz.
#
# Aufruf auf dem Server (im Projektverzeichnis):   ./deploy.sh
#                                                   ./deploy.sh --immer
#
# Ohne Neuigkeiten (kein neuer Code, keine neuen Modulversionen, keine offenen
# Migrationen) hoert das Skript nach dem Pruefen auf - kein Asset-Bau, kein
# Wartungsmodus. --immer erzwingt den kompletten Lauf (z. B. nach Aenderungen an
# der .env oder wenn ein Lauf mittendrin abgebrochen ist).
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

IMMER=0
[ "${1:-}" = "--immer" ] && IMMER=1

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
# Code, PHP-Pakete und Assets kommen, WAEHREND die Seite noch laeuft - das dauert
# am laengsten (npm ci/build oft eine halbe Minute). In den Wartungsmodus geht es
# erst fuer Migration, Modulabgleich und Caches: dann sind es wenige Sekunden.
# Preis: In der kurzen Spanne zwischen Pull und Migration laeuft schon neuer Code
# gegen die alte Datenbank. Bei reinen Oberflaechen-Aenderungen egal; braucht neuer
# Code eine neue Tabelle, kann genau in diesen Sekunden ein Aufruf scheitern.

schritt "Code holen"
vorher_deploy="$(git rev-parse "HEAD:$(basename "$0")" 2>/dev/null || true)"
vorher_head="$(git rev-parse HEAD)"
git pull --ff-only
# Neuer Code? (Nach einem Neustart wegen geaenderter deploy.sh steht das in der Umgebung.)
CODE_NEU="${DEPLOY_CODE_NEU:-0}"
[ "$vorher_head" != "$(git rev-parse HEAD)" ] && CODE_NEU=1

# Hat der Pull DIESES Skript veraendert, mit der neuen Fassung NEU starten - sonst
# liefe der Rest des Deploys noch mit der alten Logik (etwa einem alten composer-
# Schritt). So genuegt EIN ./deploy.sh, auch wenn sich das Deploy-Skript selbst
# aendert. Der Blob-Vergleich vermeidet eine Endlosschleife: nach dem exec ist
# nichts mehr zu ziehen, vorher == nachher, und es laeuft normal durch.
if [ -n "$vorher_deploy" ] \
    && [ "$vorher_deploy" != "$(git rev-parse "HEAD:$(basename "$0")" 2>/dev/null || true)" ]; then
    schritt "deploy.sh wurde aktualisiert - Neustart mit der neuen Fassung"
    DEPLOY_CODE_NEU=1 exec "$0" "$@"
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
lock_vorher="$(sha1sum composer.lock 2>/dev/null || true)"
$COMPOSER update "do1emu/*" --no-dev --optimize-autoloader --no-interaction
MODULE_NEU=0
[ "$lock_vorher" != "$(sha1sum composer.lock 2>/dev/null || true)" ] && MODULE_NEU=1

# Nichts Neues? Dann ist hier Schluss - offene Migrationen (etwa nach einem
# abgebrochenen Lauf) zaehlen als Neuigkeit, damit nichts liegen bleibt.
OFFEN=0
if artisan migrate:status --pending 2>/dev/null | grep -q 'Pending'; then
    OFFEN=1
fi
if [ "$IMMER" = 0 ] && [ "$CODE_NEU" = 0 ] && [ "$MODULE_NEU" = 0 ] && [ "$OFFEN" = 0 ]; then
    echo
    echo "Nichts Neues: kein neuer Code, keine neuen Modulversionen, keine offenen Migrationen."
    echo "Kein Wartungsmodus, nichts weiter zu tun. (Alles trotzdem: ./deploy.sh --immer)"
    echo "Aktueller Stand: $(git log --oneline -1)"
    exit 0
fi
echo "Neu: Code=$CODE_NEU Module=$MODULE_NEU offene Migrationen=$OFFEN (1 = ja)"

schritt "Assets bauen"
if command -v "${NPM%% *}" >/dev/null 2>&1; then
    $NPM ci
    $NPM run build
else
    echo "WARNUNG: '$NPM' nicht gefunden - Assets werden NICHT neu gebaut."
    echo "         Falls sich CSS/JS geaendert haben, sieht die Seite alt aus."
fi

schritt "Wartungsmodus an"
# Eigene Seite resources/views/errors/503.blade.php, beim Abschalten vorgerendert
# (--render); --refresh laesst den Browser nach 15 s selbst neu laden.
artisan down --render="errors::503" --refresh=15 --retry=15 || true
# Egal wie das Skript endet: die Seite kommt wieder hoch.
trap 'echo; echo "==> Wartungsmodus aus"; $PHP artisan up || true' EXIT

schritt "Datenbank"
artisan migrate --force

schritt "Module abgleichen"
# ZWINGEND NACH migrate, nie davor: Eine Migration haengt Menuepunkte um (etwa
# wenn ein Modul aufgeteilt wird). Liefe der Abgleich zuerst, saehe er den
# Menuepunkt als verwaist an und wuerde ihn samt der daran haengenden Rollen
# loeschen - die waeren dann von Hand nachzutragen.
artisan modules:sync

# Anleitungen der Module (resources/hilfe/*.md) ins Wiki uebernehmen - nur,
# wenn das Wiki-Modul installiert ist (MODULES.md 5a).
if artisan list --raw 2>/dev/null | grep -q '^wiki:hilfe-sync'; then
    schritt "Anleitungen ins Wiki uebernehmen"
    artisan wiki:hilfe-sync
fi

schritt "Caches neu aufbauen"
artisan optimize:clear
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache

# Ab hier ist die Seite fertig - Aufraeumen und Dauerdienste brauchen keinen
# Wartungsmodus. Die Falle (trap) bleibt fuer Abbrueche davor zustaendig.
schritt "Wartungsmodus aus"
artisan up
trap - EXIT

schritt "Aufraeumen"
# Nur beim allerersten Deploy noetig; danach steht der Link und artisan wuerde
# bei jedem Lauf einen roten ERROR ausgeben, der keiner ist.
if [ ! -e public/storage ]; then
    artisan storage:link
else
    echo "public/storage steht bereits."
fi

# --- Dauerdienste ---------------------------------------------------------
# Dienste wie der Teams-Lauscher (php artisan teams:lauschen unter systemd)
# halten den alten Code im Speicher, bis sie neu starten. Welche es auf diesem
# Server gibt, steht in der deploy.env (DIENSTE="intranet-teams ..."). Der
# Neustart braucht root: sudo -n schlaegt ohne passende sudoers-Regel sofort
# fehl statt nach einem Passwort zu fragen - dann gibt es nur die Warnung.
if [ -n "${DIENSTE:-}" ]; then
    schritt "Dauerdienste neu starten"
    for dienst in $DIENSTE; do
        if sudo -n systemctl restart "$dienst" 2>/dev/null; then
            echo "$dienst neu gestartet."
        else
            echo "WARNUNG: '$dienst' konnte nicht neu gestartet werden (sudo-Regel fehlt?)."
            echo "         Bis zum Neustart laeuft dort der alte Code: sudo systemctl restart $dienst"
        fi
    done
fi

echo
echo "Fertig. Aktueller Stand: $(git log --oneline -1)"
