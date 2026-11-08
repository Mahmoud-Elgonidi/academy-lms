<?php

namespace Modules\Platform\Ai\Gateway;

/** The chain a request goes down, where it came from (academy:…, plan:…, default, settings), and whether it is the split's second chain. */
final readonly class ResolvedRoute
{
    /** @param  list<Step>  $steps */
    public function __construct(
        public array $steps,
        public string $scope,
        public bool $split = false,
    ) {}
}
