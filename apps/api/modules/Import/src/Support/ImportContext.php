<?php

namespace Modules\Import\Support;

/** What every row of a run shares: the calling code for local numbers, the academy's clock and language, and who imports. */
final readonly class ImportContext
{
    public function __construct(
        public string $callingCode,
        public string $timezone,
        public string $locale,
        public string $actorId,
    ) {}
}
