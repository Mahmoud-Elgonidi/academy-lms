<?php

namespace Modules\Platform\Models\Games;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A published version of a game: its page and what it says about itself.
 * Never changes once written (the model refuses, and a trigger holds it).
 *
 * @property string $id
 * @property string $game_id
 * @property int $version
 * @property string $html
 * @property string $sha256
 * @property array<string, mixed> $manifest
 * @property ?string $draft_id
 * @property ?string $published_by
 * @property CarbonImmutable $published_at
 */
final class GameVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'pgsql_platform';

    protected $table = 'game_versions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['manifest' => 'array', 'published_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('A published game version never changes; publish a new one.'));
        self::deleting(fn () => throw new LogicException('A published game version is kept.'));
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_id');
    }
}
