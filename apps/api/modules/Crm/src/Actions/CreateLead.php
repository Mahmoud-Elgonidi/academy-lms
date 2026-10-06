<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Contracts\Events\LeadCreated;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Support\LeadFields;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A new lead (docs/architecture/01, section 14): who, how to reach them,
 * what and when they want, where they came from, and who follows them (the
 * one who adds it, unless someone else is named). The same number twice
 * while the first is still open is refused, with the name it is under.
 */
final class CreateLead
{
    public function __construct(private readonly AssignLead $assign, private readonly TenantContext $tenants) {}

    /** @param  array<string, mixed>  $data */
    public function handle(array $data, ?string $actorId, ?string $ownerId = null): Lead
    {
        $fields = LeadFields::validate($data);
        $open = Lead::query()->where('phone_e164', $fields['phone_e164'])
            ->whereIn('stage', array_map(fn (LeadStage $s) => $s->value, LeadStage::OPEN))->first();
        if ($open !== null) {
            throw ValidationException::withMessages(['phone_e164' => __('crm::leads.errors.duplicate', ['name' => $open->contact_name])]);
        }
        $ownerId ??= $actorId;
        if ($ownerId !== null) {
            $this->assign->check($ownerId);
        }

        return DB::transaction(function () use ($fields, $actorId, $ownerId) {
            $lead = Lead::query()->create($fields + ['owner_id' => $ownerId, 'created_by' => $actorId]);
            LeadActivity::record($lead, 'created', null, ['source' => $lead->source->value], $actorId);
            $academyId = $this->tenants->id();
            DB::afterCommit(fn () => event(new LeadCreated($academyId, $lead->id, $ownerId, $actorId)));

            return $lead;
        });
    }
}
