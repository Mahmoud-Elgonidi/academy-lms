<?php

namespace Modules\Scheduling\Actions\TimeOff;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\TimeOffDecided;
use Modules\Scheduling\Contracts\Events\TimeOffRequested;
use Modules\Scheduling\Domain\Enums\TimeOffStatus;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * Time off for a teacher (docs/architecture/01, flow "ز"): days on their own
 * clock, whole (midnight to midnight there) or some hours. The teacher asks
 * and the academy decides; a manager may also record it as granted (a sick
 * call). Nobody holds the same hours twice (the database's exclusion).
 */
final class RequestTimeOff
{
    public const MAX_DAYS = 60;

    public function __construct(
        private readonly LocalTimeResolver $resolver,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  string  $firstDay  Y-m-d on the teacher's clock
     * @param  string  $lastDay  Y-m-d, inclusive
     * @param  ?string  $from  H:i on the first day, with $to on the last: part of a day; both null: whole days
     */
    public function handle(string $teacherId, string $firstDay, string $lastDay, ?string $from, ?string $to, string $reason, ?string $note, string $actorId, bool $grant = false): TeacherTimeOff
    {
        $l = 'scheduling::timeoff.errors.';
        if (! in_array($reason, TeacherTimeOff::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __($l.'reason')]);
        }
        if (($from === null) !== ($to === null) || $lastDay < $firstDay) {
            throw ValidationException::withMessages(['last_day' => __($l.'dates')]);
        }

        $tz = $this->timezone($teacherId);
        $allDay = $from === null;
        [$startsAt, $endsAt] = $this->bounds($tz, $firstDay, $lastDay, $from, $to);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['to' => __($l.'dates')]);
        }
        if (CarbonImmutable::parse($firstDay)->diffInDays(CarbonImmutable::parse($lastDay)) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['last_day' => __($l.'too_long', ['days' => self::MAX_DAYS])]);
        }
        // A teacher asks from today on; the academy may record a leave that already began, not one that is over.
        $today = CarbonImmutable::now($tz)->toDateString();
        if ($endsAt->isPast() || (! $grant && $firstDay < $today)) {
            throw ValidationException::withMessages(['first_day' => __($l.'past')]);
        }

        try {
            $timeOff = DB::transaction(fn () => TeacherTimeOff::query()->create([
                'teacher_id' => $teacherId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $tz,
                'all_day' => $allDay,
                'reason' => $reason,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'status' => $grant ? TimeOffStatus::Approved : TimeOffStatus::Pending,
                'requested_by' => $actorId,
                'decided_by' => $grant ? $actorId : null,
                'decided_at' => $grant ? CarbonImmutable::now() : null,
            ]));
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'teacher_time_off_no_overlap')) {
                throw ValidationException::withMessages(['first_day' => __($l.'overlap')]);
            }

            throw $e;
        }

        $academyId = (string) $this->tenants->id();
        DB::afterCommit(fn () => event($grant
            ? new TimeOffDecided($academyId, $timeOff->id, $teacherId, TimeOffStatus::Approved->value, $actorId)
            : new TimeOffRequested($academyId, $timeOff->id, $teacherId, $actorId)));

        return $timeOff;
    }

    /**
     * The instants on the teacher's clock: whole days run midnight to the midnight after the last day there.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function bounds(string $tz, string $firstDay, string $lastDay, ?string $from = null, ?string $to = null): array
    {
        return [
            $this->resolver->resolve($firstDay, $from ?? '00:00', $tz)->utc,
            $from === null
                ? $this->resolver->resolve(CarbonImmutable::parse($lastDay)->addDay()->toDateString(), '00:00', $tz)->utc
                : $this->resolver->resolve($lastDay, (string) $to, $tz)->utc,
        ];
    }

    /** The teacher's own clock: their profile, else the academy's. */
    public function timezone(string $teacherId): string
    {
        return DB::table('users')->where('id', $teacherId)->value('timezone')
            ?? $this->tenants->current()?->defaultTimezone ?? 'UTC';
    }
}
