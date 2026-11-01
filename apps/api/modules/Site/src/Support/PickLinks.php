<?php

namespace Modules\Site\Support;

use Carbon\CarbonImmutable;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Http\LinkToken;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Models\JoinRequest;

/**
 * A family's own "pick a time" link for the free trial it asked for (sent in
 * the instant reply while the academy lets families book): the portal's
 * page /{locale}/trial/{token} on the academy's host, which reads and books
 * through GET/POST v1/site/trials/pick/{token}. The token names the request
 * (LinkToken, purpose "site-pick") for DAYS days: it went to the number they
 * gave, so it proves that number without a code.
 */
final class PickLinks
{
    public const PURPOSE = 'site-pick';

    public const DAYS = 14;

    public function __construct(
        private readonly TenantDirectory $directory,
        private readonly TenantContext $tenants,
    ) {}

    public function url(JoinRequest $request): string
    {
        $token = LinkToken::issue(self::PURPOSE, $request->id, $request->created_at->addDays(self::DAYS));

        return rtrim($this->directory->baseUrl($this->tenants->require()), '/').'/'.$request->locale.'/trial/'.$token;
    }

    /** The trial request a token names, while it still waits for a time; null otherwise. */
    public static function request(string $token, ?CarbonImmutable $now = null): ?JoinRequest
    {
        $id = LinkToken::read(self::PURPOSE, $token);
        $request = $id !== null ? JoinRequest::query()->find($id) : null;

        return $request !== null && $request->kind === 'trial' && $request->session_id === null && $request->status !== 'declined'
            ? $request
            : null;
    }
}
