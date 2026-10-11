<?php

namespace Modules\Platform\Models\Games;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A game in the catalog: live at one published version, retired, or not
 * published yet. Platform connection only; changed through the studio's
 * Actions (PublishGameDraft, SetGameStatus).
 *
 * @property string $id
 * @property string $key
 * @property string $island_id
 * @property string $title_ar
 * @property string $title_en
 * @property string $summary_ar
 * @property string $summary_en
 * @property list<string> $stages
 * @property list<string> $kinds
 * @property string $status
 * @property ?string $current_version_id
 * @property int $sort
 * @property ?string $created_by
 */
final class Game extends Model
{
    use HasUuids;

    public const STATUSES = ['draft', 'live', 'retired'];

    protected $connection = 'pgsql_platform';

    protected $table = 'games';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['stages' => 'array', 'kinds' => 'array'];
    }

    /** @return BelongsTo<GameIsland, $this> */
    public function island(): BelongsTo
    {
        return $this->belongsTo(GameIsland::class, 'island_id');
    }

    /** @return BelongsTo<GameVersion, $this> */
    public function current(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class, 'current_version_id');
    }

    /** @return HasMany<GameVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(GameVersion::class, 'game_id')->orderByDesc('version');
    }

    public function title(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->title_ar : $this->title_en;
    }
}
