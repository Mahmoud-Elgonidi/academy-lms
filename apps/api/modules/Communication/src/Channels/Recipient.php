<?php

namespace Modules\Communication\Channels;

final readonly class Recipient
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public string $locale,
        public string $timezone,
    ) {}
}
