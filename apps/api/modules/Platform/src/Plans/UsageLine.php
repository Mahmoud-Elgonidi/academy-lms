<?php

namespace Modules\Platform\Plans;

use Modules\SharedKernel\Plans\Limit;

/** One limit: how much is used, the most allowed (null: no limit), and whether it is this academy's own. */
final readonly class UsageLine
{
    public function __construct(
        public Limit $limit,
        public ?int $used,
        public ?int $max,
        public bool $overridden = false,
    ) {}

    /** Used over allowed, 0..; null without a limit or a count. */
    public function ratio(): ?float
    {
        if ($this->used === null || $this->max === null) {
            return null;
        }

        return $this->max === 0 ? ($this->used > 0 ? INF : 0.0) : $this->used / $this->max;
    }

    /** ok, near (80% and over), full (at or over the limit). */
    public function state(): string
    {
        $ratio = $this->ratio();

        return match (true) {
            $ratio === null || $ratio < 0.8 => 'ok',
            $ratio < 1 => 'near',
            default => 'full',
        };
    }
}
