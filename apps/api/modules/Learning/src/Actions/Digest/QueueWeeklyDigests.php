<?php

namespace Modules\Learning\Actions\Digest;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Learning\Domain\Journey\Week;
use Modules\Learning\Jobs\SendWeeklyDigestJob;
use Modules\Learning\Models\LearningSettings;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Each hour, in the current academy: every learner with a course running or
 * paused whose own clock says Friday, 7 pm or later, gets their week's note
 * on its way, once (one row per learner and week, held by the database), on
 * the assist queue. Nothing while the academy has the note off.
 */
final class QueueWeeklyDigests
{
    public const DAY = CarbonImmutable::FRIDAY;

    public const HOUR = 19;

    public function __construct(private readonly TenantContext $tenants) {}

    /** @return int how many notes went on their way */
    public function handle(?CarbonImmutable $now = null): int
    {
        if (! LearningSettings::current()->weekly_digest) {
            return 0;
        }
        $now ??= CarbonImmutable::now();
        $academy = $this->tenants->require();
        // A course running (paid for or not yet) or paused: its weekly times are still the learner's.
        $running = array_map(fn (EnrollmentStatus $s) => $s->value, array_filter(EnrollmentStatus::cases(), fn (EnrollmentStatus $s) => $s->keepsSlots()));
        $students = DB::table('enrollments')->whereIn('status', $running)->distinct()->pluck('student_id')->all();
        $zones = $students === [] ? collect() : DB::table('users')->whereIn('id', $students)->where('status', 'active')->pluck('timezone', 'id');

        $queued = 0;
        foreach ($zones as $studentId => $zone) {
            $zone = $zone ?: $academy->defaultTimezone;
            $local = $now->setTimezone($zone);
            if ($local->dayOfWeek !== self::DAY || $local->hour < self::HOUR) {
                continue;
            }
            $id = (string) Str::uuid7();
            $inserted = DB::table('weekly_digests')->insertOrIgnore([
                'id' => $id, 'academy_id' => $academy->id, 'student_id' => $studentId,
                'week_start' => Week::start($now, $zone)->toDateString(), 'status' => 'pending', 'sent_to' => 0, 'created_at' => $now,
            ]);
            if ($inserted === 1) {
                SendWeeklyDigestJob::dispatch($id);
                $queued++;
            }
        }

        return $queued;
    }
}
