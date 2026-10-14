<?php

namespace Modules\SharedKernel\Setup;

use Illuminate\Contracts\Container\Container;

/**
 * The steps of a new academy's setup, registered by the modules in their
 * providers (`app(SetupChecklist::class)->add(MyStep::class)`), the same way
 * as PersonalData: whoever shows the checklist reads it here and knows no
 * module.
 */
final class SetupChecklist
{
    /** @var list<class-string<SetupStep>> */
    private array $steps = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<SetupStep>  $step */
    public function add(string $step): void
    {
        if (! in_array($step, $this->steps, true)) {
            $this->steps[] = $step;
        }
    }

    /** @return list<SetupStep> by group (SetupGroup's order), then each one's order, then key */
    public function steps(): array
    {
        $all = array_map(fn (string $class): SetupStep => $this->container->make($class), $this->steps);
        usort($all, fn (SetupStep $a, SetupStep $b) => [$a->group()->rank(), $a->order(), $a->key()] <=> [$b->group()->rank(), $b->order(), $b->key()]);

        return $all;
    }

    /** Where the academy stands now: a fresh reading, each step asked at most once and only when needed. */
    public function progress(): SetupProgress
    {
        return new SetupProgress($this->steps());
    }
}
