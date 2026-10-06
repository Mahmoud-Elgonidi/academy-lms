<?php

namespace Modules\Communication\Contracts\DTOs;

/** A calendar day placeholder ("2026-10-12"), written in each recipient's language without a time. */
final readonly class Day
{
    public function __construct(public string $date) {}
}
