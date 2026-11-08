<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Ziel des Links aus `{{ konto_einrichten }}`: führt auf „Passwort vergessen"
 * und trägt dort die Mailadresse ein. Die Adresse steht nicht in der URL, der
 * Link ist signiert (Middleware `signed`).
 */
class KontoEinrichtenController
{
    public function __invoke(User $user): RedirectResponse
    {
        return redirect()->route('password.request')->withInput(['email' => $user->email]);
    }
}
