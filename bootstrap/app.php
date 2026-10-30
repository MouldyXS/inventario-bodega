<?php

use App\Http\Middleware\AsegurarUsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [AsegurarUsuarioActivo::class]);

        // §6.3: `tailscale serve` termina el HTTPS y reenvía a Apache en 127.0.0.1.
        // Solo se confían las cabeceras X-Forwarded-* que llegan desde ahí.
        $middleware->trustProxies(at: '127.0.0.1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
