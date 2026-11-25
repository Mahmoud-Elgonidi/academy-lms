<?php

namespace Modules\Learning\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\Events\LessonsRemoved;
use Modules\Learning\Actions\ProjectProgress;

/** Lessons nobody had reached left a published version: each course on it counts its percent and next lesson again. */
final class RebuildProgressOnLessonsRemoved
{
    public function __construct(private readonly ProjectProgress $project) {}

    public function handle(LessonsRemoved $event): void
    {
        foreach (DB::table('enrollment_positions')->where('version_id', $event->versionId)->pluck('enrollment_id') as $enrollmentId) {
            $this->project->handle((string) $enrollmentId);
        }
    }
}
