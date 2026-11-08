<?php

namespace Modules\SharedKernel\Ai;

use InvalidArgumentException;

/**
 * The system's AI capabilities, each registered by the module that owns it
 * (Capabilities::add in its provider). The console's routes page shows one
 * row for each, the academy's Settings › AI a switch for each, and the
 * gateway refuses a key nobody registered. The five helpers that came
 * before the registry are always known, from their enum, even before their
 * modules boot.
 */
final class Capabilities
{
    /** @var array<string, Capability> */
    private array $all = [];

    public function add(Capability $capability): void
    {
        $known = $this->all[$capability->key] ?? null;
        if ($known !== null && $known->module !== $capability->module) {
            throw new InvalidArgumentException("The AI capability {$capability->key} is already {$known->module}'s.");
        }
        $this->all[$capability->key] = $capability;
    }

    public function get(AiFeature|string $capability): ?Capability
    {
        $key = self::key($capability);

        return $this->all[$key] ?? (($feature = AiFeature::tryFrom($key)) !== null ? Capability::of($feature) : null);
    }

    public function require(AiFeature|string $capability): Capability
    {
        return $this->get($capability) ?? throw new InvalidArgumentException('No AI capability is registered as '.self::key($capability).'.');
    }

    /** @return array<string, Capability> by key: the five helpers first, in their order, then the rest by key */
    public function all(): array
    {
        $out = [];
        foreach (AiFeature::cases() as $feature) {
            $out[$feature->value] = $this->get($feature);
        }
        $rest = array_diff_key($this->all, $out);
        ksort($rest);

        return $out + $rest;
    }

    public static function key(AiFeature|string $capability): string
    {
        return $capability instanceof AiFeature ? $capability->value : $capability;
    }

    /** For a test that registered its own. */
    public function forget(string $key): void
    {
        unset($this->all[$key]);
    }
}
