<?php

namespace Modules\SharedKernel\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Tenancy\Exceptions\TenantNotResolved;
use Modules\SharedKernel\Tenancy\Exceptions\TenantSuspended;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders API errors as RFC 7807 problem details (application/problem+json).
 * Titles are translated (modules/SharedKernel/lang/{ar,en}/problems.php) from Accept-Language.
 */
final class ProblemDetails
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $type, $extra] = match (true) {
            $e instanceof ValidationException => [422, 'validation', ['errors' => $e->errors()]],
            $e instanceof AuthenticationException => [401, 'unauthenticated', []],
            $e instanceof AuthorizationException => [403, 'forbidden', []],
            $e instanceof TenantSuspended => [403, 'academy-suspended', []],
            $e instanceof TenantNotResolved => [404, 'academy-not-found', []],
            $e instanceof ModelNotFoundException => [404, 'not-found', []],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), self::typeForStatus($e->getStatusCode()), []],
            default => [500, 'server-error', []],
        };

        $body = [
            'type' => $type,
            'title' => __("shared::problems.{$type}"),
            'status' => $status,
        ];

        $detail = self::detail($e, $status);
        if ($detail !== null) {
            $body['detail'] = $detail;
        }

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return new JsonResponse($body + $extra, $status, ['Content-Type' => 'application/problem+json'] + $headers);
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
        // Server errors never leak internals outside debug mode.
        if ($status >= 500) {
            return config('app.debug') ? $e->getMessage() : null;
        }

        // A missing record says nothing of what it was (no class names).
        if ($e instanceof ValidationException || $e instanceof ModelNotFoundException) {
            return null;
        }

        return $e->getMessage() !== '' ? $e->getMessage() : null;
    }
}
