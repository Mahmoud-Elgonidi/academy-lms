<?php

namespace Modules\Scheduling\Domain\Policy;

use Closure;
use Modules\Scheduling\Domain\Policy\Conditions\Condition;

/** An event, conditions that must all hold, and what follows (Specification + Strategy, not a rules engine). */
final readonly class Rule
{
    /**
     * @param  list<Condition>  $conditions
     * @param  Closure(PolicySettings, PolicyInput): Decision  $outcome
     */
    public function __construct(
        public int $number,
        public PolicyEvent $event,
        public array $conditions,
        private Closure $outcome,
    ) {}

    public function matches(PolicyInput $input, PolicySettings $settings): bool
    {
        if ($input->event !== $this->event) {
            return false;
        }
        foreach ($this->conditions as $condition) {
            if (! $condition->isSatisfiedBy($input, $settings)) {
                return false;
            }
        }

        return true;
    }

    public function decide(PolicySettings $settings, PolicyInput $input): Decision
    {
        return ($this->outcome)($settings, $input);
    }
}
