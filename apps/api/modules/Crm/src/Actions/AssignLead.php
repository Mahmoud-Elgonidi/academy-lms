<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Contracts\Events\LeadAssigned;
use Modules\Crm\Enums\LeadSource;
use Modules\Crm\Models\CrmSettings;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Hands a lead to someone who works leads; they hear of it (unless they took
 * it themselves). Leads from the academy's page go round in turn among
 * everyone who works leads (nextForWebsite), while the academy shares them
 * out (crm_settings.assign_website_leads, on at first).
 */
final class AssignLead
{
    public function __construct(private readonly IdentityApi $identity, private readonly TenantContext $tenants) {}

    public function handle(Lead $lead, string $ownerId, ?string $actorId): Lead
    {
        $this->check($ownerId);
        if ($lead->owner_id === $ownerId) {
            return $lead;
        }

        return DB::transaction(function () use ($lead, $ownerId, $actorId) {
            $lead->forceFill(['owner_id' => $ownerId])->save();
            LeadActivity::record($lead, 'assigned', null, ['to' => $ownerId], $actorId);
            $academyId = $this->tenants->id();
            DB::afterCommit(fn () => event(new LeadAssigned($academyId, $lead->id, $ownerId, $actorId)));

            return $lead;
        });
    }

    /**
     * Who gets the next lead from the academy's page: those who work leads, in
     * a fixed order, the one after whoever got the last such lead; null when
     * nobody works leads or the academy hands them out itself.
     */
    public function nextForWebsite(): ?string
    {
        if (! CrmSettings::current()->assign_website_leads) {
            return null;
        }
        $staff = $this->identity->usersWithAbility(Ability::ManageLeads);
        if ($staff === []) {
            return null;
        }
        sort($staff);
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['crm-assign:'.$this->tenants->id()]);
        $last = Lead::query()->where('source', LeadSource::Website->value)->whereIn('owner_id', $staff)
            ->latest('created_at')->latest('id')->value('owner_id');
        $at = $last === null ? false : array_search($last, $staff, true);

        return $at === false ? $staff[0] : $staff[($at + 1) % count($staff)];
    }

    public function check(string $ownerId): void
    {
        if (! in_array($ownerId, $this->identity->usersWithAbility(Ability::ManageLeads), true)) {
            throw ValidationException::withMessages(['owner_id' => __('crm::leads.errors.owner')]);
        }
    }
}
