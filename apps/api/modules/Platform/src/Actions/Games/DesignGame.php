<?php

namespace Modules\Platform\Actions\Games;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Games\Designer\DesignBrief;
use Modules\Platform\Games\Designer\DesignedGame;
use Modules\Platform\Games\Designer\DesignFailed;
use Modules\Platform\Games\Designer\GameDesigner;
use Modules\Platform\Games\Manifest;
use Modules\Platform\Games\PackageCheck;
use Modules\Platform\Models\Games\GameCreature;
use Modules\Platform\Models\Games\GameDraft;
use Modules\Platform\Models\Games\GameDraftTurn;

/**
 * One round of the designer's work on a draft (run by DesignGameJob): it is
 * told the story, the island, who plays and what, the conversation so far
 * and the page as it stands; what it makes is checked (PackageCheck) and
 * kept on the draft with its note to the team. A round that fails leaves
 * the last page as it was and says why.
 */
final class DesignGame
{
    public function __construct(private readonly GameDesigner $designer) {}

    public function handle(string $draftId): void
    {
        $draft = GameDraft::query()->with(['island', 'turns'])->find($draftId);
        if ($draft === null || $draft->status !== 'designing') {
            return;
        }

        try {
            $made = $this->designer->design($this->brief($draft));
        } catch (DesignFailed $e) {
            $this->failed($draft->id, $e->reason, $e->getMessage());

            return;
        }

        DB::connection('pgsql_platform')->transaction(function () use ($draft, $made) {
            $draft = GameDraft::query()->lockForUpdate()->find($draft->id);
            if ($draft === null || $draft->status !== 'designing') {
                return;
            }
            $kinds = $made->kinds !== [] ? array_values(array_intersect($made->kinds, $draft->kinds)) ?: $draft->kinds : $draft->kinds;
            $stages = $made->stages !== [] ? array_values(array_intersect($made->stages, $draft->stages)) ?: $draft->stages : $draft->stages;
            $draft->forceFill([
                'status' => 'ready',
                'title_ar' => $made->titleAr ?: $draft->title_ar,
                'title_en' => $made->titleEn ?: $draft->title_en,
                'summary_ar' => $made->summaryAr,
                'summary_en' => $made->summaryEn,
                'html' => $made->html,
                'manifest' => Manifest::make($stages, $kinds, $made->minItems, $made->maxItems, $made->howToPlayAr, $made->howToPlayEn),
                'issues' => PackageCheck::issues($made->html),
                'error' => null,
                'served_model' => $made->servedModel,
                'usage' => self::added($draft->usage ?? [], $made),
                'rounds' => $draft->rounds + 1,
                'designed_at' => now(),
            ])->save();
            GameDraftTurn::query()->create(['draft_id' => $draft->id, 'role' => 'designer', 'body' => $made->message !== '' ? $made->message : '…']);
        });
    }

    public function failed(string $draftId, string $reason, string $detail = ''): void
    {
        GameDraft::query()->whereKey($draftId)->where('status', 'designing')->update([
            'status' => 'failed',
            'error' => in_array($reason, DesignFailed::REASONS, true) ? $reason.($detail !== '' && $detail !== $reason ? ': '.mb_substr($detail, 0, 500) : '') : 'unavailable',
            'updated_at' => now(),
        ]);
    }

    private function brief(GameDraft $draft): DesignBrief
    {
        $island = $draft->island;
        $problems = array_map(fn (array $i) => $i['rule'].': '.$i['detail'], $draft->issues ?? []);

        return new DesignBrief(
            story: $draft->story,
            topic: $draft->topic,
            island: [
                'key' => $island->key,
                'name_ar' => $island->name_ar,
                'name_en' => $island->name_en,
                'story_ar' => $island->story_ar,
                'colors' => $island->colors,
                'music' => $island->music,
                'creatures' => GameCreature::query()->where('island_id', $island->id)->orderBy('sort')->get()
                    ->map(fn (GameCreature $c) => $c->name_ar.' '.$c->emoji.' ('.$c->name_en.')')->all(),
            ],
            stages: $draft->stages,
            kinds: $draft->kinds,
            conversation: $draft->turns->map(fn (GameDraftTurn $t) => ['role' => $t->role, 'body' => $t->body])->all(),
            currentHtml: $draft->html,
            problems: $problems,
        );
    }

    /**
     * @param  array<string, int>  $before
     * @return array<string, int>
     */
    private static function added(array $before, DesignedGame $made): array
    {
        foreach ($made->usage as $key => $value) {
            $before[$key] = ($before[$key] ?? 0) + $value;
        }

        return $before;
    }
}
