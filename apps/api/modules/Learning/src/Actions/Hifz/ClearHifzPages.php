<?php

namespace Modules\Learning\Actions\Hifz;

use Modules\Learning\Models\HifzPage;

/** Takes pages off a learner's hifz map (marked by mistake): they are new to them again; their reviews stay. */
final class ClearHifzPages
{
    /** @param  list<int>  $pages */
    public function handle(string $studentId, array $pages): int
    {
        return HifzPage::query()->where('student_id', $studentId)->whereIn('page', $pages)->delete();
    }
}
