<?php

namespace Modules\Import\Support;

/** What importing a row did, or would do: created, updated or skipped, said in a line, with what it made. */
final readonly class RowOutcome
{
    /**
     * @param  'created'|'updated'|'skipped'  $status
     * @param  list<string>  $notes
     * @param  array<string, mixed>  $refs
     * @param  list<string>  $newPeople  accounts made for people who can be invited
     */
    public function __construct(
        public string $status,
        public array $notes = [],
        public array $refs = [],
        public array $newPeople = [],
    ) {}
}
