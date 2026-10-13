<?php

namespace Tests\Feature;

use App\Ekkon\Models\WebhookQuelle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Webhook-Eingang mit optionaler HMAC-Signatur je Quelle. */
class EkkonWebhookSignaturTest extends TestCase
{
    use RefreshDatabase;

    private function quelle(?string $geheimnis = null): WebhookQuelle
    {
        return WebhookQuelle::create([
            'name' => 'Test',
            'schluessel' => WebhookQuelle::neuerSchluessel(),
            'aktiv' => true,
            'signatur_geheimnis' => $geheimnis,
        ]);
    }

    private function senden(WebhookQuelle $quelle, string $body, array $kopf = [])
    {
        return $this->call('POST', '/webhooks/ekkon/'.$quelle->schluessel, [], [], [],
            collect($kopf)->mapWithKeys(fn ($w, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $w])->all()
                + ['CONTENT_TYPE' => 'application/json'],
            $body);
    }

    private function signiert(string $geheimnis, string $body, ?int $zeit = null): array
    {
        $zeit ??= time();

        return [
            'X-Intranet-Zeit' => (string) $zeit,
            'X-Intranet-Signatur' => 'sha256='.hash_hmac('sha256', $zeit.'.'.$body, $geheimnis),
        ];
    }

    public function test_ohne_geheimnis_genuegt_der_schluessel(): void
    {
        $this->senden($this->quelle(), '{"a":1}')->assertOk();
    }

    public function test_mit_geheimnis_nur_signiert(): void
    {
        $geheimnis = WebhookQuelle::neuesGeheimnis();
        $quelle = $this->quelle($geheimnis);
        $body = '{"a":1}';

        $this->senden($quelle, $body)->assertStatus(401);
        $this->senden($quelle, $body, $this->signiert('falsch', $body))->assertStatus(401);
        $this->senden($quelle, '{"a":2}', $this->signiert($geheimnis, $body))->assertStatus(401);
        $this->senden($quelle, $body, $this->signiert($geheimnis, $body, time() - 600))->assertStatus(401);

        $kopf = $this->signiert($geheimnis, $body);
        $this->senden($quelle, $body, $kopf)->assertOk();

        // Derselbe Aufruf ein zweites Mal: abgewiesen.
        $this->senden($quelle, $body, $kopf)->assertStatus(409);

        $this->assertSame(1, $quelle->eingaenge()->count());
    }

    public function test_geheimnis_liegt_verschluesselt(): void
    {
        $quelle = $this->quelle('klartext-geheimnis');

        $roh = \DB::table('ekkon_webhook_quellen')->where('id', $quelle->id)->value('signatur_geheimnis');
        $this->assertNotSame('klartext-geheimnis', $roh);
        $this->assertSame('klartext-geheimnis', $quelle->fresh()->signatur_geheimnis);
    }
}
