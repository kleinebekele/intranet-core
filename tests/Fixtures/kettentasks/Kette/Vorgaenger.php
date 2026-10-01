<?php

namespace Tests\Fixtures\kettentasks\Kette;

use App\Ekkon\Tasks\EkkonTask;

/** Vorgänger einer Kette; schlägt fehl, solange $scheitern gesetzt ist. */
class Vorgaenger extends EkkonTask
{
    public static bool $scheitern = false;

    public string $category = 'Kette';

    public string $description = 'Vorgänger';

    public function schedule(): string
    {
        return '0 2 * * *';
    }

    public function run(): array
    {
        if (self::$scheitern) {
            throw new \RuntimeException('Vorgänger scheitert');
        }

        return ['ok' => true];
    }
}
