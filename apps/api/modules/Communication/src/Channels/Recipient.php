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
        /** How they read a clock: h12 or h24 (their own choice, else the academy's). */
        public string $timeFormat = 'h12',
    ) {}
}
