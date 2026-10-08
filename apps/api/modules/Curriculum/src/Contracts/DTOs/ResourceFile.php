<?php

namespace Modules\Curriculum\Contracts\DTOs;

/** Where a lesson's file is kept, for the module that serves it to someone it has checked. */
final readonly class ResourceFile
{
    public function __construct(
        public string $resourceId,
        public string $disk,
        public string $path,
        public string $name,
        public string $mime,
        public int $size,
        public string $audience,
    ) {}
}
