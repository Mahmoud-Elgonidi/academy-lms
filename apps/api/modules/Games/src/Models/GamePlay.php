<?php

namespace Modules\Games\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One round a learner played; started by StartPlay, finished by FinishPlay.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $student_id
 * @property string $played_by
 * @property string $game_id
 * @property string $game_key
 * @property string $version_id
 * @property string $focus
 * @property ?string $enrollment_id
 * @property ?string $homework_id
 * @property list<array{id: string, node: string, kind: string}> $items
 * @property list<array{item: string, correct: bool}> $answers
 * @property CarbonImmutable $started_at
 * @property ?CarbonImmutable $finished_at
 * @property ?int $seconds
 * @property ?int $correct
 * @property int $total
 * @property ?int $stars
 * @property ?string $island_key the island a journey station's round was played on (its level's look)
 */
final class GamePlay extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'game_plays';

    protected $guarded = ['id', 'academy_id'];

    protected $attributes = ['answers' => '[]'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'answers' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'seconds' => 'integer',
            'correct' => 'integer',
            'total' => 'integer',
            'stars' => 'integer',
        ];
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /** @return list<string> */
    public function itemIds(): array
    {
        return array_column($this->items, 'id');
    }
}
