<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\Events\CurriculumAttached;
use Modules\Enrollment\Models\Enrollment;

/**
 * Puts the student on a program of the enrollment's subject, pinned to the
 * version published today (docs/architecture/02, section 6). Progress is
 * rebuilt from the reports by Learning when it hears CurriculumAttached.
 */
final class AttachCurriculum
{
    public function __construct(private readonly CurriculumApi $curriculum) {}

    public function handle(Enrollment $enrollment, ?string $programId, ?string $actorId): void
    {
        $program = $programId === null ? null : $this->curriculum->program($programId);
        if ($programId !== null && ($program === null || $program->subjectId !== $enrollment->subject_id || $program->publishedVersionId === null)) {
            throw ValidationException::withMessages(['program_id' => __('enrollment::enrollment.errors.program')]);
        }

        DB::transaction(function () use ($enrollment, $program) {
            $enrollment->fill(['program_id' => $program?->id, 'curriculum_version_id' => $program?->publishedVersionId])->save();
            DB::afterCommit(fn () => event(new CurriculumAttached($enrollment->academy_id, $enrollment->id, $program?->id, $program?->publishedVersionId)));
        });
    }
}
