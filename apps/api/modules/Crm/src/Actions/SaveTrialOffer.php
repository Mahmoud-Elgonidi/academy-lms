<?php

namespace Modules\Crm\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Domain\TrialOfferRules;
use Modules\Crm\Models\CrmSettings;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Whoever manages leads (leads.manage) chooses the academy's offer after a
 * free trial (docs/architecture/02, section 27, J2): on or off, its
 * percentage off the first invoice (5–50) and how long it stays open
 * (24–336 hours). Offers already made keep what they promised. Audited with
 * the state, the percentage and the hours.
 */
final class SaveTrialOffer
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(bool $on, int $percent, int $hours): CrmSettings
    {
        if (! Gate::allows(Ability::ManageLeads->value)) {
            throw new AuthorizationException;
        }
        $invalid = TrialOfferRules::invalid($percent, $hours);
        if ($invalid !== []) {
            throw ValidationException::withMessages(array_combine(
                array_map(fn (string $f) => 'trial_offer_'.$f, $invalid),
                array_map(fn (string $f) => __('crm::leads.settings.errors.'.$f, ['min' => $f === 'percent' ? TrialOfferRules::MIN_PERCENT : TrialOfferRules::MIN_HOURS,
                    'max' => $f === 'percent' ? TrialOfferRules::MAX_PERCENT : TrialOfferRules::MAX_HOURS]), $invalid),
            ));
        }

        return DB::transaction(function () use ($on, $percent, $hours) {
            $row = CrmSettings::query()->lockForUpdate()->first() ?? new CrmSettings;
            $row->fill(['trial_offer' => $on, 'trial_offer_percent' => $percent, 'trial_offer_hours' => $hours, 'updated_by' => auth()->id()])->save();
            $this->audit->record('crm.trial_offer_saved', 'crm_settings', null, ['state' => $on ? 'on' : 'off', 'percent' => $percent, 'hours' => $hours]);

            return $row;
        });
    }
}
