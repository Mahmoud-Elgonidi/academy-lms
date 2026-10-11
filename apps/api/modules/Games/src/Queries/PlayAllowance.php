<?php

namespace Modules\Games\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Games\Domain\Round;
use Modules\Games\Models\GameLimit;
use Modules\Games\Models\GamePlay;
use Modules\Games\Models\GameSettings;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Timezones;

/**
 * Whether a learner may play now and for how long today: the academy's
 * choice, their guardian's (which can only lower it), and the minutes of
 * today's rounds on the learner's own clock (a round still open counts up to
 * Round::MAX_SECONDS).
 */
final class PlayAllowance
{
    public function __construct(private readonly TenantContext $tenants) {}

    /** Night, when a guardian locks it: from this hour to MORNING on the learner's clock. */
    public const NIGHT = 21;

    public const MORNING = 7;

    /** @return array{academy: bool, child: bool, limit: ?int, used: int, left: ?int, night_lock: bool, may: bool, reason: ?string} */
    public function for(string $studentId, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $settings = GameSettings::current();
        $limit = GameLimit::query()->find($studentId);
        $minutes = $limit?->daily_minutes ?? $settings->daily_minutes;
        if ($limit?->daily_minutes !== null && $settings->daily_minutes !== null) {
            $minutes = min($limit->daily_minutes, $settings->daily_minutes);
        }

        $zone = $this->zone($studentId);
        $dayStart = $now->setTimezone($zone)->startOfDay()->utc();
        $seconds = GamePlay::query()->where('student_id', $studentId)->where('started_at', '>=', $dayStart)->get(['started_at', 'seconds'])
            ->sum(fn (GamePlay $p) => $p->seconds ?? min(Round::MAX_SECONDS, max(0, $now->getTimestamp() - $p->started_at->getTimestamp())));
        $used = (int) ceil($seconds / 60);
        $left = $minutes === null ? null : max(0, $minutes - $used);

        $hour = (int) $now->setTimezone($zone)->format('G');
        $night = ($limit?->night_lock ?? false) && ($hour >= self::NIGHT || $hour < self::MORNING);

        $reason = match (true) {
            ! $settings->enabled => 'off',
            $limit !== null && ! $limit->enabled => 'paused',
            $night => 'night',
            $left === 0 => 'limit',
            default => null,
        };

        return ['academy' => $settings->enabled, 'child' => $limit?->enabled ?? true, 'limit' => $minutes, 'used' => $used, 'left' => $left,
            'night_lock' => $limit?->night_lock ?? false, 'may' => $reason === null, 'reason' => $reason];
    }

    /** The learner's own clock: their profile's zone, else the academy's. */
    public function zone(string $studentId): string
    {
        $zone = DB::table('users')->where('id', $studentId)->value('timezone');

        return Timezones::isValid($zone) ? $zone : ($this->tenants->current()?->defaultTimezone ?? 'UTC');
    }
}
