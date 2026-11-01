<?php

namespace Modules\Crm\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Crm\Models\CrmSettings;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Whoever manages leads (leads.manage) chooses whether leads from the
 * academy's page are handed out in turn among those who work leads, and
 * whether a family may book a free second trial with another teacher from
 * its trial's report (docs/architecture/02, section 27, J2; left as it is
 * when not given). Audited with the settings' states.
 */
final class SaveCrmSettings
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(bool $assignWebsiteLeads, ?bool $secondTrial = null): CrmSettings
    {
        if (! Gate::allows(Ability::ManageLeads->value)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($assignWebsiteLeads, $secondTrial) {
            $row = CrmSettings::query()->lockForUpdate()->first() ?? new CrmSettings;
            $secondBefore = $row->second_trial;
            $row->fill(['assign_website_leads' => $assignWebsiteLeads, 'updated_by' => auth()->id()] + ($secondTrial === null ? [] : ['second_trial' => $secondTrial]))->save();
            $this->audit->record('crm.settings_saved', 'crm_settings', null, ['assign_website_leads' => $assignWebsiteLeads ? 'on' : 'off']);
            if ($secondTrial !== null && $secondTrial !== $secondBefore) {
                $this->audit->record('crm.second_trial_saved', 'crm_settings', null, ['state' => $secondTrial ? 'on' : 'off']);
            }

            return $row;
        });
    }
}
