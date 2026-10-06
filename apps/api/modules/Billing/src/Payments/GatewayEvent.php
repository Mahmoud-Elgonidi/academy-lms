<?php

namespace Modules\Billing\Payments;

/** A verified webhook delivery: which event (once per id), and which checkout it is about (the gateway's reference). */
final readonly class GatewayEvent
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(public string $id, public string $type, public ?string $ref, public array $payload) {}
}
