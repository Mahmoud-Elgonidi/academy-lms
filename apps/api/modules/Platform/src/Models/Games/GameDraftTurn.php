<?php

namespace Modules\Platform\Models\Games;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One message on a draft: the team's story or a change it asked for, or
 * the designer's note on what it made. Kept as written.
 *
 * @property string $id
 * @property string $draft_id
 * @property string $role
 * @property string $body
 * @property ?string $admin_id
 * @property CarbonImmutable $created_at
 */
final class GameDraftTurn extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $connection = 'pgsql_platform';

    protected $table = 'game_draft_turns';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('A studio message is kept as written.'));
        self::deleting(fn () => throw new LogicException('A studio message is kept as written.'));
    }
}
