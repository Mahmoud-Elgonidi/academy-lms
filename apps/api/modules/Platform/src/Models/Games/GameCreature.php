<?php

namespace Modules\Platform\Models\Games;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A creature hatched on an island. Platform connection only.
 *
 * @property string $id
 * @property string $island_id
 * @property string $key
 * @property string $name_ar
 * @property string $name_en
 * @property string $emoji
 * @property string $rarity
 * @property int $sort
 */
final class GameCreature extends Model
{
    use HasUuids;

    protected $connection = 'pgsql_platform';

    protected $table = 'game_creatures';

    protected $guarded = [];
}
