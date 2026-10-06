<?php

namespace Modules\Communication\Contracts\DTOs;

/** A value the sender has in both languages (a lesson or subject name); each recipient gets theirs. */
final readonly class Localized
{
    public function __construct(public string $ar, public string $en) {}

    public function in(string $locale): string
    {
        return $locale === 'ar' ? $this->ar : $this->en;
    }
}
