<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Modules\Learning\Models\Cheer;

/** The student saw their cheers (the app showed them once). */
final class MarkCheersSeen
{
    public function handle(string $studentId): int
    {
        return Cheer::query()->where('student_id', $studentId)->whereNull('seen_at')->update(['seen_at' => CarbonImmutable::now()]);
    }
}
