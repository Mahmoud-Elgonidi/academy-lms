<?php

namespace Modules\Platform\Actions\Games;

use Modules\Platform\Models\Games\GameDraft;

/**
 * A round that has run for longer than any round should (STALE_MINUTES:
 * the designer's worker stopped or the service never answered) is marked
 * failed, so the team can ask again. A round still within its time is left
 * alone.
 */
final class StopGameDesign
{
    public const STALE_MINUTES = 20;

    public function handle(GameDraft $draft): bool
    {
        return GameDraft::query()->whereKey($draft->id)->where('status', 'designing')
            ->where('updated_at', '<', now()->subMinutes(self::STALE_MINUTES))
            ->update(['status' => 'failed', 'error' => 'unavailable: stale', 'updated_at' => now()]) > 0;
    }
}
