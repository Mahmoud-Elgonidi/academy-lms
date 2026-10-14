<?php

namespace Modules\SharedKernel\Setup;

/**
 * One reading of the checklist. A step's done() is asked the first time
 * something needs it and kept for this reading only, so a page that shows
 * the progress in several places (the menu's badge, the page) reads each
 * step once; complete() stops at the first required step not done.
 */
final class SetupProgress
{
    /** @var array<string, bool> */
    private array $done = [];

    /** @param  list<SetupStep>  $steps  in display order */
    public function __construct(private readonly array $steps) {}

    /** @return list<SetupStep> */
    public function steps(): array
    {
        return $this->steps;
    }

    public function isDone(SetupStep $step): bool
    {
        return $this->done[$step->key()] ??= $step->done();
    }

    /** @return list<SetupStep> what must be ready before families come */
    public function required(): array
    {
        return array_values(array_filter($this->steps, fn (SetupStep $step) => $step->required()));
    }

    /** @return list<SetupStep> what helps, but can wait */
    public function optional(): array
    {
        return array_values(array_filter($this->steps, fn (SetupStep $step) => ! $step->required()));
    }

    /** Whether every required step is done, reading no further than the first that is not. */
    public function complete(): bool
    {
        foreach ($this->required() as $step) {
            if (! $this->isDone($step)) {
                return false;
            }
        }

        return true;
    }

    public function requiredDone(): int
    {
        return count(array_filter($this->required(), fn (SetupStep $step) => $this->isDone($step)));
    }

    public function requiredLeft(): int
    {
        return count($this->required()) - $this->requiredDone();
    }

    /** The share of required steps done, 0 to 100 (100 with none to do). */
    public function percent(): int
    {
        $total = count($this->required());

        return $total === 0 ? 100 : intdiv($this->requiredDone() * 100, $total);
    }
}
