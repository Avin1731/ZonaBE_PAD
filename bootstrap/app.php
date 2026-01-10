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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'ensuresubmissions'=> \App\Http\Middleware\EnsureSubmissions::class,
            'ensuredocument'=> \App\Http\Middleware\EnsureDocument::class,
            'ensureevaluation'=> \App\Http\Middleware\EnsureEvaluation::class,
            'checkdeadline'=> \App\Http\Middleware\CheckDeadline::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();