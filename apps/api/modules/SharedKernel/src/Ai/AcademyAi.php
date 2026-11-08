<?php

namespace Modules\SharedKernel\Ai;

/**
 * Whether the current academy may use an AI helper now: its plan has the
 * area, the academy turned the helper on, the platform has the keys it
 * needs, and today's uses are under the cap. Platform binds it; until then
 * every helper is off. A capability is one of the five helpers or any other
 * a module registered (Capabilities), by its key.
 */
interface AcademyAi
{
    /** @param  AiFeature|string  $capability  a helper, or a registered capability's key (Capabilities) */
    public function on(AiFeature|string $capability): bool;

    /** @throws AiFailed off, not_configured, quota or limit */
    public function require(AiFeature|string $capability): void;
}
