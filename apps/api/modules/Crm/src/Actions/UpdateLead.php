<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Support\LeadFields;

/** Corrects a lead's details; what changed goes on its timeline. Accounts already made for the learner keep theirs. */
final class UpdateLead
{
    /** @param  array<string, mixed>  $data */
    public function handle(Lead $lead, array $data, ?string $actorId): Lead
    {
        $fields = LeadFields::validate($data);

        return DB::transaction(function () use ($lead, $fields, $actorId) {
            $lead->fill($fields);
            $changed = array_keys($lead->getDirty());
            if ($changed !== []) {
                $lead->save();
                LeadActivity::record($lead, 'edited', null, ['fields' => $changed], $actorId);
            }

            return $lead;
        });
    }
}
