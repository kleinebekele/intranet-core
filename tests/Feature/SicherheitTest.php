<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\IpKuerzung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Härtungen vor dem öffentlichen Betrieb: gekürzte IPs, Grundschutz-Header,
 * Passwort-Reset ohne Kontoverrat.
 */
class SicherheitTest extends TestCase
{
    use RefreshDatabase;

    public function test_ips_werden_auf_zwei_bloecke_gekuerzt(): void
    {
        $this->assertSame('203.0.0.0', IpKuerzung::kuerzen('203.0.113.57'));
        $this->assertSame('2001:db8::', IpKuerzung::kuerzen('2001:db8:85a3::8a2e:370:7334'));
        $this->assertNull(IpKuerzung::kuerzen(''));
        $this->assertNull(IpKuerzung::kuerzen('kein-wert'));
    }

    public function test_grundschutz_header_stehen_an_jeder_antwort(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }

    public function test_passwort_reset_antwortet_immer_gleich(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $bekannt = $this->post('/forgot-password', ['email' => $user->email]);
        $unbekannt = $this->post('/forgot-password', ['email' => 'niemand@example.com']);
        $nochmal = $this->post('/forgot-password', ['email' => $user->email]);

        foreach ([$bekannt, $unbekannt, $nochmal] as $antwort) {
            $antwort->assertSessionHasNoErrors()->assertSessionHas('status', __('passwords.sent'));
        }
    }
}
