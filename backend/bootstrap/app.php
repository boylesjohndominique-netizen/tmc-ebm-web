<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        // Channel authorization endpoint lives under /api so the React app
        // (Bearer-token Sanctum auth) can reach it with the same CORS setup
        // as every other API route.
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Enable CORS for React frontend
        $middleware->prepend(HandleCors::class);

        // Admin middleware
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'perm' => PermissionMiddleware::class,
            'super.admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
    ])
    ->withExceptions(function ($exceptions): void {
        //
    })
    ->create();