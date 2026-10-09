<?php

namespace Modules\Privacy\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Modules\Privacy\Models\PrivacySettings;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;

/** Who the academy is on its privacy notice and terms, and its own words added to them. In the audit log. */
final class SavePrivacySettings
{
    public function __construct(private readonly AuditTrail $audit) {}

    /** @param  array{legal_name?: ?string, contact_email?: ?string, address?: ?string, privacy_extra?: ?string, terms_extra?: ?string}  $data */
    public function handle(array $data, string $actorId): PrivacySettings
    {
        if (! Gate::allows(Ability::HandleDataRequests->value)) {
            throw new AuthorizationException;
        }
        $clean = array_map(fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null, array_intersect_key($data, array_flip(['legal_name', 'contact_email', 'address', 'privacy_extra', 'terms_extra'])));
        Validator::make($clean, [
            'legal_name' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'address' => ['nullable', 'string', 'max:300'],
            'privacy_extra' => ['nullable', 'string', 'max:5000'],
            'terms_extra' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $settings = PrivacySettings::current();
        $settings->fill($clean + ['updated_by' => $actorId])->save();
        $this->audit->record('privacy.settings_saved', null, null, ['fields' => array_keys(array_filter($clean))]);

        return $settings;
    }
}
