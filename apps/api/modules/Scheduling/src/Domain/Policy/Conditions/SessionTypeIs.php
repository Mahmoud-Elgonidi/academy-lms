<?php

namespace Modules\Scheduling\Domain\Policy\Conditions;

use Modules\Scheduling\Domain\Policy\PolicyInput;
use Modules\Scheduling\Domain\Policy\PolicySettings;

final readonly class SessionTypeIs implements Condition
{
    public function __construct(private string $type) {}

    public function isSatisfiedBy(PolicyInput $input, PolicySettings $settings): bool
    {
        return $input->sessionType === $this->type;
    }

    public function describe(PolicySettings $settings): array
    {
        return ['session_type', ['type' => $this->type]];
    }
}
