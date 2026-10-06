<?php

namespace Modules\Scheduling\Domain\Policy\Conditions;

use Modules\Scheduling\Domain\Policy\PolicyInput;
use Modules\Scheduling\Domain\Policy\PolicySettings;

/** The student still has cancellations or reschedules left in this block (or, with $left false, has none). */
final readonly class QuotaLeft implements Condition
{
    public function __construct(private bool $left = true) {}

    public function isSatisfiedBy(PolicyInput $input, PolicySettings $settings): bool
    {
        return ($input->quotaUsed < $settings->get('quota_per_block')) === $this->left;
    }

    public function describe(PolicySettings $settings): array
    {
        return [$this->left ? 'quota_left' : 'quota_used_up', ['n' => $settings->get('quota_per_block')]];
    }
}
