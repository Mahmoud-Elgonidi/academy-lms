<?php

namespace Modules\SharedKernel\Files;

/** What a scanner said about one file: clean, infected (with the signature it matched), or that it could not tell. */
final readonly class ScanVerdict
{
    public const CLEAN = 'clean';

    public const INFECTED = 'infected';

    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public string $status,
        public ?string $signature = null,
        public ?string $error = null,
    ) {}

    public static function clean(): self
    {
        return new self(self::CLEAN);
    }

    public static function infected(string $signature): self
    {
        return new self(self::INFECTED, $signature);
    }

    public static function unavailable(string $error): self
    {
        return new self(self::UNAVAILABLE, error: $error);
    }
}
