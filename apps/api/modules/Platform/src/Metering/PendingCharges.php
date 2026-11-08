<?php

namespace Modules\Platform\Metering;

use Modules\SharedKernel\Plans\Meter;
use Throwable;

/**
 * A helper that hears a recording and then writes from it (the report from
 * a voice note) is charged once: what it heard is held here, with its actual
 * cost, until its words come back (settled then) or do not (released at the
 * end of the request or job, or by the hold's own time if nothing ends it).
 * Scoped: one per request and per job.
 */
final class PendingCharges
{
    /** @var array<string, array{academy: string, hold: ?string, cost: int, about: array<string, mixed>}> */
    private array $pending = [];

    public function __construct(private readonly Meters $meters) {}

    /** @param  array<string, mixed>  $about */
    public function put(string $capability, string $academyId, ?string $hold, int $cost, array $about): void
    {
        if (isset($this->pending[$capability])) {
            $this->releaseOne($capability);
        }
        $this->pending[$capability] = ['academy' => $academyId, 'hold' => $hold, 'cost' => $cost, 'about' => $about];
    }

    /** @return ?array{academy: string, hold: ?string, cost: int, about: array<string, mixed>} */
    public function take(string $capability, string $academyId): ?array
    {
        $pending = $this->pending[$capability] ?? null;
        if ($pending === null || $pending['academy'] !== $academyId) {
            return null;
        }
        unset($this->pending[$capability]);

        return $pending;
    }

    /** What was heard and never written from costs nothing. */
    public function releaseAll(): void
    {
        foreach (array_keys($this->pending) as $capability) {
            $this->releaseOne($capability);
        }
    }

    private function releaseOne(string $capability): void
    {
        $pending = $this->pending[$capability];
        unset($this->pending[$capability]);
        if ($pending['hold'] === null) {
            return;
        }
        try {
            $this->meters->release($pending['academy'], Meter::AiCredits, $pending['hold']);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
