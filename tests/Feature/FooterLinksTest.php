<?php

namespace Tests\Feature;

use App\Models\FooterLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Systemeinstellungen → Footer: Links im Footer, eigene Seiten (Impressum,
 * Datenschutz) auch ohne Anmeldung lesbar, Einbindung von Dienstleister-Skripten.
 */
class FooterLinksTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_impressum_und_datenschutz_sind_als_vorlage_angelegt(): void
    {
        $this->assertSame(['impressum', 'datenschutz'], FooterLink::orderBy('position')->pluck('slug')->all());

        $this->get('/seite/impressum')->assertOk()->assertSee('Lorem ipsum');
    }

    public function test_links_stehen_auf_der_anmeldeseite_und_im_footer(): void
    {
        $this->get('/login')->assertOk()->assertSee('Impressum')->assertSee('Datenschutz');

        $this->actingAs($this->admin())->get(route('admin.settings.index'))
            ->assertOk()->assertSee(route('seiten.show', 'datenschutz'));
    }

    public function test_admin_legt_eigene_seite_mit_einbindung_an(): void
    {
        $this->actingAs($this->admin())->post(route('admin.footer.store'), [
            'titel' => 'Barrierefreiheit',
            'art' => 'intern',
            'inhalt' => '<h2>Erklärung</h2><p>Text</p>',
            'einbindung' => '<script src="https://dienst.example/ds.js"></script>',
            'aktiv' => '1',
        ])->assertRedirect(route('admin.footer.index'));

        auth()->logout();

        $this->get('/seite/barrierefreiheit')->assertOk()
            ->assertSee('<h2>Erklärung</h2>', false)
            ->assertSee('<script src="https://dienst.example/ds.js"></script>', false);
    }

    public function test_externer_link_braucht_adresse_und_hat_keine_seite(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.footer.store'), ['titel' => 'Schule', 'art' => 'extern', 'aktiv' => '1'])
            ->assertSessionHasErrors('url');

        $this->actingAs($admin)->post(route('admin.footer.store'), [
            'titel' => 'Schule', 'art' => 'extern', 'url' => 'https://schule.example', 'aktiv' => '1',
        ])->assertRedirect();

        $link = FooterLink::where('titel', 'Schule')->sole();
        $this->assertNull($link->slug);

        auth()->logout();
        $this->get('/login')->assertSee('https://schule.example');
    }

    public function test_abgeschaltete_seite_ist_nicht_erreichbar(): void
    {
        FooterLink::where('slug', 'impressum')->update(['aktiv' => false]);

        $this->get('/seite/impressum')->assertNotFound();
        $this->get('/login')->assertDontSee('/seite/impressum');
    }

    public function test_nur_admins_pflegen_den_footer(): void
    {
        $this->admin();

        $this->actingAs(User::factory()->create())->get(route('admin.footer.index'))->assertForbidden();
    }

    public function test_reihenfolge_verschieben(): void
    {
        $datenschutz = FooterLink::where('slug', 'datenschutz')->sole();

        $this->actingAs($this->admin())->post(route('admin.footer.verschieben', $datenschutz), ['richtung' => 'links']);

        $this->assertSame(['datenschutz', 'impressum'], FooterLink::orderBy('position')->pluck('slug')->all());
    }
}
