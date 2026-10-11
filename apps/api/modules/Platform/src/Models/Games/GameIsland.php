<?php

namespace Modules\Platform\Models\Games;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An island of the games world. Platform connection only.
 *
 * @property string $id
 * @property string $key
 * @property string $name_ar
 * @property string $name_en
 * @property string $story_ar
 * @property string $story_en
 * @property string $emoji
 * @property array{sky: string, sea: string, land: string, accent: string} $colors
 * @property string $music
 * @property int $sort
 */
final class GameIsland extends Model
{
    use HasUuids;

    protected $connection = 'pgsql_platform';

    protected $table = 'game_islands';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['colors' => 'array'];
    }

    /** @return HasMany<GameCreature, $this> */
    public function creatures(): HasMany
    {
        return $this->hasMany(GameCreature::class, 'island_id')->orderBy('sort');
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->name_ar : $this->name_en;
    }
}
