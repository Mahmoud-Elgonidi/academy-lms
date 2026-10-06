<?php

namespace Modules\Crm\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;

/** A call, a WhatsApp message or a note on a lead. The first contact moves a new lead to "contacted". */
final class LogLeadContact
{
    public function handle(Lead $lead, string $kind, ?string $body, ?string $actorId): LeadActivity
    {
        $body = blank($body) ? null : trim((string) $body);
        if (! in_array($kind, LeadActivity::CONTACT_KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __('crm::leads.errors.kind')]);
        }
        if ($kind === 'note' && $body === null) {
            throw ValidationException::withMessages(['body' => __('crm::leads.errors.note')]);
        }
        if ($body !== null && mb_strlen($body) > 2000) {
            throw ValidationException::withMessages(['body' => __('crm::leads.errors.too_long')]);
        }

        return DB::transaction(function () use ($lead, $kind, $body, $actorId) {
            $activity = LeadActivity::record($lead, $kind, $body, [], $actorId);
            if ($kind !== 'note') {
                $lead->forceFill(['last_contact_at' => CarbonImmutable::now()])->save();
                if ($lead->stage === LeadStage::New) {
                    $lead->moveTo(LeadStage::Contacted, $actorId);
                }
            }

            return $activity;
        });
    }
}
