<?php

namespace Modules\Scheduling\Domain\LiveBoard;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\Enums\SessionEventKind;

final readonly class BoardEvent
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public SessionEventKind $kind,
        public CarbonImmutable $at,
        public array $data,
        public ?string $actorName,
    ) {}
}
