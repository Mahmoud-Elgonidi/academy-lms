<?php

namespace Modules\Curriculum\Contracts\DTOs;

final readonly class ProgramOption
{
    /** @param  ?string  $description  what the program is, in the current language (the other's where it is empty); null when it has none */
    public function __construct(
        public string $id,
        public string $subjectId,
        public string $name,
        public ?string $publishedVersionId,
        public int $lessons,
        public ?string $description = null,
    ) {}
}
