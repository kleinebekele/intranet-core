<?php

namespace Tests\Feature;

use App\Ekkon\Models\Notification;
use App\Ekkon\Models\NotificationRoute;
use App\Ekkon\Services\Benachrichtiger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Meldungsarten stumm schalten (Route vom Typ „ohne") und liegengebliebene
 * Meldungen ohne Ziel je Art auf einmal löschen.
 */
class EkkonBenachrichtigungStummTest extends TestCase
{
    use RefreshDatabase;

    private const ART = 'waechter-still';

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function ohneZiel(string $art, int $anzahl): void
    {
        foreach (range(1, $anzahl) as $i) {
            Notification::query()->create([
                'typ' => 'keins', 'ziel' => null, 'titel' => "T{$i}", 'text' => 'x',
                'meldungsart' => $art, 'status' => 'ohne_ziel',
            ]);
        }
    }

    public function test_stumme_route_verwirft_die_meldung(): void
    {
        NotificationRoute::query()->create(['meldungsart' => self::ART, 'typ' => NotificationRoute::TYP_OHNE, 'aktiv' => true]);

        $ergebnis = app(Benachrichtiger::class)->benachrichtige(self::ART, 'Titel', 'Text');

        $this->assertSame(['angelegt' => 0, 'ohne_ziel' => false, 'uebersprungen' => 0], $ergebnis);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_ohne_route_bleibt_die_meldung_sichtbar_liegen(): void
    {
        app(Benachrichtiger::class)->benachrichtige(self::ART, 'Titel', 'Text');

        $this->assertSame(1, Notification::query()->where('status', 'ohne_ziel')->count());
    }

    public function test_stumm_schalten_ueber_die_maske(): void
    {
        $this->actingAs($this->admin())->post(route('module.ekkon.notifications.route.store'), [
            'meldungsart' => self::ART,
            'typ' => 'ohne',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ekkon_notification_routes', ['meldungsart' => self::ART, 'typ' => 'ohne', 'aktiv' => true]);
    }

    public function test_alle_ohne_ziel_einer_art_loeschen(): void
    {
        $this->ohneZiel(self::ART, 3);
        $this->ohneZiel('andere-art', 2);
        Notification::query()->create([
            'typ' => 'mail', 'ziel' => 'a@b.de', 'titel' => 'offen', 'text' => 'x',
            'meldungsart' => self::ART, 'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->delete(route('module.ekkon.notifications.ohne-ziel.destroy'), ['meldungsart' => self::ART])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame(0, Notification::query()->where('meldungsart', self::ART)->where('status', 'ohne_ziel')->count());
        // Andere Arten und noch zuzustellende Meldungen bleiben.
        $this->assertSame(2, Notification::query()->where('meldungsart', 'andere-art')->count());
        $this->assertSame(1, Notification::query()->where('status', 'pending')->count());
    }

    public function test_seite_zeigt_die_knoepfe(): void
    {
        $this->ohneZiel(self::ART, 2);

        $this->actingAs($this->admin())->get(route('module.ekkon.notifications.index'))
            ->assertOk()->assertSee('alle 2 löschen')->assertSee('stumm schalten');
    }
}
