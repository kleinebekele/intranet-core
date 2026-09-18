<?php

namespace Tests\Fixtures\alttasks\Probe;

use Intranet\Modules\Ekkon\Tasks\EkkonTask;

/**
 * Task eines Fachmoduls, das noch vom ALTEN Ekkon-Namen erbt – so wie
 * ekkon-jtl, BI und das Aftersales-Portal es bis zu ihrer Umstellung tun.
 */
class Altlast extends EkkonTask
{
    public string $category = 'Probe';

    public string $description = 'Erbt vom alten Klassennamen.';

    public function schedule(): string
    {
        return '0 3 * * *';
    }

    public function run(): array
    {
        return ['ok' => true];
    }
}
