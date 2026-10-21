<?php

namespace Modules\Learning\Actions\Assist;

use Carbon\CarbonImmutable;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Hifz\HifzFromLessons;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\HomeworkSubmission;
use Modules\Learning\Models\SubmissionCheck;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\ExpectedText;
use Modules\SharedKernel\Ai\RecitationEngine;

/**
 * Checks a recording handed in against what the homework asked to recite,
 * word by word, for the teacher alone (docs/architecture/02, section 22). The
 * recording is only read, never changed or deleted; the engine is never told
 * who recited it. Nothing else of the homework changes: no grade, status,
 * stars or KPI reads the check. Busy or a provider down is tried again by the
 * queue; any other failure is kept with its reason, for the teacher to see.
 * The pages it covers are reviewed on the learner's hifz map (HifzFromLessons).
 */
final class CheckRecitation
{
    /** Failures the queue tries again. */
    public const RETRIED = ['busy', 'unavailable'];

    public function __construct(
        private readonly AcademyAi $ai,
        private readonly RecitationEngine $engine,
        private readonly CurriculumApi $curriculum,
        private readonly HifzFromLessons $hifz,
    ) {}

    public function handle(SubmissionCheck $check): SubmissionCheck
    {
        if ($check->status !== 'pending') {
            return $check;
        }
        $submission = HomeworkSubmission::query()->find($check->submission_id);
        $homework = Homework::query()->find($check->homework_id);
        $source = $homework?->expectedSource();
        if ($submission?->file_path === null || $source === null) {
            return self::failed($check, 'unreadable');
        }

        try {
            $this->ai->require(AiFeature::RecitationCheck);
            $passage = $this->curriculum->passage($source) ?? throw new AiFailed('unreadable');
            $verses = array_map(fn (array $v) => [
                'surah' => $v['surah'], 'verse' => $v['verse'], 'words' => array_slice($passage->words, $v['first'] - 1, $v['last'] - $v['first'] + 1),
            ], $passage->verses);
            $result = $this->engine->check(
                AiFeature::RecitationCheck,
                AudioClip::fromDisk(SubmitHomework::DISK, $submission->file_path, (string) $submission->mime),
                new ExpectedText($verses ?: [['surah' => null, 'verse' => null, 'words' => $passage->words]]),
            );
        } catch (AiFailed $e) {
            if (in_array($e->reason, self::RETRIED, true)) {
                throw $e;
            }

            return self::failed($check, $e->reason);
        }

        $check->update([
            'status' => 'ready',
            'reason' => null,
            'engine' => mb_substr($result->engine, 0, 80),
            'heard' => $result->heard,
            'result' => $result->toArray(),
            'seconds' => $result->seconds,
            'checked_at' => CarbonImmutable::now(),
        ]);
        // The pages it covers are reviewed on the learner's hifz map, by how each went.
        $this->hifz->recited($check, $result, $homework->student_id, CarbonImmutable::parse($submission->submitted_at));

        return $check;
    }

    public static function failed(SubmissionCheck $check, string $reason): SubmissionCheck
    {
        $check->update(['status' => 'failed', 'reason' => mb_substr($reason, 0, 20), 'checked_at' => CarbonImmutable::now()]);

        return $check;
    }
}
