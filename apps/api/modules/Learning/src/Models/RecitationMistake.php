<?php

namespace Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A word the teacher marked in a lesson's mushaf (the mistakes notebook): its
 * surah, its verse and its place in the verse from 1, and what it was. One
 * mark a word a lesson. Changed only through Actions\Mushaf\{MarkMistake,
 * UnmarkMistake}.
 *
 * @property string $id
 * @property string $student_id
 * @property ?string $enrollment_id
 * @property string $session_id
 * @property string $teacher_id
 * @property int $surah
 * @property int $verse
 * @property int $word
 * @property string $kind wrong | hesitation | tajweed
 */
final class RecitationMistake extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const KINDS = ['wrong', 'hesitation', 'tajweed'];

    protected $fillable = ['student_id', 'enrollment_id', 'session_id', 'teacher_id', 'surah', 'verse', 'word', 'kind'];

    protected function casts(): array
    {
        return ['surah' => 'integer', 'verse' => 'integer', 'word' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
