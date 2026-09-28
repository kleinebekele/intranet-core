<?php

namespace Tests\Feature;

use App\Models\MailOutbox;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Maillog: Tooltip an der Empfängeradresse mit Benutzer und Rollen.
 */
class MailOutboxAnsichtTest extends TestCase
{
    use RefreshDatabase;

    public function test_empfaenger_zeigt_benutzer_und_rollen(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $eltern = User::factory()->create(['name' => 'Erika Muster', 'email' => 'erika@example.org']);
        Role::create(['role_id' => 'eltern-klasse-3', 'name' => 'Eltern Klasse 3']);
        $eltern->roles()->attach('eltern-klasse-3');

        MailOutbox::create([
            'status' => MailOutbox::FEHLGESCHLAGEN,
            'betreff' => 'Inforum',
            'an' => ['Erika@Example.org', 'fremd@example.org'],
            'fehler' => '421 too many connections',
            'nachricht' => MailOutbox::verpacken((new Email)->from('a@example.org')->to('erika@example.org')->text('x')),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.mail.index'))
            ->assertOk()
            ->assertSee(route('admin.users.edit', $eltern), false)
            ->assertSee('Erika Muster')
            ->assertSee('Eltern Klasse 3')
            ->assertSee('Kein Benutzer mit dieser Adresse.');
    }
}
