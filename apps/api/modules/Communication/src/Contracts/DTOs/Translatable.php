<?php

namespace Modules\Communication\Contracts\DTOs;

/** A placeholder value translated into each recipient's language. */
final readonly class Translatable
{
    /** @param  array<string, string|int>  $replace */
    public function __construct(public string $key, public array $replace = []) {}
}
