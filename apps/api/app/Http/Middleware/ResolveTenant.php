<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Tenancy\Exceptions\TenantNotResolved;
use Modules\SharedKernel\Tenancy\Exceptions\TenantSuspended;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Decides which academy a request is for, before authentication and route
 * model binding run (it is placed ahead of both in the middleware priority).
 *
 *  1. A bearer token is the source of truth: users belong to one academy.
 *  2. Otherwise the host ({slug}.{platform domain} or a verified custom domain),
 *     or the X-Academy header (slug) that the shared mobile app sends.
 *
 * A token for academy A sent to academy B's host or header is refused (403).
 */
final class ResolveTenant
{
    public const HEADER = 'X-Academy';

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly TenantDirectory $directory,
        private readonly IdentityApi $identity,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $fromToken = $this->fromToken($request);
        $fromRequest = $this->fromHost($request) ?? $this->fromHeader($request);

        if ($fromToken !== null && $fromRequest !== null && $fromToken->id !== $fromRequest->id) {
            throw new AccessDeniedHttpException('This token belongs to a different academy.');
        }

        // A bad or expired token with nothing else to go on is an auth failure, not a missing academy.
        if ($fromToken === null && $fromRequest === null && $request->bearerToken()) {
            throw new AuthenticationException;
        }

        $tenant = $fromToken ?? $fromRequest ?? throw new TenantNotResolved;

        if (! $tenant->active) {
            throw new TenantSuspended;
        }

        $this->tenants->set($tenant);

        return $next($request);
    }

    public function terminate(): void
    {
        $this->tenants->clear();
    }

    private function fromToken(Request $request): ?TenantRef
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            return null;
        }

        $academyId = $this->identity->academyIdForToken($token);

        // An unknown token resolves nothing here; auth:sanctum rejects it later.
        return $academyId === null ? null : $this->directory->byId($academyId);
    }

    private function fromHost(Request $request): ?TenantRef
    {
        return $this->directory->byHost($request->getHost());
    }

    private function fromHeader(Request $request): ?TenantRef
    {
        $slug = $request->headers->get(self::HEADER);

        return is_string($slug) && $slug !== '' ? $this->directory->bySlug($slug) : null;
    }
}
