<?php

namespace Modules\Communication\Contracts\DTOs;

/**
 * A placeholder value translated into each recipient's language. With a
 * count, the key is a plural choice (`{1}…|{2}…|[3,10]…|[11,*]…`: Arabic's
 * one, two, few and many) chosen by it, and :count is that number.
 */
final readonly class Translatable
{
    /** @param  array<string, string|int>  $replace */
    public function __construct(public string $key, public array $replace = [], public ?int $count = null) {}

    /** A number with its counted word, agreeing in each recipient's language ("حصتان", "3 حصص", "11 حصة"). */
    public static function count(string $key, int $count): self
    {
        return new self($key, [], $count);
    }
}
