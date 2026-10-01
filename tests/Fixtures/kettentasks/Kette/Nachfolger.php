<?php

namespace Tests\Fixtures\kettentasks\Kette;

use App\Ekkon\Tasks\EkkonTask;

/** Nachfolger: verarbeitet die Daten von Kette/Vorgaenger. */
class Nachfolger extends EkkonTask
{
    public static int $laeufe = 0;

    public string $category = 'Kette';

    public string $description = 'Nachfolger';

    public array $folgtAuf = ['Kette/Vorgaenger'];

    public function schedule(): string
    {
        return '50 2 * * *';
    }

    public function run(): array
    {
        self::$laeufe++;

        return ['ok' => true];
    }
}
