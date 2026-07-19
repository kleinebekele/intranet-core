<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registrierung_ist_geschlossen_sobald_benutzer_existieren(): void
    {
        User::factory()->create();

        $this->get('/register')->assertRedirect(route('login'));

        $this->post('/register', [
            'name' => 'Eindringling',
            'email' => 'boese@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'boese@example.com']);
    }

    public function test_registrierung_laesst_sich_per_env_oeffnen(): void
    {
        config(['intranet.registration_enabled' => true]);
        User::factory()->create();

        $this->get('/register')->assertOk();

        $this->post('/register', [
            'name' => 'Neuer Kollege',
            'email' => 'neu@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_erste_registrierung_ist_trotz_schalter_offen(): void
    {
        // Kein Benutzer vorhanden (frische Installation) → offen, wird Admin.
        $this->get('/register')->assertOk();

        $this->post('/register', [
            'name' => 'Erster Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertTrue(User::first()->is_admin);
    }
}
