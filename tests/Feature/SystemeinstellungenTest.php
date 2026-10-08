<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Systemeinstellungen: Erscheinungsbild und Mailversand sind getrennte Reiter –
 * Speichern des einen darf den anderen nicht überschreiben.
 */
class SystemeinstellungenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_beide_reiter_sind_erreichbar(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()->assertSee('Systemeinstellungen')->assertSee('Haupttitel')->assertDontSee('Stundenlimit');

        $this->actingAs($admin)->get(route('admin.settings.mailversand'))
            ->assertOk()->assertSee('Stundenlimit')->assertDontSee('Haupttitel');
    }

    public function test_stundenlimit_wird_gespeichert(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.mailversand.update'), ['mail_stundenlimit' => 100])
            ->assertRedirect(route('admin.settings.mailversand'));

        $this->assertSame('100', Setting::get('mail_stundenlimit'));
    }

    public function test_erscheinungsbild_laesst_stundenlimit_in_ruhe(): void
    {
        Setting::set('mail_stundenlimit', '100');

        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), ['haupttitel' => 'Schule'])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame('100', Setting::get('mail_stundenlimit'));
        $this->assertSame('Schule', Setting::get('haupttitel'));
    }
}
