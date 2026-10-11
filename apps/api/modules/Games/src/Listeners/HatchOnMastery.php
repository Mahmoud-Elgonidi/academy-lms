<?php

namespace Modules\Games\Listeners;

use Modules\Games\Actions\HatchCreature;
use Modules\Learning\Contracts\Events\NodeMastered;

/** A lesson the teacher marked mastered hatches the learner's next creature. */
final class HatchOnMastery
{
    public function __construct(private readonly HatchCreature $hatch) {}

    public function handle(NodeMastered $event): void
    {
        $this->hatch->byMastery($event->studentId, $event->nodeId);
    }
}
