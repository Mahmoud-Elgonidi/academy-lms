<?php

namespace Modules\Platform\Ai\Gateway;

/**
 * What the gateway is doing right now, for the line ai_usage keeps of it:
 * which engine it is asking, which one failed just before, and when the
 * attempt began. Set around each attempt and cleared after; empty when an
 * adapter is asked outside the gateway (the console's checks).
 */
final class CallContext
{
    private ?string $engineId = null;

    private ?string $fallbackFrom = null;

    private ?float $startedAt = null;

    public function begin(?string $engineId, ?string $fallbackFrom): void
    {
        $this->engineId = $engineId;
        $this->fallbackFrom = $fallbackFrom;
        $this->startedAt = hrtime(true) / 1e6;
    }

    public function end(): void
    {
        $this->engineId = $this->fallbackFrom = null;
        $this->startedAt = null;
    }

    /** @return array{engine_id: ?string, fallback_from: ?string, latency_ms: ?int} */
    public function line(): array
    {
        return [
            'engine_id' => $this->engineId,
            'fallback_from' => $this->fallbackFrom,
            'latency_ms' => $this->startedAt === null ? null : (int) round(hrtime(true) / 1e6 - $this->startedAt),
        ];
    }
}
