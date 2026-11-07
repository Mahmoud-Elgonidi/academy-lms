<?php

namespace Modules\Platform\Domain\Modules;

use Modules\SharedKernel\Plans\Feature;

/** What ModuleRules decided: the areas hidden after the change, those hidden along with another, and why not. */
final readonly class ModuleDecision
{
    /**
     * @param  list<Feature>  $hidden
     * @param  list<Feature>  $also
     * @param  list<ModuleRefusal>  $refusals
     */
    public function __construct(
        public array $hidden,
        public array $also,
        public array $refusals,
    ) {}

    public function allowed(): bool
    {
        return $this->refusals === [];
    }
}
