<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware) {
        $trustedProxies = env('TRUSTED_PROXIES');
        if ($trustedProxies !== null) {
            if (is_bool($trustedProxies) || $trustedProxies === '*') {
                $middleware->trustProxies(at: '*');
            } elseif (is_string($trustedProxies) && trim($trustedProxies) !== '') {
                $middleware->trustProxies(at: array_map('trim', explode(',', $trustedProxies)));
            }
        }

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'shift' => \App\Http\Middleware\EnsureShiftIsOpen::class,
        ]);
        // Defense in depth: safe headers everywhere, strict headers + HTTPS
        // redirect in production. Never breaks the app's own behaviour.
        $middleware->web(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
