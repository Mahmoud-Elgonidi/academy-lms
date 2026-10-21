<?php

namespace Modules\Learning\Actions\Hifz;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Learning\Domain\Hifz\Scheduler;
use Modules\Learning\Models\HifzPage;
use Modules\SharedKernel\Tenancy\TenantContext;
use Ramsey\Uuid\Uuid;

/**
 * The learner (or a guardian beside them) says how a page of the daily
 * review went, when no check hears it: forgot, hard, good or easy. Once a
 * day per page on the learner's own clock; the first word that day counts.
 */
final class GradeHifzPage
{
    public function __construct(private readonly RecordHifzReview $review) {}

    public function handle(string $studentId, int $page, int $grade, ?CarbonImmutable $at = null): HifzPage
    {
        Validator::make(['grade' => $grade], ['grade' => ['required', 'integer', 'between:'.Scheduler::AGAIN.','.Scheduler::EASY]])->validate();
        $at ??= CarbonImmutable::now();
        $zone = DB::table('users')->where('id', $studentId)->value('timezone') ?: app(TenantContext::class)->require()->defaultTimezone;
        $day = $at->setTimezone($zone)->toDateString();
        $this->review->handle($studentId, $page, $grade, 'self', Uuid::uuid5(Uuid::NAMESPACE_URL, "hifz-self:{$studentId}:{$page}:{$day}")->toString(), $at);

        return HifzPage::query()->where('student_id', $studentId)->where('page', $page)->firstOrFail();
    }
}
