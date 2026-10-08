<?php

namespace Modules\People\Actions\Consent;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Models\PeopleSettings;
use Modules\SharedKernel\Audit\AuditTrail;

/** Under which age the academy asks a guardian to consent: 18, or 16 or 13 where its law allows. In the activity log. */
final class SaveConsentAge
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(int $age): void
    {
        if (! in_array($age, PeopleSettings::CONSENT_AGES, true)) {
            throw ValidationException::withMessages(['consent_age' => __('validation.in', ['attribute' => __('people::consent.office.settings')])]);
        }
        DB::transaction(function () use ($age) {
            $settings = PeopleSettings::query()->first() ?? new PeopleSettings;
            if ($settings->exists && $settings->consent_age === $age) {
                return;
            }
            $settings->fill(['consent_age' => $age])->save();
            $this->audit->record('people.consent_age_saved', null, null, ['age' => $age]);
        });
    }
}
