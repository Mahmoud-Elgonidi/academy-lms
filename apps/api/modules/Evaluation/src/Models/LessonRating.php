<?php

namespace Modules\Evaluation\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One person's rating of one lesson: 1 to 5 stars, a few quick words, an
 * optional comment. The teacher sees the stars and words without names.
 *
 * @property string $id
 * @property string $session_id
 * @property string $rater_id
 * @property string $student_id
 * @property string $teacher_id
 * @property int $stars
 * @property list<string> $tags
 * @property ?string $comment
 * @property string $via
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class LessonRating extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** Quick words, the good and the not so good. */
    public const TAGS = [
        'good' => ['clear', 'patient', 'engaging', 'prepared'],
        'bad' => ['late', 'connection', 'too_fast', 'distracted'],
    ];

    protected $fillable = ['session_id', 'rater_id', 'student_id', 'teacher_id', 'stars', 'tags', 'comment', 'via'];

    protected $attributes = ['tags' => '[]', 'via' => 'app'];

    protected function casts(): array
    {
        return ['stars' => 'integer', 'tags' => 'array', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
