<?php

namespace Modules\Site\Contracts\DTOs;

/** How the academy takes free trials asked for on its page. */
final readonly class TrialSettings
{
    public function __construct(
        public bool $pageOn,                 // the academy's page shows
        public bool $familyBooks,            // the family books its own trial (the page on, and trial_booking = family)
        public int $responseHours,           // how soon the academy promises to answer a request
        public ?string $defaultSubjectId,    // a family that asks for help choosing starts here (null: the staff choose)
        public int $noticeHours = 3,         // a family books its own trial at least this long ahead
    ) {}
}
