<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Homework set after a lesson: text, a file, or a voice recording, and
 * practice in the games on the lesson's own items (or only that).
 *
 * @property string $id
 * @property string $academy_id
 * @property ?string $enrollment_id
 * @property ?string $session_id
 * @property ?string $report_id
 * @property string $teacher_id
 * @property string $student_id
 * @property string $title
 * @property ?string $instructions
 * @property string $accepts any | text | audio | file | practice (handed in by playing the lessons' practice)
 * @property CarbonImmutable $due_at
 * @property string $status assigned | submitted | reviewed | missed
 * @property ?int $grade 1–5
 * @property ?string $feedback
 * @property ?string $reviewed_by
 * @property ?CarbonImmutable $reviewed_at
 * @property ?list<string> $practice_nodes the report's lessons whose practice it asks for
 * @property ?array{correct: int, total: int, stars: int, game: string, played_at: string} $practice_result the best round played for it
 */
final class Homework extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const ACCEPTS = ['any', 'text', 'audio', 'file', 'practice'];

    public const STATUSES = ['assigned', 'submitted', 'reviewed', 'missed'];

    protected $table = 'homework';

    protected $fillable = [
        'enrollment_id', 'session_id', 'report_id', 'teacher_id', 'student_id', 'title', 'instructions', 'accepts', 'due_at',
        'status', 'grade', 'feedback', 'reviewed_by', 'reviewed_at', 'practice_nodes', 'practice_result',
    ];

    protected $attributes = ['accepts' => 'any', 'status' => 'assigned'];

    protected function casts(): array
    {
        return ['due_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'grade' => 'integer', 'practice_nodes' => 'array', 'practice_result' => 'array'];
    }

    /** Whether it comes with practice in the games. */
    public function hasPractice(): bool
    {
        return $this->practice_nodes !== null && $this->practice_nodes !== [];
    }

    /** @return HasMany<HomeworkSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class)->orderBy('submitted_at');
    }
}
