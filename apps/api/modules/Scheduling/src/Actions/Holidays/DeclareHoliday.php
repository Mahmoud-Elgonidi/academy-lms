<?php

namespace Modules\Scheduling\Actions\Holidays;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Contracts\Events\HolidayDeclared;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * The academy closes on some days (Eid, a national day): local days on the
 * academy's clock (docs/architecture/02, section 1). Every booked lesson
 * starting in them is cancelled under rule 15 (the minutes come back), the
 * students' credit lasts as many days longer, and no lessons are generated
 * there afterwards (MaterializeSessions). Each person hears once, not once
 * a lesson (HolidayDeclared).
 */
final class DeclareHoliday
{
    public const MAX_DAYS = 31;

    public function __construct(
        private readonly LocalTimeResolver $resolver,
        private readonly TenantContext $tenants,
        private readonly CancelSession $cancel,
        private readonly EnrollmentApi $enrollments,
        private readonly CreditsApi $credits,
    ) {}

    /** @return Collection<int, Session> the booked lessons these days would cancel */
    public function affected(string $firstDay, string $lastDay): Collection
    {
        [$startsAt, $endsAt] = $this->bounds($firstDay, $lastDay);

        return Session::query()->where('status', SessionStatus::Scheduled->value)
            ->where('starts_at', '>=', $startsAt->max(CarbonImmutable::now()))->where('starts_at', '<', $endsAt)
            ->orderBy('starts_at')->get();
    }

    public function handle(string $name, string $firstDay, string $lastDay, ?string $actorId): AcademyHoliday
    {
        $l = 'scheduling::timeoff.holidays.errors.';
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => __($l.'name')]);
        }
        if ($lastDay < $firstDay) {
            throw ValidationException::withMessages(['last_day' => __($l.'dates')]);
        }
        if (CarbonImmutable::parse($firstDay)->diffInDays(CarbonImmutable::parse($lastDay)) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['last_day' => __($l.'too_long', ['days' => self::MAX_DAYS])]);
        }
        $tz = $this->timezone();
        if ($lastDay < CarbonImmutable::now($tz)->toDateString()) {
            throw ValidationException::withMessages(['first_day' => __($l.'past')]);
        }
        [$startsAt, $endsAt] = $this->bounds($firstDay, $lastDay);

        try {
            $holiday = DB::transaction(fn () => AcademyHoliday::query()->create([
                'name' => $name, 'timezone' => $tz, 'first_day' => $firstDay, 'last_day' => $lastDay,
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'created_by' => $actorId,
            ]));
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'academy_holidays_no_overlap')) {
                throw ValidationException::withMessages(['first_day' => __($l.'overlap')]);
            }

            throw $e;
        }

        // The block's validity gives the closed days back first (once per account), so the minutes coming
        // back can fund a later lesson; then each lesson in its own transaction, so one that changed
        // meanwhile (started, cancelled) does not stop the rest.
        $affected = $this->affected($firstDay, $lastDay);
        $accounts = $affected->pluck('enrollment_id')->filter()->unique()
            ->map(fn (string $id) => $this->enrollments->find($id)?->creditAccountId)->filter()->unique();
        foreach ($accounts as $account) {
            $this->credits->extend($account, $holiday->days(), "holiday:{$holiday->id}:{$account}", $actorId, 'holiday');
        }

        $cancelled = [];
        foreach ($affected as $session) {
            try {
                $this->cancel->handle($session, 'system', 'holiday', $name, $actorId);
                $cancelled[] = $session->id;
            } catch (ValidationException) {
                continue;
            }
        }

        $holiday->forceFill(['cancelled_count' => count($cancelled)])->save();
        event(new HolidayDeclared((string) $this->tenants->id(), $holiday->id, $cancelled, $actorId));

        return $holiday;
    }

    /** @return array{CarbonImmutable, CarbonImmutable} from the first day's midnight to the midnight after the last, on the academy's clock */
    public function bounds(string $firstDay, string $lastDay): array
    {
        $tz = $this->timezone();

        return [
            $this->resolver->resolve($firstDay, '00:00', $tz)->utc,
            $this->resolver->resolve(CarbonImmutable::parse($lastDay)->addDay()->toDateString(), '00:00', $tz)->utc,
        ];
    }

    public function timezone(): string
    {
        return $this->tenants->current()?->defaultTimezone ?? 'UTC';
    }
}
