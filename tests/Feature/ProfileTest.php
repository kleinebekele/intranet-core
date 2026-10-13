<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Test User', $user->refresh()->name);
    }

    public function test_neue_email_braucht_passwort(): void
    {
        $user = User::factory()->create();
        $alt = $user->email;

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => 'neu@example.com'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => 'neu@example.com', 'current_password' => 'falsch'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame($alt, $user->refresh()->email);
        $this->assertNull($user->email_neu);
    }

    public function test_neue_email_gilt_erst_nach_bestaetigung(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $alt = $user->email;

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => 'neu@example.com', 'current_password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame($alt, $user->email, 'Adresse darf vor der Bestätigung nicht wechseln.');
        $this->assertSame('neu@example.com', $user->email_neu);
        $this->assertNotNull($user->email_neu_token);

        // Falscher Schlüssel ändert nichts.
        $this->actingAs($user)->get('/profile/email/bestaetigen/'.str_repeat('x', 40))
            ->assertSessionHasErrors('email');
        $this->assertSame($alt, $user->refresh()->email);

        $token = Str::random(40);
        $user->forceFill(['email_neu_token' => hash('sha256', $token)])->save();

        $this->actingAs($user)->get('/profile/email/bestaetigen/'.$token)
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('neu@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->email_neu);
        $this->assertNull($user->email_neu_token);

        // Der Link gilt nur einmal.
        $this->actingAs($user)->get('/profile/email/bestaetigen/'.$token)
            ->assertSessionHasErrors('email');
    }

    public function test_bestaetigungslink_laeuft_ab(): void
    {
        $user = User::factory()->create();
        $alt = $user->email;
        $token = Str::random(40);
        $user->forceFill([
            'email_neu' => 'neu@example.com',
            'email_neu_token' => hash('sha256', $token),
            'email_neu_am' => now()->subMinutes(61),
        ])->save();

        $this->actingAs($user)->get('/profile/email/bestaetigen/'.$token)
            ->assertSessionHasErrors('email');

        $this->assertSame($alt, $user->refresh()->email);
    }

    public function test_bestaetigung_scheitert_wenn_adresse_inzwischen_vergeben(): void
    {
        $user = User::factory()->create();
        $alt = $user->email;
        $token = Str::random(40);
        $user->forceFill([
            'email_neu' => 'neu@example.com',
            'email_neu_token' => hash('sha256', $token),
            'email_neu_am' => now(),
        ])->save();
        User::factory()->create(['email' => 'neu@example.com']);

        $this->actingAs($user)->get('/profile/email/bestaetigen/'.$token)
            ->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertSame($alt, $user->email);
        $this->assertNull($user->email_neu);
    }

    public function test_fremder_bestaetigungslink_wirkt_nicht(): void
    {
        $opfer = User::factory()->create();
        $angreifer = User::factory()->create();
        $token = Str::random(40);
        $opfer->forceFill([
            'email_neu' => 'neu@example.com',
            'email_neu_token' => hash('sha256', $token),
            'email_neu_am' => now(),
        ])->save();

        $this->actingAs($angreifer)->get('/profile/email/bestaetigen/'.$token)
            ->assertSessionHasErrors('email');

        $this->assertNotSame('neu@example.com', $angreifer->refresh()->email);
        $this->assertNotSame('neu@example.com', $opfer->refresh()->email);
    }

    public function test_microsoft_konto_kann_email_nicht_aendern(): void
    {
        User::factory()->create(); // erster Benutzer wird Admin
        $user = User::factory()->create(['microsoft_id' => 'abc', 'password' => null]);
        $alt = $user->email;

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => 'neu@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame($alt, $user->refresh()->email);
        $this->assertNull($user->email_neu);
    }

    public function test_aenderung_zurueckziehen(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['email_neu' => 'neu@example.com', 'email_neu_token' => 'x', 'email_neu_am' => now()])->save();

        $this->actingAs($user)->delete('/profile/email')->assertRedirect('/profile');

        $this->assertNull($user->refresh()->email_neu);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
