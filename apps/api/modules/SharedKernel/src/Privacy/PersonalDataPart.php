<?php

namespace Modules\SharedKernel\Privacy;

/** One module's part of a person's copy: rows of plain values, and the files they gave (path on the private `local` disk => name in the archive). */
final readonly class PersonalDataPart
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $files
     */
    public function __construct(public array $data, public array $files = []) {}

    public function isEmpty(): bool
    {
        return array_filter($this->data, fn ($v) => $v !== [] && $v !== null) === [] && $this->files === [];
    }
}
