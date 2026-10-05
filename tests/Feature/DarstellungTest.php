<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarstellungTest extends TestCase
{
    use RefreshDatabase;

    public function test_ohne_wahl_bleibt_das_intranet_hell(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertDontSee('<html lang="en" class="dark">', false)
            ->assertDontSee('prefers-color-scheme', false);
    }

    public function test_dunkel_setzt_die_klasse_am_html(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile/darstellung', ['farbschema' => 'dunkel'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('dunkel', $user->refresh()->farbschema);

        $html = $this->actingAs($user)->get('/profile')->getContent();
        $this->assertMatchesRegularExpression('/<html[^>]*class="dark"/', $html);
    }

    public function test_wie_system_schaltet_per_skript(): void
    {
        $user = User::factory()->create(['farbschema' => 'system']);

        $html = $this->actingAs($user)->get('/profile')->getContent();

        $this->assertDoesNotMatchRegularExpression('/<html[^>]*class="dark"/', $html);
        $this->assertStringContainsString("matchMedia('(prefers-color-scheme: dark)')", $html);
    }

    public function test_unbekannter_wert_wird_abgelehnt(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile/darstellung', ['farbschema' => 'lila'])
            ->assertSessionHasErrors('farbschema');

        $this->assertNull($user->refresh()->farbschema);
    }
}
