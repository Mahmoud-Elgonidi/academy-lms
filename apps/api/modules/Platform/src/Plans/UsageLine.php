<?php

namespace Modules\Platform\Plans;

use Modules\SharedKernel\Plans\Limit;

/**
 * One limit: how much is used, the plan's most (null: no limit), whether it
 * is this academy's own, and for a monthly one (the usage ledger) what more
 * this month may use over the plan's allowance (the bought balance, less
 * any overage owed) and the bought balance left.
 */
final readonly class UsageLine
{
    public function __construct(
        public Limit $limit,
        public ?int $used,
        public ?int $max,
        public bool $overridden = false,
        public int $extra = 0,
        public int $bought = 0,
    ) {}

    /** The most allowed now: the plan's with the bought balance; null without a limit. */
    public function allowed(): ?int
    {
        return $this->max === null ? null : $this->max + $this->extra;
    }

    /** What is left of it now (never below nothing); null without a limit or a count. */
    public function left(): ?int
    {
        $allowed = $this->allowed();

        return $allowed === null || $this->used === null ? null : max(0, $allowed - $this->used);
    }

    /** Used over allowed, 0..; null without a limit or a count. */
    public function ratio(): ?float
    {
        $allowed = $this->allowed();
        if ($this->used === null || $allowed === null) {
            return null;
        }

        return $allowed === 0 ? ($this->used > 0 ? INF : 0.0) : $this->used / $allowed;
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
