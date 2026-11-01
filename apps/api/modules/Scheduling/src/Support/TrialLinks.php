<?php

namespace Modules\Scheduling\Support;

use Carbon\CarbonImmutable;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Http\LinkToken;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A family's own links for a coming free trial lesson, without signing in:
 * the portal's pages on the academy's host to change its time
 * (/{locale}/trial/lesson/{token}/change) and to say they cannot come
 * (/{locale}/trial/lesson/{token}/cancel). The token names the lesson
 * (LinkToken, purpose "trial-lesson") while it is to come; the portal reads and
 * acts through GET/POST v1/site/trials/lesson/{token}….
 */
final class TrialLinks
{
    public const PURPOSE = 'trial-lesson';

    public function __construct(
        private readonly TenantDirectory $directory,
        private readonly TenantContext $tenants,
    ) {}

    public function token(Session $session): string
    {
        // Good while the lesson is to come, wherever it is moved (lesson() checks that); 30 days at most.
        return LinkToken::issue(self::PURPOSE, $session->id, CarbonImmutable::now()->addDays(30));
    }

    /** The coming trial lesson a token names; null when it names none (another academy's, over, cancelled, not a trial). */
    public static function lesson(?string $token): ?Session
    {
        $id = LinkToken::read(self::PURPOSE, $token);
        $session = $id !== null ? Session::query()->find($id) : null;

        return $session !== null && $session->type === SessionType::Trial && $session->status === SessionStatus::Scheduled && $session->starts_at->isFuture()
            ? $session
            : null;
    }

    /** @return array{change: string, cancel: string} */
    public function links(Session $session, string $locale): array
    {
        $base = rtrim($this->directory->baseUrl($this->tenants->require()), '/').'/'.($locale === 'en' ? 'en' : 'ar').'/trial/lesson/'.$this->token($session);

        return ['change' => $base.'/change', 'cancel' => $base.'/cancel'];
    }
}
