<?php

namespace Modules\Curriculum\Contracts\DTOs;

final readonly class ProgramOption
{
    public function __construct(
        public string $id,
        public string $subjectId,
        public string $name,
        public ?string $publishedVersionId,
        public int $lessons,
    ) {}
}
