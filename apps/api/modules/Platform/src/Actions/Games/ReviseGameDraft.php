<?php

namespace Modules\Platform\Actions\Games;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Jobs\DesignGameJob;
use Modules\Platform\Models\Games\GameDraft;
use Modules\Platform\Models\Games\GameDraftTurn;

/**
 * The platform team asks the designer for changes to a draft it has played
 * (or to try again after a failed round): the message joins the draft's
 * conversation and the designer remakes the whole page with it.
 */
final class ReviseGameDraft
{
    public function handle(GameDraft $draft, string $adminId, string $changes): GameDraft
    {
        Validator::make(['changes' => $changes], ['changes' => ['required', 'string', 'min:3', 'max:4000']], [],
            ['changes' => __('platform::games.attributes.changes')])->validate();

        DB::connection('pgsql_platform')->transaction(function () use ($draft, $adminId, $changes) {
            $draft = GameDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if (! in_array($draft->status, ['ready', 'failed'], true)) {
                throw ValidationException::withMessages(['changes' => __('platform::games.errors.not_waiting')]);
            }
            GameDraftTurn::query()->create(['draft_id' => $draft->id, 'role' => 'admin', 'body' => trim($changes), 'admin_id' => $adminId]);
            $draft->forceFill(['status' => 'designing', 'error' => null])->save();
        });

        DesignGameJob::dispatch($draft->id)->afterCommit();

        return $draft->refresh();
    }
}
