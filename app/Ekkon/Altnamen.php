<?php

namespace App\Ekkon;

use Composer\InstalledVersions;

/**
 * Ekkon war bis 2026-09 ein eigenes Paket (`do1emu/module-ekkon`, Namensraum
 * `Intranet\Modules\Ekkon`). Fachmodule erben weiterhin von den alten Namen –
 * dieser Autoloader bildet sie auf `App\Ekkon` ab, damit kein Modul auf einen
 * Schlag umgestellt werden muss.
 *
 * Wird ganz früh in `bootstrap/app.php` angemeldet: Paket-Provider registrieren
 * vor dem Core und greifen dabei schon auf die alten Namen zu.
 */
class Altnamen
{
    private const ALT = 'Intranet\\Modules\\Ekkon\\';

    private const PAKET = 'do1emu/module-ekkon';

    public static function anmelden(): void
    {
        if (self::altesPaketAktiv()) {
            return;
        }

        spl_autoload_register(static function (string $klasse): void {
            if (! str_starts_with($klasse, self::ALT)) {
                return;
            }

            $neu = __NAMESPACE__.'\\'.substr($klasse, strlen(self::ALT));

            if (class_exists($neu) || interface_exists($neu) || trait_exists($neu)) {
                class_alias($neu, $klasse);
            }
        });
    }

    /**
     * Eine Registry für alle – unter dem neuen UND dem alten Namen.
     *
     * Paket-Provider registrieren vor dem Core und binden die Registry selbst per
     * `singletonIf`. Täte das ein Modul unter dem neuen und ein anderes unter dem
     * alten Namen, gäbe es zwei Registries – und die Tasks des einen wären LAUTLOS
     * weg. Deshalb steht die Bindung schon, bevor der erste Provider läuft; alle
     * späteren `singletonIf` sind dann wirkungslos.
     */
    public static function registryBinden(\Illuminate\Contracts\Foundation\Application $app): void
    {
        if (self::altesPaketAktiv()) {
            return;
        }

        $app->singleton(Support\TaskRegistry::class);
        $app->alias(Support\TaskRegistry::class, self::ALT.'Support\\TaskRegistry');
    }

    /**
     * Liegt das alte Paket noch MIT Code in `vendor/`? Dann führt es – der Core
     * hält sich zurück, sonst liefen zwei Task-Systeme nebeneinander. Ab der
     * leeren Übergangsversion des Pakets (ohne `src/`) übernimmt der Core.
     */
    public static function altesPaketAktiv(): bool
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled(self::PAKET)) {
            return false;
        }

        $pfad = InstalledVersions::getInstallPath(self::PAKET);

        return $pfad !== null && is_file($pfad.'/src/EkkonServiceProvider.php');
    }
}
