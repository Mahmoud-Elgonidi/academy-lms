<?php

namespace Modules\People\Checks;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\CheckKind;
use Modules\People\Contracts\DTOs\TeacherClearance;
use Modules\People\Models\PeopleSettings;
use Modules\People\Models\TeacherCheck;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whether teachers may take new lessons with students under 18
 * (docs/architecture/02, section 15): a pause for a safeguarding concern
 * always keeps them away; otherwise, when the academy asks for a check, they
 * need a verified criminal record check that has not run out on the
 * academy's calendar. One read for many teachers.
 */
final class Clearances
{
    public function __construct(private readonly TenantContext $tenants) {}

    /** Today on the academy's calendar. */
    public function today(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->setTimezone($this->tenants->require()->defaultTimezone)->startOfDay();
    }

    /** @param  bool  $withPause  false: where their documents alone stand (what the teacher sees; a pause is the academy's to explain) */
    public function of(string $teacherId, ?CarbonImmutable $now = null, bool $withPause = true): TeacherClearance
    {
        return $this->many([$teacherId], $now, $withPause)[$teacherId];
    }

    /**
     * @param  list<string>  $teacherIds
     * @return array<string, TeacherClearance>
     */
    public function many(array $teacherIds, ?CarbonImmutable $now = null, bool $withPause = true): array
    {
        $teacherIds = array_values(array_unique($teacherIds));
        if ($teacherIds === []) {
            return [];
        }
        $required = PeopleSettings::current()->require_minor_check;
        $today = $this->today($now);
        $paused = $withPause ? DB::table('teacher_profiles')->whereIn('user_id', $teacherIds)->whereNotNull('minors_paused_at')->pluck('user_id')->flip() : collect();
        $checks = TeacherCheck::query()->whereIn('teacher_id', $teacherIds)->where('kind', CheckKind::CriminalRecord->value)
            ->whereIn('status', ['verified', 'pending'])->get(['teacher_id', 'status', 'expires_on'])->groupBy('teacher_id');

        $clearances = [];
        foreach ($teacherIds as $id) {
            /** @var Collection<int, TeacherCheck> $own */
            $own = $checks->get($id, collect());
            $valid = $own->filter(fn (TeacherCheck $c) => $c->isValidOn($today));
            // The furthest day a valid check covers (none: one with no end date).
            $until = $valid->isEmpty() || $valid->contains(fn (TeacherCheck $c) => $c->expires_on === null)
                ? null : $valid->max(fn (TeacherCheck $c) => $c->expires_on);
            [$cleared, $state] = match (true) {
                isset($paused[$id]) => [false, TeacherClearance::PAUSED],
                ! $required => [true, TeacherClearance::NOT_REQUIRED],
                $valid->isNotEmpty() => [true, TeacherClearance::VERIFIED],
                $own->contains('status', 'pending') => [false, TeacherClearance::PENDING],
                $own->contains('status', 'verified') => [false, TeacherClearance::EXPIRED],
                default => [false, TeacherClearance::MISSING],
            };
            $clearances[$id] = new TeacherClearance($id, $cleared, $state, $required, $valid->isNotEmpty() ? $until : null);
        }

        return $clearances;
    }
}
