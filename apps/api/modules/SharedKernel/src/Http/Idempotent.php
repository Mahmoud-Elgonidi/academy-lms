<?php

namespace Modules\SharedKernel\Http;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\SharedKernel\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Idempotency-Key for the apps' writes (a cancel, a payment, a request): the
 * same key replays the first answer for a day instead of doing it twice, so
 * a retry on a bad network is safe. Keys are per academy, person and route;
 * the same key with a different body is a conflict. Without the header the
 * request runs as usual.
 */
final class Idempotent
{
    public const HEADER = 'Idempotency-Key';

    private const HOURS = 24;

    public function __construct(private readonly TenantContext $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get(self::HEADER);
        if ($key === null || $key === '' || $request->isMethodSafe()) {
            return $next($request);
        }
        if (strlen($key) > 255) {
            throw new ConflictHttpException(__('shared::problems.idempotency_key'));
        }

        $scope = 'idempotency:'.hash('sha256', implode('|', [$this->tenants->id(), $request->user()?->getAuthIdentifier(), $request->method(), $request->path(), $key]));
        $fingerprint = hash('sha256', $request->getContent());
        $store = $this->store();

        return $store->lock($scope.':lock', 30)->block(10, function () use ($store, $scope, $fingerprint, $request, $next): Response {
            $seen = $store->get($scope);
            if (is_array($seen)) {
                if ($seen['fingerprint'] !== $fingerprint) {
                    throw new ConflictHttpException(__('shared::problems.idempotency_reused'));
                }

                return response($seen['body'], $seen['status'], ['Content-Type' => $seen['type'], 'Idempotent-Replayed' => 'true']);
            }

            $response = $next($request);
            // Server errors are not kept: trying again may well work.
            if ($response->getStatusCode() < 500) {
                $store->put($scope, [
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'type' => $response->headers->get('Content-Type', 'application/json'),
                    'body' => (string) $response->getContent(),
                ], now()->addHours(self::HOURS));
            }

            return $response;
        });
    }

    private function store(): Repository
    {
        return Cache::store();
    }
}
