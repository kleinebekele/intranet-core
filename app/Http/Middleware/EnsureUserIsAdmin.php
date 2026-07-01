<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to admin-only routes for non-admin users.
 * Registered under the alias "admin" in bootstrap/app.php.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            abort(403, 'Nur Administratoren haben Zugriff auf diesen Bereich.');
        }

        return $next($request);
    }
}
