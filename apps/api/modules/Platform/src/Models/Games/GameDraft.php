<?php

namespace Modules\Platform\Models\Games;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A game the platform team is making in the studio, with the designer:
 * the story, what the designer made of it (title, page, manifest) and
 * what the package check found. Platform connection only; moved only by
 * the studio's Actions.
 *
 * @property string $id
 * @property ?string $game_id
 * @property string $island_id
 * @property list<string> $stages
 * @property list<string> $kinds
 * @property string $topic
 * @property string $story
 * @property string $status
 * @property ?string $title_ar
 * @property ?string $title_en
 * @property ?string $summary_ar
 * @property ?string $summary_en
 * @property ?array<string, mixed> $manifest
 * @property ?string $html
 * @property list<array{rule: string, detail: string}> $issues
 * @property ?string $error
 * @property ?string $served_model
 * @property ?array<string, int> $usage
 * @property int $rounds
 * @property string $created_by
 * @property ?CarbonImmutable $designed_at
 * @property ?CarbonImmutable $published_at
 * @property ?string $published_version_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class GameDraft extends Model
{
    use HasUuids;

    public const OPEN = ['designing', 'ready', 'failed'];

    protected $connection = 'pgsql_platform';

    protected $table = 'game_drafts';

    protected $guarded = [];

    protected $attributes = ['issues' => '[]'];

    protected function casts(): array
    {
        return [
            'stages' => 'array',
            'kinds' => 'array',
            'manifest' => 'array',
            'issues' => 'array',
            'usage' => 'array',
            'designed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_id');
    }

    /** @return BelongsTo<GameIsland, $this> */
    public function island(): BelongsTo
    {
        return $this->belongsTo(GameIsland::class, 'island_id');
    }

    /** @return HasMany<GameDraftTurn, $this> */
    public function turns(): HasMany
    {
        return $this->hasMany(GameDraftTurn::class, 'draft_id')->orderBy('created_at')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function title(?string $locale = null): ?string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->title_ar : $this->title_en;
    }
}
