<?php

namespace Tests\Feature;

use App\Mail\Vorlagen\VorlagenRegister;
use App\Models\MailOutbox;
use App\Models\MailVorlage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MailVorlagenBackendTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_uebersicht_listet_die_vorlagen(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.mailvorlagen.index'))
            ->assertOk()
            ->assertSee('Einladung (Zugang anlegen)')
            ->assertSee('Rahmen (Layout aller Mails)');
    }

    public function test_speichern_legt_eine_zeile_an(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.mailvorlagen.update', 'einladung'), [
                'betreff' => 'Neuer Betreff',
                'html' => '<p>Hallo {{ name }}</p>',
                'text' => 'Hallo {{ name }}',
            ])
            ->assertRedirect(route('admin.mailvorlagen.index'));

        $this->assertDatabaseHas('mail_vorlagen', ['schluessel' => 'einladung', 'betreff' => 'Neuer Betreff']);
    }

    /** Deckt sich alles mit dem Standard, wird nichts gespeichert. */
    public function test_standardwerte_speichern_keine_zeile(): void
    {
        $register = app(VorlagenRegister::class);
        $standard = $register->finden('einladung');

        $this->actingAs($this->admin())
            ->put(route('admin.mailvorlagen.update', 'einladung'), [
                'betreff' => $standard->betreff,
                'html' => $standard->html,
                'text' => $standard->text,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('mail_vorlagen', ['schluessel' => 'einladung']);
    }

    public function test_zuruecksetzen_loescht_die_zeile(): void
    {
        MailVorlage::create(['schluessel' => 'einladung', 'betreff' => 'x', 'html' => 'y', 'text' => 'z']);

        $this->actingAs($this->admin())
            ->post(route('admin.mailvorlagen.reset', 'einladung'))
            ->assertRedirect();

        $this->assertDatabaseMissing('mail_vorlagen', ['schluessel' => 'einladung']);
    }

    public function test_vorschau_rendert_ohne_zu_speichern(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.mailvorlagen.vorschau', 'einladung'), [
                'betreff' => 'Vorschau-Betreff',
                'html' => '<p>Hallo {{ name }}</p>',
                'text' => 'Hallo {{ name }}',
            ])
            ->assertOk()
            ->assertJsonFragment(['betreff' => 'Vorschau-Betreff']);

        // Nichts gespeichert.
        $this->assertDatabaseMissing('mail_vorlagen', ['schluessel' => 'einladung']);
    }

    public function test_vorschau_nutzt_die_eingegebenen_werte(): void
    {
        $antwort = $this->actingAs($this->admin())
            ->postJson(route('admin.mailvorlagen.vorschau', 'einladung'), [
                'betreff' => 'Test',
                'html' => '<p>Hallo {{ name }}</p>',
                'text' => 'Hallo {{ name }}',
                'werte' => ['name' => 'Klara Konkret'],
            ])
            ->assertOk()
            ->json();

        $this->assertStringContainsString('Klara Konkret', $antwort['html']);
    }

    /** Platzhalter, die diese Vorlage gar nicht kennt, werden verworfen. */
    public function test_vorschau_ignoriert_fremde_werte(): void
    {
        $antwort = $this->actingAs($this->admin())
            ->postJson(route('admin.mailvorlagen.vorschau', 'einladung'), [
                'html' => '<p>{{ name }}</p>',
                'text' => '{{ name }}',
                'werte' => ['gibtsnicht' => 'XXXX', 'name' => 'Klara'],
            ])
            ->assertOk()
            ->json();

        $this->assertStringContainsString('Klara', $antwort['html']);
        $this->assertStringNotContainsString('XXXX', $antwort['html']);
    }

    public function test_testmail_geht_an_die_freie_adresse(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.mailvorlagen.testmail', 'einladung'), [
                'an' => 'wohin@example.org',
                'werte' => ['name' => 'Klara Konkret'],
                'betreff' => 'Test',
                'html' => '<p>Hallo {{ name }}</p>',
                'text' => 'Hallo {{ name }}',
            ])
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);

        $eintrag = MailOutbox::sole();
        $this->assertContains('wohin@example.org', $eintrag->an);
        $this->assertStringStartsWith('[TEST]', $eintrag->betreff);
    }

    public function test_testmail_verlangt_eine_adresse(): void
    {
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->admin())
                ->postJson(route('admin.mailvorlagen.testmail', 'einladung'), [
                    'html' => '<p>x</p>', 'text' => 'x',
                ]);
            $this->fail('Erwartete eine ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('an', $e->errors());
        }
    }

    public function test_testmail_an_kuenstliche_adresse_geht_nicht_raus(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.mailvorlagen.testmail', 'einladung'), [
                'an' => 'schueler-1@schueler.intern',
                'html' => '<p>x</p>', 'text' => 'x',
            ])
            ->assertStatus(422);

        $this->assertSame(0, MailOutbox::count());
    }

    public function test_nicht_admins_haben_keinen_zugriff(): void
    {
        $this->admin();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.mailvorlagen.index'))
            ->assertForbidden();
    }

    public function test_unbekannte_vorlage_gibt_404(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.mailvorlagen.edit', 'gibtsnicht'))
            ->assertNotFound();
    }
}
