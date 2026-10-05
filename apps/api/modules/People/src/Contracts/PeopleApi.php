<?php

namespace Modules\People\Contracts;

/** What other modules may ask of People. */
interface PeopleApi
{
    public function linkGuardian(string $guardianId, string $studentId, string $relationship = 'parent', bool $isPrimary = true): void;
}
