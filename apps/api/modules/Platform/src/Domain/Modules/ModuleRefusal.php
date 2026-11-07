<?php

namespace Modules\Platform\Domain\Modules;

use Modules\SharedKernel\Plans\Feature;

/** Why an area cannot change as asked: paid (the plan's), needs (another area first), in_use (count things still hold it on). */
final readonly class ModuleRefusal
{
    public function __construct(
        public Feature $feature,
        public string $reason,
        public int $count = 0,
        public ?Feature $needs = null,
    ) {}
}
