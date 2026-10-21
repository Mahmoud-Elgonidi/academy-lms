<?php

namespace Modules\Learning\Listeners;

use Modules\Learning\Contracts\Events\HomeworkSubmitted;
use Modules\Learning\Jobs\CheckRecitationJob;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SubmissionCheck;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFeature;

/**
 * A recording handed in on homework that says what to recite gets its check,
 * once, in the background, while the academy has the helper on.
 */
final class QueueRecitationCheck
{
    public function __construct(private readonly AcademyAi $ai) {}

    public function handle(HomeworkSubmitted $event): void
    {
        $homework = Homework::query()->find($event->homeworkId);
        if ($homework?->expectedSource() === null || ! $this->ai->on(AiFeature::RecitationCheck)) {
            return;
        }
        $submission = $homework->submissions()->reorder()->orderByDesc('submitted_at')->orderByDesc('id')->whereNotNull('file_path')->first();
        if ($submission === null || ! $submission->isAudio() || SubmissionCheck::query()->where('submission_id', $submission->id)->exists()) {
            return;
        }
        $check = SubmissionCheck::query()->create(['submission_id' => $submission->id, 'homework_id' => $homework->id, 'status' => 'pending']);
        CheckRecitationJob::dispatch($check->id);
    }
}
