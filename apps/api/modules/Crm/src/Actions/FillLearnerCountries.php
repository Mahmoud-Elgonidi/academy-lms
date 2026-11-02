<?php

namespace Modules\Crm\Actions;

use Modules\Crm\Models\Lead;
use Modules\People\Contracts\PeopleApi;

/**
 * Catches up the learners and parents the CRM made before PrepareLearner
 * gave them the lead's country: each takes it from their lead, only where
 * they have none (People's fillCountry), so the family is priced where it
 * lives on the report, the start flow and its invoice alike. The oldest lead
 * first, so a family that asked twice takes the country it first gave.
 * Run once per academy (crm:learner-countries, from its migration) and for
 * the demo (DemoCatchUpSeeder).
 *
 * @return int how many profiles took a country
 */
final class FillLearnerCountries
{
    public function __construct(private readonly PeopleApi $people) {}

    public function handle(): int
    {
        $filled = 0;
        Lead::query()->whereNotNull('learner_id')->whereNotNull('country')->orderBy('created_at')->orderBy('id')
            ->each(function (Lead $lead) use (&$filled) {
                foreach (array_unique(array_filter([$lead->learner_id, $lead->contact_id])) as $person) {
                    $filled += $this->people->fillCountry((string) $person, $lead->country);
                }
            });

        return $filled;
    }
}
