<?php

namespace Modules\Scheduling\Actions\Shifts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Models\ShiftProposalComment;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\ProposalRules;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Clock;

/**
 * What every shift action shares: the teacher's timezone and current
 * schedule, the academy's rules, one lock per teacher, and comments.
 */
final class ShiftProposals
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function settings(): SchedulingSettings
    {
        return SchedulingSettings::current();
    }

    public function rules(): ProposalRules
    {
        $settings = $this->settings();

        return new ProposalRules($settings->min_block_min, $settings->reduction_notice_days);
    }

    public function teacherTimezone(string $teacherId): string
    {
        return DB::table('users')->where('id', $teacherId)->value('timezone') ?? $this->tenants->require()->defaultTimezone;
    }

    /** The schedule in force on $date, or an empty week. */
    public function scheduleOn(string $teacherId, CarbonImmutable $date, string $timezone): WeeklyShifts
    {
        return ShiftSchedule::inForce($teacherId, $date)?->shifts() ?? WeeklyShifts::empty($timezone);
    }

    /** Throws a validation error listing every problem, or returns quietly. */
    public function assertValid(string $teacherId, WeeklyShifts $shifts, CarbonImmutable $effectiveFrom, ?int $min, ?int $max): void
    {
        $today = CarbonImmutable::now($shifts->timezone)->startOfDay();
        $problems = $this->rules()->check(
            $shifts,
            $effectiveFrom,
            $this->scheduleOn($teacherId, $effectiveFrom, $shifts->timezone),
            $today,
            $min,
            $max,
            DB::table('teacher_profiles')->where('user_id', $teacherId)->value('max_weekly_min'),
        );

        if ($problems !== []) {
            throw ValidationException::withMessages(['shifts' => array_map(
                fn (array $p) => __('scheduling::shifts.problems.'.$p[0], self::readable($p[1])),
                $problems,
            )]);
        }
    }

    /** The teacher's proposal that is open or still a draft, if any. */
    public function openOrDraft(string $teacherId): ?ShiftProposal
    {
        return ShiftProposal::query()
            ->where('teacher_id', $teacherId)
            ->whereIn('status', ['draft', 'submitted', 'changes_requested', 'countered'])
            ->latest()
            ->first();
    }

    /** Serialises shift changes per teacher across coordinators and the teacher. */
    public function lock(string $teacherId): void
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['shifts:'.$teacherId]);
    }

    public function comment(ShiftProposal $proposal, ?string $authorId, string $role, ?string $body): void
    {
        $body = $body === null ? '' : trim($body);
        if ($body !== '') {
            ShiftProposalComment::query()->create([
                'proposal_id' => $proposal->id,
                'author_id' => $authorId,
                'author_role' => $role,
                'body' => mb_substr($body, 0, 2000),
            ]);
        }
    }

    /** @param  array<string, mixed>  $params */
    private static function readable(array $params): array
    {
        if (isset($params['day'])) {
            $params['day'] = __('scheduling::shifts.days.'.$params['day']);
        }
        if (isset($params['start'])) {
            $params['start'] = Clock::wall((string) $params['start']);
        }

        return $params;
    }
}
