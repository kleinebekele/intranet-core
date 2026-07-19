<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // GANZ vorne und global: Die sprechenden Adressen müssen stehen, bevor
        // der Router die Anfrage einer Route zuordnet – und nicht nur für die
        // web-Gruppe, denn die greift erst nach dem Zuordnen.
        $middleware->prepend(\App\Http\Middleware\RoutenAliaseAnwenden::class);

        // 2FA-Sperre global an der web-Gruppe: schützt automatisch auch alle
        // Modul-Routen, ohne dass Module etwas davon wissen müssen.
        $middleware->web(append: \App\Http\Middleware\EnsureTwoFactorChallenge::class);

        // Sichtbarkeits-Einstellungen der Modul-Verwaltung als Zugriffs-Regel
        // für alle module.{key}.*-Routen (nicht nur fürs Menü).
        $middleware->web(append: \App\Http\Middleware\EnsureModuleAccess::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
