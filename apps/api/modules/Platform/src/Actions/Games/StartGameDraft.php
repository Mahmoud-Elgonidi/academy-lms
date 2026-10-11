<?php

namespace Modules\Platform\Actions\Games;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Jobs\DesignGameJob;
use Modules\Platform\Models\Games\GameDraft;
use Modules\Platform\Models\Games\GameDraftTurn;
use Modules\SharedKernel\Games\ItemKind;

/**
 * The platform team starts a new game from a story: which island it lives
 * on, who it is for (the learner looks), which practice items it plays and
 * what the academies teach with it. The designer goes to work on it after
 * commit (DesignGameJob); the studio shows the draft as it comes.
 */
final class StartGameDraft
{
    public const STAGES = ['kids', 'middle', 'teen', 'classic'];

    public function __construct(private readonly PlatformAudit $audit) {}

    /** @param  array{story: string, island_id: string, stages: list<string>, kinds: list<string>, topic?: ?string}  $data */
    public function handle(string $adminId, array $data): GameDraft
    {
        $valid = Validator::make($data, [
            'story' => ['required', 'string', 'min:20', 'max:4000'],
            'island_id' => ['required', 'uuid', Rule::exists('pgsql_platform.game_islands', 'id')],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*' => [Rule::in(self::STAGES)],
            'kinds' => ['required', 'array', 'min:1'],
            'kinds.*' => [Rule::in(ItemKind::values())],
            'topic' => ['nullable', 'string', 'max:160'],
        ], [], [
            'story' => __('platform::games.attributes.story'),
            'island_id' => __('platform::games.attributes.island'),
            'stages' => __('platform::games.attributes.stages'),
            'kinds' => __('platform::games.attributes.kinds'),
        ])->validate();

        $draft = DB::connection('pgsql_platform')->transaction(function () use ($adminId, $valid) {
            $draft = GameDraft::query()->create([
                'island_id' => $valid['island_id'],
                'stages' => array_values(array_unique($valid['stages'])),
                'kinds' => array_values(array_unique($valid['kinds'])),
                'topic' => trim((string) ($valid['topic'] ?? '')),
                'story' => trim($valid['story']),
                'status' => 'designing',
                'created_by' => $adminId,
            ]);
            GameDraftTurn::query()->create(['draft_id' => $draft->id, 'role' => 'admin', 'body' => $draft->story, 'admin_id' => $adminId]);
            $this->audit->record(null, 'platform.game_draft_started', 'game_draft', $draft->id, ['kinds' => implode(', ', $draft->kinds)]);

            return $draft;
        });

        DesignGameJob::dispatch($draft->id)->afterCommit();

        return $draft;
    }
}
