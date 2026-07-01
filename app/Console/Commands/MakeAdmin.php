<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Promote a user to administrator by e-mail:
 *   php artisan intranet:admin someone@example.com
 */
class MakeAdmin extends Command
{
    protected $signature = 'intranet:admin {email}';

    protected $description = 'Grant administrator rights to a user by e-mail address.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("Kein Benutzer mit der E-Mail {$this->argument('email')} gefunden.");

            return self::FAILURE;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("{$user->email} ist jetzt Administrator.");

        return self::SUCCESS;
    }
}
