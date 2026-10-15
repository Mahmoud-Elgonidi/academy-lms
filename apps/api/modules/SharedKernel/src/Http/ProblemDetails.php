<?php

namespace Modules\SharedKernel\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Plans\FeatureNotInPlan;
use Modules\SharedKernel\Plans\PlanLimitReached;
use Modules\SharedKernel\Tenancy\Exceptions\TenantNotResolved;
use Modules\SharedKernel\Tenancy\Exceptions\TenantSuspended;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders API errors as RFC 7807 problem details (application/problem+json).
 * Titles are translated (modules/SharedKernel/lang/{ar,en}/problems.php) from Accept-Language,
 * also when the error comes before the locale middleware (no academy, no such route).
 */
final class ProblemDetails
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $type, $extra] = match (true) {
            $e instanceof PlanLimitReached => [403, 'plan-limit-reached', ['limit' => $e->limit->value, 'max' => $e->max]],
            $e instanceof FeatureNotInPlan => [403, 'feature-not-in-plan', ['feature' => $e->feature->value]],
            $e instanceof AiFailed => [$e->status(), $e->type(), ['reason' => $e->reason]],
            $e instanceof ValidationException => [422, 'validation', ['errors' => $e->errors()]],
            $e instanceof ProblemException => [$e->getStatusCode(), $e->type, $e->extra],
            $e instanceof AuthenticationException => [401, 'unauthenticated', []],
            $e instanceof AuthorizationException => [403, 'forbidden', []],
            $e instanceof TenantSuspended => [403, 'academy-suspended', []],
            $e instanceof TenantNotResolved => [404, 'academy-not-found', []],
            $e instanceof ModelNotFoundException => [404, 'not-found', []],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), self::typeForStatus($e->getStatusCode()), []],
            default => [500, 'server-error', []],
        };

        $locale = $request->headers->has('Accept-Language')
            ? ($request->getPreferredLanguage(SetLocale::SUPPORTED) ?? app()->getLocale())
            : app()->getLocale();

        $body = [
            'type' => $type,
            'title' => __("shared::problems.{$type}", [], $locale),
            'status' => $status,
        ];

        $detail = self::detail($e, $status);
        if ($detail !== null) {
            $body['detail'] = $detail;
        }

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return new JsonResponse($body + $extra, $status, ['Content-Type' => 'application/problem+json', 'Content-Language' => $locale] + $headers);
    }

    private static function typeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad-request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not-found',
            405 => 'method-not-allowed',
            409 => 'conflict',
            429 => 'too-many-requests',
            default => $status >= 500 ? 'server-error' : 'http-error',
        };
    }

    private static function detail(Throwable $e, int $status): ?string
    {
        // A helper that cannot run says so in the reader's words, never the provider's (busy is a 503 too).
        if ($e instanceof AiFailed) {
            return $e->sayable();
        }
        // Server errors never leak internals outside debug mode.
        if ($status >= 500) {
            return config('app.debug') ? $e->getMessage() : null;
        }

        // Anything not found says nothing of what it was: no class names, no
        // routes, no "which academy" (the title says enough, in the reader's language).
        if ($e instanceof PlanLimitReached) {
            return $e->getMessage();
        }
        if ($e instanceof ValidationException || $status === 404) {
            return null;
        }

        return $e->getMessage() !== '' ? $e->getMessage() : null;
    }
}
