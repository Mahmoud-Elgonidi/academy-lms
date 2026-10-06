<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;

/** A lost lead comes back (they called again): back to "contacted". */
final class ReopenLead
{
    public function handle(Lead $lead, ?string $actorId): Lead
    {
        if ($lead->stage !== LeadStage::Lost) {
            throw ValidationException::withMessages(['lead' => __('crm::leads.errors.not_lost')]);
        }
        $open = Lead::query()->where('phone_e164', $lead->phone_e164)->whereKeyNot($lead->id)
            ->whereIn('stage', array_map(fn (LeadStage $s) => $s->value, LeadStage::OPEN))->first();
        if ($open !== null) {
            throw ValidationException::withMessages(['lead' => __('crm::leads.errors.duplicate', ['name' => $open->contact_name])]);
        }

        return DB::transaction(function () use ($lead, $actorId) {
            $lead->forceFill(['lost_reason' => null])->save();
            $lead->moveTo(LeadStage::Contacted, $actorId, log: false);
            LeadActivity::record($lead, 'reopened', null, [], $actorId);

            return $lead;
        });
    }
}
