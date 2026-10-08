<?php

namespace Modules\SharedKernel\Plans;

/** Everything, without limits: until Platform binds the academies' plans. */
final class OpenPlan implements AcademyPlan
{
    public function has(Feature $feature): bool
    {
        return true;
    }

    public function limit(Limit $limit): ?int
    {
        return null;
    }

    public function ensureRoom(Limit $limit, int $wouldBe): void {}

    public function requireFeature(Feature $feature): void {}
}
