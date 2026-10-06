<?php

namespace Modules\People\Contracts\DTOs;

use Carbon\CarbonImmutable;
use Modules\People\Contracts\LearnerStage;

/** A student's age and the look of their area: the one chosen, or the one their age gives. */
final readonly class StudentProfileData
{
    public function __construct(
        public string $studentId,
        public ?CarbonImmutable $birthDate,
        public ?LearnerStage $chosenStage,
        public LearnerStage $stage,
        public ?int $age,
    ) {}
}
