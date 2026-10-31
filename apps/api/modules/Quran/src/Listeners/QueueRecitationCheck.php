<?php

namespace Modules\Quran\Listeners;

use Modules\Learning\Contracts\Events\HomeworkSubmitted;
use Modules\Learning\Models\Homework;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Jobs\CheckRecitationJob;
use Modules\Quran\Models\SubmissionCheck;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFeature;

/**
 * A recording handed in on homework that says what to recite gets its check,
 * once, in the background, while the academy has the helper on and its
 * plan has the Quran.
 */
final class QueueRecitationCheck
{
    public function __construct(
        private readonly AcademyAi $ai,
        private readonly QuranArea $area,
    ) {}

    public function handle(HomeworkSubmitted $event): void
    {
        $homework = Homework::query()->find($event->homeworkId);
        if (VerseRange::fromArray($homework?->expected) === null || ! $this->area->on() || ! $this->ai->on(AiFeature::RecitationCheck)) {
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
