<?php

namespace Modules\Platform\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a capability's requests go (console › AI routing): for one kind
 * (text, speech) and scope (default, plan:{code}, academy:{id}), an ordered
 * chain of engines with an optional tier each, and an optional share sent to
 * a second chain. Saved only through SaveAiRoutes; platform connection only.
 *
 * @property string $id
 * @property string $capability
 * @property string $kind
 * @property string $scope
 * @property list<array{engine: string, tier: ?string}> $chain
 * @property ?int $split_percent
 * @property ?list<array{engine: string, tier: ?string}> $split_chain
 * @property ?string $updated_by
 */
final class AiRoute extends Model
{
    use HasUuids;

    public const DEFAULT = 'default';

    protected $connection = 'pgsql_platform';

    protected $table = 'ai_routes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['chain' => 'array', 'split_chain' => 'array', 'split_percent' => 'integer'];
    }

    public static function planScope(string $code): string
    {
        return 'plan:'.$code;
    }

    public static function academyScope(string $id): string
    {
        return 'academy:'.$id;
    }
}
