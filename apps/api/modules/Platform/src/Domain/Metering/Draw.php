<?php

namespace Modules\Platform\Domain\Metering;

/** How one use is taken: so much from each lot in spend order, and what no lot covered (overage). */
final readonly class Draw
{
    /** @param  array<string, int>  $fromLots  lot id => amount taken, in spend order */
    public function __construct(public array $fromLots, public int $overage) {}

    public function total(): int
    {
        return array_sum($this->fromLots) + $this->overage;
    }
}
