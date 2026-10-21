<?php

namespace Modules\People\Contracts\DTOs;

/** A teacher's card as the academy's page settings list it, for the academy to take one off or put it back. */
final readonly class TeacherCardSummary
{
    /**
     * @param  bool  $shown  the teacher's own switch
     * @param  bool  $active  the account and the teacher's profile are active
     */
    public function __construct(
        public string $id,
        public string $displayName,
        public string $teacherName,
        public ?string $bio,
        public ?string $photoUrl,
        public bool $shown,
        public bool $hiddenByAcademy,
        public bool $active,
    ) {}

    /** On the page now (when its section shows). */
    public function onPage(): bool
    {
        return $this->shown && ! $this->hiddenByAcademy && $this->active;
    }
}
