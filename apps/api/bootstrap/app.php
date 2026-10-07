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
use Modules\SharedKernel\Tenancy\Exceptions\TenantNotResolved;
use Modules\SharedKernel\Tenancy\Exceptions\TenantSuspended;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'tenant']])
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
        // A suspended academy's web pages: one page saying so, not a server error (the API answers 403 academy-suspended).
        $exceptions->render(function (TenantSuspended $e, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }
            app()->setLocale($request->getPreferredLanguage(SetLocale::SUPPORTED) ?? 'ar');

            return response()->view('shared::suspended', [], 503);
        });
        $exceptions->render(fn (Throwable $e, Request $request) => ProblemDetails::render($e, $request));

        // The back office on a host that is no academy: a plain 404, not a server error.
        $exceptions->map(TenantNotResolved::class, fn (TenantNotResolved $e) => request()->is('api/*')
            ? $e
            : new NotFoundHttpException($e->getMessage(), $e));
    })->create();
