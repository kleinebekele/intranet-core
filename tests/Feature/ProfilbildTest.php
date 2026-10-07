<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilbildTest extends TestCase
{
    use RefreshDatabase;

    public function test_profilbild_wird_zugeschnitten_gespeichert_und_ausgeliefert(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/bild', ['profilbild' => UploadedFile::fake()->image('foto.png', 800, 600)])
            ->assertRedirect('/profile');

        $pfad = $user->fresh()->profilbild;
        $this->assertStringEndsWith('.jpg', $pfad);
        [$b, $h] = getimagesizefromstring(Storage::disk('local')->get($pfad));
        $this->assertSame([256, 256], [$b, $h]);

        $this->get(route('profilbild', $user))->assertOk();
        $this->get('/profile')->assertSee('/profilbild/'.$user->id, false);
    }

    public function test_neues_bild_ersetzt_altes_und_entfernen_loescht_datei(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/bild', ['profilbild' => UploadedFile::fake()->image('a.jpg')]);
        $alt = $user->fresh()->profilbild;
        $this->actingAs($user)->post('/profile/bild', ['profilbild' => UploadedFile::fake()->image('b.jpg')]);

        Storage::disk('local')->assertMissing($alt);

        $this->actingAs($user)->post('/profile/bild', ['entfernen' => 1]);
        $this->assertNull($user->fresh()->profilbild);
        $this->assertSame([], Storage::disk('local')->files('profilbilder'));
    }

    public function test_keine_bilddatei_wird_abgelehnt(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/bild', ['profilbild' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('profilbild');
    }

    public function test_bild_nur_fuer_angemeldete(): void
    {
        $user = User::factory()->create(['profilbild' => 'profilbilder/x.jpg']);

        $this->get(route('profilbild', $user))->assertRedirect('/login');
    }
}
