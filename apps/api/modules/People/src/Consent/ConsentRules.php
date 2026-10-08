<?php

namespace Modules\People\Consent;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\DTOs\ConsentState;
use Modules\People\Models\PeopleSettings;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whose account needs a guardian's consent (docs/architecture/02, "موافقة
 * ولي الأمر"): a student younger than the academy's consent age (18 unless it
 * chose 16 or 13) on their birthday in the academy's calendar; with no birth
 * date, a student the academy manages for a guardian (no contact of their
 * own) or who has a guardian at all. Teachers, staff and guardians never do.
 */
final class ConsentRules
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function consentAge(): int
    {
        return PeopleSettings::current()->consent_age;
    }

    public function state(string $userId, ?CarbonImmutable $now = null): ConsentState
    {
        return $this->states([$userId], $now)[$userId] ?? new ConsentState($userId, false, false);
    }

    /**
     * @param  list<string>  $userIds
     * @return array<string, ConsentState>
     */
    public function states(array $userIds, ?CarbonImmutable $now = null): array
    {
        if ($userIds === []) {
            return [];
        }
        $today = ($now ?? CarbonImmutable::now())->setTimezone($this->tenants->require()->defaultTimezone)->startOfDay();
        $age = $this->consentAge();
        $people = DB::table('users as u')
            ->leftJoin('student_profiles as sp', fn ($j) => $j->on('sp.user_id', '=', 'u.id')->on('sp.academy_id', '=', 'u.academy_id'))
            ->whereIn('u.id', $userIds)
            ->select('u.id', 'u.is_managed_minor', 'sp.birth_date')
            ->selectRaw("exists (select 1 from user_roles r where r.academy_id = u.academy_id and r.user_id = u.id and r.role = 'student') as is_student")
            ->selectRaw('exists (select 1 from guardian_student g where g.academy_id = u.academy_id and g.student_id = u.id) as has_guardian')
            ->get();
        $consents = DB::table('guardian_consents as c')
            ->leftJoin('users as g', fn ($j) => $j->on('g.id', '=', 'c.guardian_id')->on('g.academy_id', '=', 'c.academy_id'))
            ->whereIn('c.student_id', $userIds)->whereNull('c.withdrawn_at')->where('c.purpose', 'account')
            ->get(['c.id', 'c.student_id', 'c.guardian_id', 'c.via', 'c.granted_at', 'g.name as guardian_name'])
            ->keyBy('student_id');

        $states = [];
        foreach ($people as $person) {
            $needed = (bool) $person->is_student && ($person->birth_date !== null
                ? CarbonImmutable::parse($person->birth_date, $today->timezone)->addYears($age)->greaterThan($today)
                : ((bool) $person->is_managed_minor || (bool) $person->has_guardian));
            $consent = $consents->get($person->id);
            $states[$person->id] = new ConsentState(
                studentId: $person->id,
                needed: $needed,
                granted: $consent !== null,
                consentId: $consent?->id,
                guardianId: $consent?->guardian_id,
                guardianName: $consent?->guardian_name,
                via: $consent?->via,
                grantedAt: $consent ? CarbonImmutable::parse($consent->granted_at) : null,
            );
        }

        return $states;
    }
}
