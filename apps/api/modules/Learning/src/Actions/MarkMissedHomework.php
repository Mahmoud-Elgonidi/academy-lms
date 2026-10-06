<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Modules\Learning\Models\Homework;

/** Sweeper: homework not handed in by its due time is missed (a late hand-in is still accepted). */
final class MarkMissedHomework
{
    public function handle(?CarbonImmutable $now = null): int
    {
        return Homework::query()->where('status', 'assigned')->where('due_at', '<', $now ?? CarbonImmutable::now())->update(['status' => 'missed', 'updated_at' => CarbonImmutable::now()]);
    }
}
