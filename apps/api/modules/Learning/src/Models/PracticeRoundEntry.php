<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One practice round a learner finished in a game; written once by
 * RecordPractice, never changed.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $student_id
 * @property ?string $enrollment_id
 * @property ?string $homework_id
 * @property string $game
 * @property string $focus
 * @property int $correct
 * @property int $total
 * @property int $stars
 * @property int $seconds
 * @property string $played_by
 * @property CarbonImmutable $played_at
 */
final class PracticeRoundEntry extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'practice_rounds';

    protected $fillable = ['student_id', 'enrollment_id', 'homework_id', 'game', 'focus', 'correct', 'total', 'stars', 'seconds', 'played_by', 'played_at'];

    protected function casts(): array
    {
        return ['correct' => 'integer', 'total' => 'integer', 'stars' => 'integer', 'seconds' => 'integer', 'played_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('A practice round is kept as it was played.'));
    }
}
