<?php

namespace Modules\SharedKernel\Ai;

/**
 * Whether the current academy may use an AI helper now: its plan has the
 * area, the academy turned the helper on, the platform has the keys it
 * needs, and today's uses are under the cap. Platform binds it; until then
 * every helper is off.
 */
interface AcademyAi
{
    public function on(AiFeature $feature): bool;

    /** @throws AiFailed off, not_configured or limit */
    public function require(AiFeature $feature): void;
}
