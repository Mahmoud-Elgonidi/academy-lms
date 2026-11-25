<?php

namespace Modules\Curriculum\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Lessons left a published version (DeleteNode, after commit): what follows the version counts it again. */
final class LessonsRemoved
{
    use Dispatchable;

    /** @param  list<string>  $nodeIds  the levels, units and lessons removed */
    public function __construct(
        public readonly string $versionId,
        public readonly array $nodeIds,
    ) {}
}
