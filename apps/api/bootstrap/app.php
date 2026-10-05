<?php

use App\Http\Middleware\ResolveTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\SharedKernel\Http\ProblemDetails;
use Modules\SharedKernel\Http\ResolveViewerTimezone;
use Modules\SharedKernel\Http\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'locale' => SetLocale::class,
            'timezone' => ResolveViewerTimezone::class,
        ]);

        // The tenant must be known before authentication, rate limiting and
        // route model binding touch the database.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (Throwable $e, Request $request) => ProblemDetails::render($e, $request));
    })->create();
