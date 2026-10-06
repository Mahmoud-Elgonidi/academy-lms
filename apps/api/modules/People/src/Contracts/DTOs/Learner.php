<?php

namespace Modules\People\Contracts\DTOs;

/** A student as their family sees them on the web. */
final readonly class Learner
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $isSelf,
    ) {}

    public function initial(): string
    {
        return mb_substr(trim($this->name), 0, 1);
    }
}
