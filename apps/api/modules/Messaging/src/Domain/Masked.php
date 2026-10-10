<?php

namespace Modules\Messaging\Domain;

/** A message as everyone reads it, what was hidden from it, and what was only named. */
final readonly class Masked
{
    /**
     * @param  list<string>  $hidden  email | link | handle | phone, each once
     * @param  list<string>  $flags  outside_app: an app to talk on outside the platform was named
     */
    public function __construct(
        public string $text,
        public array $hidden,
        public array $flags,
    ) {}

    public function changed(): bool
    {
        return $this->hidden !== [];
    }
}
