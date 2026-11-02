<?php

namespace Modules\Scheduling\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Actions\Shifts\ResolveShiftConflict;
use Modules\Scheduling\Actions\Shifts\ShiftProposals;
use Modules\Scheduling\Actions\Shifts\WithdrawShiftProposal;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Queries\ShiftImpactQuery;

/**
 * Room for families to start a course on their own in the demo (docs/
 * architecture/02, section 27, J3): a weekly time is offered only when it is
 * free in all of the first four weeks, and the demo's mid-term holiday (one
 * weekday two to three weeks ahead) and Nour's leave take whole weekdays out
 * of those weeks, so three teaching days left the Quran and Tajweed teachers
 * with one or two days, or none. The academy adds evenings on other days
 * (CounterShiftProposal from today, the teacher accepting it through
 * ApproveShiftProposal; a lesson already outside the hours stays as an
 * agreed exception), so the plans of two and three lessons a week can
 * start with each of them; and Sara, who gave Jana (13) her Tajweed trial,
 * teaches middle school too, so «ابدأ بهذه الباقة» on Jana's report starts
 * with her.
 *
 * Runs after the enrolments and before the shift proposals in the fresh
 * seeding, and in DemoCatchUpSeeder: once (a teacher whose week in force is
 * no longer the demo's first week, by the demo's owner or by this seeder, is
 * left as they are). On an older demo, Sara's and Mona's own open proposals
 * from DemoShiftProposalsSeeder (untouched) are withdrawn by them and told
 * again with these days kept; one the owner answered keeps the teacher as is.
 */
final class DemoOpenHoursSeeder extends Seeder
{
    /** The days added to each teacher's first week (DemoShiftsSeeder::WEEKS), on their own clock. */
    public const DAYS = [
        'sara' => [[1, '17:00', '21:00'], [4, '17:00', '21:00']],
        'nour' => [[7, '17:00', '21:00'], [1, '17:00', '21:00'], [3, '17:00', '21:00']],
        'mahmoud' => [[7, '16:00', '20:00'], [2, '16:00', '20:00']],
        'mona' => [[1, '17:00', '21:00'], [2, '17:00', '21:00']],
    ];

    public const NOTE = 'أضفنا أمسيات في أيام أخرى لنستقبل الطلاب الجدد بعد الحصص التجريبية.';

    /** The demo's own open proposals, as DemoShiftProposalsSeeder made them: told again with the new days kept. */
    private const STORIES = [
        'sara' => [DemoShiftProposalsSeeder::SARA_NOTE, ShiftProposalStatus::Submitted],
        'mona' => [DemoShiftProposalsSeeder::MONA_NOTE, ShiftProposalStatus::Countered],
    ];

    public function __construct(private readonly ShiftImpactQuery $impact) {}

    public function run(CounterShiftProposal $counter, ApproveShiftProposal $approve, WithdrawShiftProposal $withdraw, ResolveShiftConflict $resolve, ShiftProposals $proposals, PeopleApi $people): void
    {
        $user = fn (string $key) => DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $owner = $user('owner');
        if ($owner === null) {
            return;
        }
        $this->saraTeachesMiddleSchool($user('sara'), $people);

        foreach (self::DAYS as $key => $days) {
            $teacherId = $user($key);
            $schedule = $teacherId === null ? null : $this->firstWeek($teacherId, DemoShiftsSeeder::WEEKS[$key]);
            if ($schedule === null) {
                continue;
            }
            $open = $proposals->openOrDraft($teacherId);
            [$note, $status] = self::STORIES[$key] ?? [null, null];
            if ($open !== null && ($open->note !== $note || $open->status !== $status)) {
                continue;   // the teacher's own proposal, or one the academy answered: their week stays as it is
            }
            try {
                DB::transaction(function () use ($open, $teacherId, $schedule, $key, $days, $owner, $counter, $approve, $withdraw, $resolve) {
                    if ($open !== null) {
                        $withdraw->handle($open, $teacherId);
                    }
                    $tz = $schedule->timezone;
                    $proposal = $counter->handle($teacherId, DemoShiftsSeeder::week([...DemoShiftsSeeder::WEEKS[$key], ...$days], $tz),
                        CarbonImmutable::now($tz)->startOfDay(), self::NOTE, $owner);
                    if ($proposal->status !== ShiftProposalStatus::Approved) {
                        // Only days are added: a lesson already outside the hours (the live board's) stays where it is.
                        foreach ($this->impact->conflicts($teacherId, $proposal->shifts(), $proposal->effective_from) as $conflict) {
                            $proposal = $resolve->handle($proposal, $conflict['key'], 'exception', $owner);
                        }
                        $approve->handle($proposal->refresh(), $teacherId, 'teacher');
                    }
                    if ($open !== null) {
                        $story = app(DemoShiftProposalsSeeder::class);
                        $key === 'sara' ? $story->sara() : $story->mona();
                    }
                });
            } catch (ValidationException) {
                continue;   // an academy that approves at once, with a lesson outside the hours: as it is
            }
        }
    }

    /** The week in force from today on, while it is still the demo's first week (its only version: nothing approved since or ahead). */
    private function firstWeek(string $teacherId, array $blocks): ?ShiftSchedule
    {
        $versions = ShiftSchedule::query()->where('teacher_id', $teacherId)->where('status', 'active')->with('rules')->get();
        if ($versions->count() !== 1) {
            return null;
        }
        $first = $versions->first();
        $open = ShiftSchedule::query()->whereKey($first->id)->whereNull('source_proposal_id')->whereRaw('upper_inf(effective) and lower(effective) <= current_date')->exists();

        return $open && self::same($first->shifts(), DemoShiftsSeeder::week($blocks, $first->timezone)) ? $first : null;
    }

    private static function same(WeeklyShifts $a, WeeklyShifts $b): bool
    {
        $key = function (WeeklyShifts $w): array {
            $rows = array_map(fn (ShiftBlock $x) => $x->weekday.'|'.$x->start().'|'.$x->durationMin, $w->blocks);
            sort($rows);

            return $rows;
        };

        return $key($a) === $key($b);
    }

    /** Sara takes middle school (Jana is 13), unless the demo's owner changed her ages. */
    private function saraTeachesMiddleSchool(?string $saraId, PeopleApi $people): void
    {
        $profile = $saraId === null ? null : DB::table('teacher_profiles')->where('user_id', $saraId)->first();
        if ($profile === null || json_decode((string) $profile->age_groups, true) !== ['teen', 'adults']) {
            return;
        }
        $people->saveTeacherProfile(new TeacherProfileData(
            $saraId, (string) $profile->gender, json_decode((string) $profile->languages, true),
            DB::table('teacher_subjects')->where('teacher_id', $saraId)->pluck('subject_id')->all(), $profile->dialect,
            (bool) $profile->accepts_new, (bool) $profile->accepts_trials, $profile->max_weekly_min === null ? null : (int) $profile->max_weekly_min,
            ['middle', 'teen', 'adults'], $profile->max_daily_lessons === null ? null : (int) $profile->max_daily_lessons,
        ));
    }
}
