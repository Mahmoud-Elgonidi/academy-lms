<?php

namespace Modules\People\Contracts\Events;

/** A child's account waits for a guardian's consent: these guardians are asked (each with their own link). */
final readonly class ConsentRequested
{
    /** @param  list<string>  $guardianIds */
    public function __construct(
        public string $academyId,
        public string $studentId,
        public array $guardianIds,
        public bool $again = false,          // the office asks again on purpose (not deduplicated like a link's request)
    ) {}
}
