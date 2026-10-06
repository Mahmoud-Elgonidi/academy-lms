<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Contracts\Events\LeadLost;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\SharedKernel\Tenancy\TenantContext;

/** Closes an open lead as lost, with why; ReopenLead takes it back. */
final class MarkLeadLost
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function handle(Lead $lead, string $reason, ?string $note, ?string $actorId): Lead
    {
        if (! $lead->stage->isOpen()) {
            throw ValidationException::withMessages(['lead' => __('crm::leads.errors.closed')]);
        }
        if (! in_array($reason, Lead::LOST_REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __('crm::leads.errors.reason')]);
        }

        return DB::transaction(function () use ($lead, $reason, $note, $actorId) {
            $lead->forceFill(['lost_reason' => $reason])->save();
            $lead->moveTo(LeadStage::Lost, $actorId, log: false);
            LeadActivity::record($lead, 'lost', blank($note) ? null : trim($note), ['reason' => $reason], $actorId);
            $academyId = $this->tenants->id();
            DB::afterCommit(fn () => event(new LeadLost($academyId, $lead->id, $reason, $actorId)));

            return $lead;
        });
    }
}
