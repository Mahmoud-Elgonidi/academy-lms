<?php

namespace Modules\Privacy\Contracts\Events;

/** Someone asked for their data (or their child's) to be put right; the academy answers within 30 days. */
final readonly class CorrectionRequested
{
    public function __construct(public string $requestId) {}
}
