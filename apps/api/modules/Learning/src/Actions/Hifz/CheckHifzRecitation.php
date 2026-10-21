<?php

namespace Modules\Learning\Actions\Hifz;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Actions\Assist\CheckRecitation;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Domain\Hifz\Scheduler;
use Modules\Learning\Models\HifzRecitation;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\ExpectedText;
use Modules\SharedKernel\Ai\RecitationEngine;

/**
 * Hears a page recited for the daily review against the page's own words
 * (the engine is never told who recited it), keeps how it went word by word
 * for the learner to see at once, counts its grade as a review of the page,
 * and deletes the recording. Busy or a provider down is tried again by the
 * queue; any other failure is kept with its reason.
 */
final class CheckHifzRecitation
{
    public function __construct(
        private readonly AcademyAi $ai,
        private readonly RecitationEngine $engine,
        private readonly CurriculumApi $curriculum,
        private readonly RecordHifzReview $review,
    ) {}

    public function handle(HifzRecitation $recitation): HifzRecitation
    {
        if ($recitation->status !== 'pending') {
            return $recitation;
        }
        $page = $this->curriculum->quranPage($recitation->page);
        if ($recitation->file_path === null || $page === null) {
            return self::failed($recitation, 'unreadable');
        }

        try {
            $this->ai->require(AiFeature::RecitationCheck);
            $verses = array_map(fn (array $v) => [
                'surah' => $v['surah'], 'verse' => $v['verse'], 'words' => array_slice($page->words, $v['first'] - 1, $v['last'] - $v['first'] + 1),
            ], $page->verses);
            $result = $this->engine->check(AiFeature::RecitationCheck, AudioClip::fromDisk(SubmitHomework::DISK, $recitation->file_path, (string) $recitation->mime), new ExpectedText($verses));
        } catch (AiFailed $e) {
            if (in_array($e->reason, CheckRecitation::RETRIED, true)) {
                throw $e;
            }

            return self::failed($recitation, $e->reason);
        }

        $summary = $result->summary();
        $grade = Scheduler::gradeFor($summary['words'] > 0 ? max(0, $summary['ok'] - $summary['extra']) / $summary['words'] : 0.0);
        $recitation->update(['status' => 'ready', 'reason' => null, 'result' => $result->toArray(), 'grade' => $grade, 'checked_at' => CarbonImmutable::now()]);
        $this->review->handle($recitation->student_id, $recitation->page, $grade, 'wird', $recitation->id, $recitation->created_at);
        self::forget($recitation);

        return $recitation;
    }

    public static function failed(HifzRecitation $recitation, string $reason): HifzRecitation
    {
        $recitation->update(['status' => 'failed', 'reason' => mb_substr($reason, 0, 20), 'checked_at' => CarbonImmutable::now()]);
        self::forget($recitation);

        return $recitation;
    }

    /** Heard (or never to be): the recording goes. */
    private static function forget(HifzRecitation $recitation): void
    {
        if ($recitation->file_path !== null) {
            Storage::disk(SubmitHomework::DISK)->delete($recitation->file_path);
            $recitation->update(['file_path' => null]);
        }
    }
}
