<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A page of the mushaf on a learner's hifz map: learning or memorized, and
 * when it is next due for review (Domain\Hifz\Scheduler). A page not here is
 * new to them. Changed only through Actions\Hifz.
 *
 * @property string $id
 * @property string $student_id
 * @property int $page
 * @property string $state learning | memorized
 * @property float $stability
 * @property float $difficulty
 * @property CarbonImmutable $due_at
 * @property CarbonImmutable $last_reviewed_at
 * @property int $reviews
 * @property int $lapses
 */
final class HifzPage extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['student_id', 'page', 'state', 'stability', 'difficulty', 'due_at', 'last_reviewed_at', 'reviews', 'lapses'];

    protected function casts(): array
    {
        return [
            'page' => 'integer', 'stability' => 'float', 'difficulty' => 'float', 'reviews' => 'integer', 'lapses' => 'integer',
            'due_at' => 'immutable_datetime', 'last_reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return array{state: string, stability: float, difficulty: float, due_at: CarbonImmutable, last_reviewed_at: CarbonImmutable, reviews: int, lapses: int} */
    public function card(): array
    {
        return $this->only(['state', 'stability', 'difficulty', 'due_at', 'last_reviewed_at', 'reviews', 'lapses']);
    }
}
