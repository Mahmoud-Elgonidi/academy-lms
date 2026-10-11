<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Domain\Journey\Stars;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Learning\Queries\Journey\JourneyMap;

/**
 * A student opens the week's chest on their journey (docs/architecture/02,
 * section 18): every lesson of the week attended and every homework due in
 * it handed in, worked out again here from what happened. Once a week; its
 * stars (and the kids' next sticker) count from then on.
 */
final class OpenWeeklyChest
{
    public function __construct(private readonly JourneyMap $journey) {}

    /**
     * @return array{stars: int, sticker: ?string, total_before: int, total: int, rank_before: string, rank: array<string, mixed>}
     *
     * @throws ValidationException not every mission done, or already opened
     */
    public function handle(string $studentId, string $stage, string $period, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $state = $this->journey->for($studentId, $stage, false, $now);
        $chest = $state['chest'];
        if ($chest === null || $chest['period'] !== $period || $chest['state'] !== 'done') {
            throw ValidationException::withMessages(['period' => __($chest !== null && $chest['period'] === $period && $chest['state'] === 'opened'
                ? 'learning::map.chest.opened' : 'learning::map.chest.not_ready')]);
        }

        $before = (int) $state['stars']['total'];
        try {
            DB::transaction(fn () => ChallengeClaim::query()->create([
                'student_id' => $studentId,
                'challenge' => 'chest',
                'period' => $period,
                'points' => Stars::CHEST,
                'sticker' => $chest['sticker'],
                'claimed_at' => $now,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['period' => __('learning::map.chest.opened')]);
        }

        return [
            'stars' => Stars::CHEST,
            'sticker' => $chest['sticker'],
            'total_before' => $before,
            'total' => $before + Stars::CHEST,
            'rank_before' => $state['stars']['rank']['key'],
            'rank' => Stars::rank($before + Stars::CHEST),
        ];
    }
}
