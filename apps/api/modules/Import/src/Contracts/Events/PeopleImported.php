<?php

namespace Modules\Import\Contracts\Events;

/** An import made accounts for these people; if the run asks for it, each is welcomed with a link to choose a password. */
final readonly class PeopleImported
{
    /** @param  list<string>  $userIds */
    public function __construct(
        public string $academyId,
        public string $runId,
        public string $kind,
        public array $userIds,
        public bool $invite,
    ) {}
}
