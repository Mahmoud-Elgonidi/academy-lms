<?php

namespace Modules\People\Contracts\DTOs;

/** A subject on a teacher's public card, in the current language, with the levels they teach from one to another (null: open). */
final readonly class PublicTeacherSubject
{
    public function __construct(
        public string $name,
        public ?string $levelFrom = null,
        public ?string $levelTo = null,
    ) {}
}
