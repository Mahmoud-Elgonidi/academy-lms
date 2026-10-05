<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/** A participant's connection dropped (from the video provider). Three in 15 minutes flag the session. */
final class RecordConnectionDrop
{
    public function handle(Session $session, string $userId, int $seconds, ?CarbonImmutable $at = null): void
    {
        SessionEvent::record($session, SessionEventKind::ConnectionDrop, ['user_id' => $userId, 'seconds' => $seconds], null, $at);
    }
}
