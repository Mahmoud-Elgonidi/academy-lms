<?php

namespace Modules\Curriculum\Domain;

/**
 * One point of a version's readiness to publish: blocker or warning, its key
 * (its words are curriculum::curriculum.readiness.items.{key}) and the node it
 * is about (null for the whole version).
 */
final readonly class ReadinessItem
{
    public function __construct(
        public string $severity,
        public string $key,
        public ?string $nodeId,
    ) {}

    public function blocks(): bool
    {
        return $this->severity === PublishReadiness::BLOCKER;
    }
}
