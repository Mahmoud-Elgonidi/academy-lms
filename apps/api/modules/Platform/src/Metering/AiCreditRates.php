<?php

namespace Modules\Platform\Metering;

use Modules\Platform\Domain\Metering\CreditRates;
use Modules\Platform\Models\Metering\AiCreditRate;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\Capability;

/**
 * What each AI capability costs in credits now: the platform owners' rate
 * (console › AI credit rates), else the capability's own (Capability::credits
 * and creditsPerMinute, registered by its module). Read once per request.
 */
final class AiCreditRates
{
    /** @var ?array<string, array{base: int, per_minute: int}> */
    private ?array $saved = null;

    public function __construct(private readonly Capabilities $capabilities) {}

    /** @return array{base: int, per_minute: int, own: bool} */
    public function for(Capability|string $capability): array
    {
        $known = $capability instanceof Capability ? $capability : $this->capabilities->get($capability);
        $key = $capability instanceof Capability ? $capability->key : $capability;
        $saved = $this->saved()[$key] ?? null;
        if ($saved !== null) {
            return $saved + ['own' => true];
        }

        return ['base' => $known?->credits ?? 1, 'per_minute' => $known?->creditsPerMinute ?? 0, 'own' => false];
    }

    /** What a request costs: its base, and so much each minute of what was heard. */
    public function charge(Capability|string $capability, ?float $seconds = null): int
    {
        $rate = $this->for($capability);

        return CreditRates::charge($rate['base'], $rate['per_minute'], $seconds);
    }

    /** The least a request of this capability can cost (the helper is closed below it). */
    public function least(Capability|string $capability): int
    {
        $rate = $this->for($capability);

        return CreditRates::least($rate['base'], $rate['per_minute']);
    }

    /** @return array<string, array{capability: Capability, base: int, per_minute: int, own: bool, default_base: int, default_per_minute: int}> */
    public function all(): array
    {
        $out = [];
        foreach ($this->capabilities->all() as $key => $capability) {
            $out[$key] = ['capability' => $capability] + $this->for($capability) + ['default_base' => $capability->credits, 'default_per_minute' => $capability->creditsPerMinute];
        }

        return $out;
    }

    public function forget(): void
    {
        $this->saved = null;
    }

    /** @return array<string, array{base: int, per_minute: int}> */
    private function saved(): array
    {
        return $this->saved ??= AiCreditRate::query()->get()
            ->mapWithKeys(fn (AiCreditRate $r) => [$r->capability => ['base' => $r->base, 'per_minute' => $r->per_minute]])->all();
    }
}
