<?php

namespace Modules\Scheduling\Domain\Policy\Conditions;

use Modules\Scheduling\Domain\Policy\PolicyInput;
use Modules\Scheduling\Domain\Policy\PolicySettings;

/** One condition of a rule (Specification, docs/architecture/04, section 4.5). */
interface Condition
{
    public function isSatisfiedBy(PolicyInput $input, PolicySettings $settings): bool;

    /** @return array{0: string, 1: array<string, int|string>} a translation key and its values, for the rule table */
    public function describe(PolicySettings $settings): array;
}
