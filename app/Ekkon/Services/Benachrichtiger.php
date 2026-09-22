<?php

namespace App\Ekkon\Services;

use App\Ekkon\Models\Notification;
use App\Ekkon\Models\NotificationRoute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Legt Benachrichtigungen an – und löst dabei die ZIELE auf.
 *
 * ── Warum hier und nicht im Versand? ────────────────────────────────────
 * Versand-Klassen wuchern, wenn drei Dinge in einer Datei liegen: wer kriegt
 * es, wie wird es formatiert, wie geht es raus. Jede neue Meldungsart wird dann
 * ein weiteres `if`.
 *
 * Deshalb hier die Trennung:
 *  - ROUTING passiert beim ANLEGEN (diese Klasse): schlägt die Ziele nach und
 *    schreibt fertige Zeilen – z. B. eine für Teams, eine für Mail.
 *  - ZUSTELLUNG ist DUMM (Task Notifications/SendNotifications): liest pending,
 *    schaut auf `typ`, ruft Mail- oder Teams-Versand. Kennt weder Meldungsarten
 *    noch Empfänger ⇒ kann nicht wuchern.
 */
class Benachrichtiger
{
    /**
     * @param  string  $meldungsart  Muss der Task im Code deklarieren
     *                               (EkkonTask::$meldungsarten) – die Maske
     *                               bietet nur diese zur Auswahl an.
     * @param  array<string, mixed>  $daten  Strukturiertes Beiwerk fürs Debugging
     * @param  string|null  $idempotenzSchluessel  Gesetzt = dieselbe Meldung wird
     *                      nie zweimal angelegt. Für Tasks, die im Minutentakt
     *                      laufen, praktisch Pflicht – sonst postet ein
     *                      15-Minuten-Task dieselbe Meldung 96x am Tag.
     * @param  array{name: string, inhalt?: string, pfad?: string}|null  $anhang
     *                      Datei zur Meldung: `inhalt` = rohe Bytes (werden hier
     *                      einmal abgelegt) oder `pfad` = liegt schon auf der Disk
     *                      `local`. Mail hängt sie an, Teams bekommt sie im
     *                      Webhook-Umschlag; alle Zielzeilen teilen sich die Datei.
     * @return array{angelegt: int, ohne_ziel: bool, uebersprungen: int}
     */
    public function benachrichtige(
        string $meldungsart,
        string $titel,
        string $text,
        array $daten = [],
        ?string $idempotenzSchluessel = null,
        ?string $quelle = null,
        ?string $html = null,
        ?array $anhang = null,
    ): array {
        $anhang = $this->anhangAblegen($anhang);

        $routen = NotificationRoute::query()
            ->where('meldungsart', $meldungsart)
            ->where('aktiv', true)
            ->get();

        // Keine Route ⇒ die Meldung wird NICHT verschluckt, sondern sichtbar
        // als 'ohne_ziel' abgelegt. Sonst vergisst man die Route und merkt
        // monatelang nicht, dass niemand informiert wird.
        if ($routen->isEmpty()) {
            $this->anlegen([
                'typ' => 'keins',
                'ziel' => null,
                'titel' => $titel,
                'text' => $text,
                'html' => $html,
                'anhang_pfad' => $anhang['pfad'] ?? null,
                'anhang_name' => $anhang['name'] ?? null,
                'daten' => $daten,
                'quelle' => $quelle,
                'meldungsart' => $meldungsart,
                'status' => 'ohne_ziel',
            ], $this->schluessel($idempotenzSchluessel, 'ohne_ziel', $meldungsart));

            return ['angelegt' => 0, 'ohne_ziel' => true, 'uebersprungen' => 0];
        }

        $angelegt = 0;
        $uebersprungen = 0;

        foreach ($routen as $route) {
            // Eine Route kann in MEHRERE Ziele aufgehen – „an die System-Admins"
            // ist je Admin eine eigene Zeile. Teams/feste Adresse = genau eins.
            $ziele = $this->zieleFuer($route);

            // Keine Ziele (leere Adresse, kein Admin vorhanden) = Konfigurations-
            // fehler, nicht stillschweigend eine leere Mail verschicken.
            if ($ziele === []) {
                $uebersprungen++;

                continue;
            }

            foreach ($ziele as $ziel) {
                $neu = $this->anlegen([
                    'typ' => $route->typ,
                    'ziel' => $ziel,
                    'titel' => $titel,
                    'text' => $text,
                    'html' => $html,
                    'anhang_pfad' => $anhang['pfad'] ?? null,
                    'anhang_name' => $anhang['name'] ?? null,
                    'daten' => $daten,
                    'quelle' => $quelle,
                    'meldungsart' => $meldungsart,
                    'status' => 'pending',
                ], $this->schluessel($idempotenzSchluessel, $route->typ, $ziel));

                $neu ? $angelegt++ : $uebersprungen++;
            }
        }

        return ['angelegt' => $angelegt, 'ohne_ziel' => false, 'uebersprungen' => $uebersprungen];
    }

    /** Ordner auf der Disk `local`, unter dem Anhänge liegen. */
    public const ANHANG_ORDNER = 'ekkon/anhaenge';

    /**
     * Rohe Bytes einmal auf die Disk legen; ein schon abgelegter Pfad wird
     * durchgereicht. Der Dateiname wird auf Buchstaben/Ziffern/Punkt/Strich
     * eingedampft – er wird später zum Dateinamen im Teams-Kanal.
     *
     * @param  array{name: string, inhalt?: string, pfad?: string}|null  $anhang
     * @return array{name: string, pfad: string}|null
     */
    private function anhangAblegen(?array $anhang): ?array
    {
        if ($anhang === null) {
            return null;
        }

        $name = trim((string) ($anhang['name'] ?? ''));
        $name = preg_replace('/[^\w.\-]+/u', '-', $name) ?: 'anhang';
        $name = mb_substr(trim($name, '-'), 0, 120);

        if (filled($anhang['pfad'] ?? null)) {
            return ['name' => $name, 'pfad' => (string) $anhang['pfad']];
        }

        if (! isset($anhang['inhalt']) || $anhang['inhalt'] === '') {
            return null;
        }

        $pfad = self::ANHANG_ORDNER.'/'.now()->format('Y-m').'/'.Str::lower(Str::random(12)).'-'.$name;
        Storage::disk('local')->put($pfad, $anhang['inhalt']);

        return ['name' => $name, 'pfad' => $pfad];
    }

    /**
     * Die konkreten Ziele einer Route – aufgelöst beim Anlegen (Routing-Prinzip).
     *
     * @return string[] Teams: eine Channel-ID · feste Adresse: eine Mailadresse ·
     *                  „an die Admins": je eine Adresse pro Administrator
     */
    private function zieleFuer(NotificationRoute $route): array
    {
        if ($route->typ === 'teams') {
            $id = (string) $route->teams_channel_id;

            return $id === '' ? [] : [$id];
        }

        if ($route->mail_an_admins) {
            return \App\Models\User::query()
                ->where('is_admin', true)
                ->whereNotNull('email')
                ->orderBy('email')
                ->pluck('email')
                ->map(fn ($m) => (string) $m)
                ->all();
        }

        // Ein bestimmter Benutzer: Adresse FRISCH auflösen, damit die Route einem
        // späteren Adresswechsel folgt. Gelöscht/ohne Adresse ⇒ kein Ziel.
        if ($route->mail_user_id) {
            $mail = (string) (\App\Models\User::query()->whereKey($route->mail_user_id)->value('email') ?? '');

            return $mail === '' ? [] : [$mail];
        }

        $adresse = (string) $route->mail_empfaenger;

        return $adresse === '' ? [] : [$adresse];
    }

    /**
     * Der Idempotenz-Schlüssel muss das ZIEL enthalten: Dieselbe Meldung geht
     * ja bewusst an mehrere Ziele (Teams UND Mail) – ohne Ziel im Schlüssel
     * würde das UNIQUE die zweite Zeile schlucken und nur einer bekäme sie.
     */
    private function schluessel(?string $basis, string $typ, string $ziel): ?string
    {
        if ($basis === null) {
            return null;
        }

        return mb_substr($basis.'|'.$typ.':'.$ziel, 0, 120);
    }

    /** @return bool true = neu angelegt, false = gab es schon (Idempotenz) */
    private function anlegen(array $werte, ?string $schluessel): bool
    {
        if ($schluessel === null) {
            Notification::create($werte);

            return true;
        }

        $vorher = Notification::query()->where('idempotenz_schluessel', $schluessel)->exists();

        if ($vorher) {
            return false;
        }

        Notification::create($werte + ['idempotenz_schluessel' => $schluessel]);

        return true;
    }
}
