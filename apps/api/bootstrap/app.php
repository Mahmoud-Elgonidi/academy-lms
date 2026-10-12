<?php

use App\Http\Middleware\ResolveTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\SharedKernel\Health\RecentErrors;
use Modules\SharedKernel\Http\Idempotent;
use Modules\SharedKernel\Http\ProblemDetails;
use Modules\SharedKernel\Http\ResolveViewerTimezone;
use Modules\SharedKernel\Http\SecurityHeaders;
use Modules\SharedKernel\Http\SetLocale;
use Modules\SharedKernel\Http\ThrottlePerRoute;
use Modules\SharedKernel\Plans\RequirePlanFeature;
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
            'idempotent' => Idempotent::class,
            'plan.feature' => RequirePlanFeature::class,
            // throttle:N,M counts each route on its own (Laravel's would share one count per person across routes).
            'throttle' => ThrottlePerRoute::class,
        ]);

        // The tenant must be known before authentication, rate limiting and
        // route model binding touch the database.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ResolveTenant::class);

        // Every answer carries the security headers; the apps' API has a ceiling per person.
        $middleware->append(SecurityHeaders::class);
        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A request that names no academy is answered 404; it is the caller's, not an error of the app to log or count.
        $exceptions->dontReportWhen(fn (Throwable $e) => $e instanceof TenantNotResolved && $e->byRequest);

        // Every reported error also goes to the platform's health page (redacted, counted by kind).
        $exceptions->report(function (Throwable $e): void {
            RecentErrors::record($e);
        });

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

        // Something that is not an id where an id goes (a cut link, a typo in an address, a probe) names a record
        // that is not there: 404, never the database refusing the text as a uuid (docs/architecture/03, section 11).
        // Only for a request: the same refusal in a job or a command is our own bug, reported as it is.
        $exceptions->map(QueryException::class, fn (QueryException $e) => $e->getCode() === '22P02' && str_contains($e->getMessage(), 'type uuid')
            && (! app()->runningInConsole() || app()->runningUnitTests())
            ? new NotFoundHttpException('Not found.', $e)
            : $e);
    })->create();
