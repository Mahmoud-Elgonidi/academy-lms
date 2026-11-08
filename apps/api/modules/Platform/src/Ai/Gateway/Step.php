<?php

namespace Modules\Platform\Ai\Gateway;

use Modules\Platform\Ai\Engines\EngineSpec;

/** One engine in a chain, with the tier it is asked at (null: the prompt's own). */
final readonly class Step
{
    public function __construct(
        public EngineSpec $engine,
        public ?string $tier = null,
    ) {}
}
