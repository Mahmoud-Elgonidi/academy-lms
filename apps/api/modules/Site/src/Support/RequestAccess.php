<?php

namespace Modules\Site\Support;

use Illuminate\Support\Facades\Gate;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;

/**
 * Who answers which requests from the academy's page: a trial lesson is the
 * sales team's (leads.manage), a teacher asking to teach is who runs the
 * teachers' (teachers.manage). An academy whose plan leaves the CRM out has
 * no one with leads.manage, so its trial requests are its owner's and
 * admins' (site.manage): a family's request never waits where nobody sees it.
 */
final class RequestAccess
{
    public function __construct(private readonly AcademyPlan $plan, private readonly IdentityApi $identity) {}

    /** @return list<string> the kinds the person signed in may see and answer */
    public function kinds(): array
    {
        $kinds = [];
        foreach (['trial', 'teach'] as $kind) {
            if (Gate::allows($this->ability($kind)->value)) {
                $kinds[] = $kind;
            }
        }

        return $kinds;
    }

    public function may(string $kind): bool
    {
        return in_array($kind, $this->kinds(), true);
    }

    /** @return list<string> the users told of a new request of this kind */
    public function recipients(string $kind): array
    {
        return $this->identity->usersWithAbility($this->ability($kind));
    }

    private function ability(string $kind): Ability
    {
        return match ($kind) {
            'teach' => Ability::ManageTeachers,
            default => $this->plan->has(Feature::Crm) ? Ability::ManageLeads : Ability::ManageSite,
        };
    }
}
