<?php

namespace Modules\People\Actions\Checks;

use Illuminate\Support\Facades\DB;
use Modules\People\Models\PeopleSettings;
use Modules\SharedKernel\Audit\AuditTrail;

/** Whether a teacher needs a verified criminal record check before teaching anyone under 18. In the activity log. */
final class SaveMinorCheckRule
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(bool $required): void
    {
        DB::transaction(function () use ($required) {
            $settings = PeopleSettings::query()->first() ?? new PeopleSettings(['consent_age' => 18]);
            if ($settings->exists && $settings->require_minor_check === $required) {
                return;
            }
            $settings->fill(['require_minor_check' => $required])->save();
            $this->audit->record($required ? 'people.minor_check_required' : 'people.minor_check_optional');
        });
    }
}
