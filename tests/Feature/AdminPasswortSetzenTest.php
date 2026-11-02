<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Neben dem Passwort-Link kann der Admin ein Passwort direkt vergeben – für
 * Benutzer, bei denen der Link nicht ankommt.
 */
class AdminPasswortSetzenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_admin_setzt_passwort_und_benutzer_kommt_damit_rein(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())->withSession(['two_factor_passed' => true])
            ->post(route('admin.users.passwort', $user), ['password' => 'Neues-Passwort-2026', 'password_confirmation' => 'Neues-Passwort-2026'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Neues-Passwort-2026', $user->fresh()->password));
        $this->assertDatabaseHas('audit_log', ['aktion' => 'passwort.gesetzt', 'betroffener_id' => $user->id]);
    }

    public function test_bestaetigung_muss_passen(): void
    {
        $user = User::factory()->create();
        $alt = $user->password;

        $this->actingAs($this->admin())->withSession(['two_factor_passed' => true])
            ->post(route('admin.users.passwort', $user), ['password' => 'Neues-Passwort-2026', 'password_confirmation' => 'anders'])
            ->assertSessionHasErrorsIn('passwort', 'password');

        $this->assertSame($alt, $user->fresh()->password);
    }

    public function test_normaler_benutzer_darf_nicht(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.users.passwort', $user), ['password' => 'Neues-Passwort-2026', 'password_confirmation' => 'Neues-Passwort-2026'])
            ->assertForbidden();
    }

    public function test_bearbeiten_seite_zeigt_formular(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())->withSession(['two_factor_passed' => true])
            ->get(route('admin.users.edit', $user))
            ->assertOk()->assertSee('Passwort setzen');
    }
}
