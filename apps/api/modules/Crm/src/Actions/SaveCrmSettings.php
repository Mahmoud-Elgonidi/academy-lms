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
 * academy's page are handed out in turn among those who work leads. Audited
 * with the setting's state.
 */
final class SaveCrmSettings
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(bool $assignWebsiteLeads): CrmSettings
    {
        if (! Gate::allows(Ability::ManageLeads->value)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($assignWebsiteLeads) {
            $row = CrmSettings::query()->lockForUpdate()->first() ?? new CrmSettings;
            $row->fill(['assign_website_leads' => $assignWebsiteLeads, 'updated_by' => auth()->id()])->save();
            $this->audit->record('crm.settings_saved', 'crm_settings', null, ['assign_website_leads' => $assignWebsiteLeads ? 'on' : 'off']);

            return $row;
        });
    }
}
