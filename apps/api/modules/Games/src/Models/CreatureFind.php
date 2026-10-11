<?php

namespace Modules\Games\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A creature a learner hatched: from stars in the games or a lesson mastered.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $student_id
 * @property string $creature_key
 * @property string $source stars | mastery
 * @property ?string $node_id
 * @property ?string $play_id
 * @property ?string $nickname the name the learner gave it
 * @property bool $buddy the one that keeps them company
 * @property CarbonImmutable $found_at
 */
final class CreatureFind extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'creature_finds';

    protected $guarded = ['id', 'academy_id'];

    protected function casts(): array
    {
        return ['found_at' => 'immutable_datetime', 'buddy' => 'boolean'];
    }
}
