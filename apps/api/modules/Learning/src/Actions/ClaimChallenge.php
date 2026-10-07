<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Domain\Challenges\ChallengeCatalog;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Learning\Queries\ChallengesQuery;
use Modules\Learning\Queries\JourneyQuery;

/**
 * A student collects the reward of a challenge they finished: its points
 * (and, for the kids' chest, the next sticker), once per challenge and
 * period. Whether it is finished is worked out again here from what really
 * happened; the app's word is never taken for it.
 */
final class ClaimChallenge
{
    public function __construct(private readonly ChallengesQuery $challenges) {}

    /**
     * @return array{reward: int, sticker: ?string, points_before: int, points: int, rank_before: string, rank: array<string, mixed>}
     *
     * @throws ValidationException not finished, or already collected
     */
    public function handle(string $studentId, string $stage, string $challenge, string $period, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $state = $this->challenges->for($studentId, $stage, $now);
        $row = collect($state['challenges'])->first(fn (array $c) => $c['key'] === $challenge && $c['period'] === $period);
        if ($row === null || $row['state'] !== 'done') {
            throw ValidationException::withMessages(['challenge' => __($row !== null && $row['state'] === 'claimed' ? 'learning::challenges.errors.claimed' : 'learning::challenges.errors.not_ready')]);
        }

        $before = (int) $state['journey']['points'];
        try {
            DB::transaction(fn () => ChallengeClaim::query()->create([
                'student_id' => $studentId,
                'challenge' => $challenge,
                'period' => $period,
                'points' => $row['reward'],
                'sticker' => $challenge === 'chest' ? ($row['sticker'] ?? ChallengeCatalog::sticker(0)) : null,
                'claimed_at' => $now,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['challenge' => __('learning::challenges.errors.claimed')]);
        }

        return [
            'reward' => $row['reward'],
            'sticker' => $challenge === 'chest' ? $row['sticker'] : null,
            'points_before' => $before,
            'points' => $before + $row['reward'],
            'rank_before' => $state['journey']['rank']['key'],
            'rank' => JourneyQuery::rank($before + $row['reward']),
        ];
    }
}
