<?php

namespace Modules\Safeguarding\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\Safeguarding\Events\ConcernEscalated;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Models\ConcernEntry;

/**
 * A concern nobody took within the hour: the leads hear again, once, and it
 * says so on the concern.
 *
 * @return int how many were raised again
 */
final class FollowUpConcerns
{
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $raised = 0;
        $late = Concern::query()->where('status', ConcernStatus::New->value)->whereNull('escalated_at')
            ->where('created_at', '<=', $now->subMinutes(Concern::ACK_MINUTES))->get();
        foreach ($late as $concern) {
            DB::transaction(function () use ($concern, $now, &$raised) {
                $updated = Concern::query()->whereKey($concern->id)->where('status', ConcernStatus::New->value)->whereNull('escalated_at')->update(['escalated_at' => $now]);
                if ($updated === 1) {
                    $raised++;
                    ConcernEntry::event($concern, 'escalated', null);
                    DB::afterCommit(fn () => event(new ConcernEscalated($concern->academy_id, $concern->id)));
                }
            });
        }

        return $raised;
    }
}
