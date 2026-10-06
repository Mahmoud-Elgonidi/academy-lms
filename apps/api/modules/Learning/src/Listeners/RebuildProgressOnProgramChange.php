<?php

namespace Modules\Learning\Listeners;

use Modules\Enrollment\Contracts\Events\CurriculumAttached;
use Modules\Learning\Actions\ProjectProgress;

final class RebuildProgressOnProgramChange
{
    public function __construct(private readonly ProjectProgress $project) {}

    public function handle(CurriculumAttached $event): void
    {
        $this->project->handle($event->enrollmentId);
    }
}
