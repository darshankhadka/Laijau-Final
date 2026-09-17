<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO |
                     Request::HEADER_X_FORWARDED_AWS_ELB
        );
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: (env('APP_ENV') === 'testing' || ($_ENV['APP_ENV'] ?? '') === 'testing') ? ['*'] : [
            'payment/esewa/*',
            'payment/khalti/*',
            'webhooks/*',
            'webhook',
            'api/webhooks/*',
            'api/webhook',
            'payment/connectips/*',
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\StorefrontMaintenanceMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
