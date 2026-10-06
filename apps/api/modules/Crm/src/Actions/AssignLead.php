<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Contracts\Events\LeadAssigned;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;

/** Hands a lead to someone who works leads; they hear of it (unless they took it themselves). */
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

    public function check(string $ownerId): void
    {
        if (! in_array($ownerId, $this->identity->usersWithAbility(Ability::ManageLeads), true)) {
            throw ValidationException::withMessages(['owner_id' => __('crm::leads.errors.owner')]);
        }
    }
}
