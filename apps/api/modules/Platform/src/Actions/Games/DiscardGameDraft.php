<?php

namespace Modules\Platform\Actions\Games;

use Illuminate\Validation\ValidationException;
use Modules\Platform\Models\Games\GameDraft;

/** The platform team drops a draft it will not publish; it stays in the studio's history. */
final class DiscardGameDraft
{
    public function handle(GameDraft $draft): void
    {
        $done = GameDraft::query()->whereKey($draft->id)->whereIn('status', GameDraft::OPEN)
            ->update(['status' => 'discarded', 'updated_at' => now()]);
        if ($done === 0) {
            throw ValidationException::withMessages(['draft' => __('platform::games.errors.closed')]);
        }
    }
}
