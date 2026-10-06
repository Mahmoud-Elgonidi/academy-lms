<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Homework set after a lesson: text, a file, or a voice recording.
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
 * @property string $accepts any | text | audio | file
 * @property CarbonImmutable $due_at
 * @property string $status assigned | submitted | reviewed | missed
 * @property ?int $grade 1–5
 * @property ?string $feedback
 * @property ?string $reviewed_by
 * @property ?CarbonImmutable $reviewed_at
 */
final class Homework extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const ACCEPTS = ['any', 'text', 'audio', 'file'];

    public const STATUSES = ['assigned', 'submitted', 'reviewed', 'missed'];

    protected $table = 'homework';

    protected $fillable = [
        'enrollment_id', 'session_id', 'report_id', 'teacher_id', 'student_id', 'title', 'instructions', 'accepts', 'due_at',
        'status', 'grade', 'feedback', 'reviewed_by', 'reviewed_at',
    ];

    protected $attributes = ['accepts' => 'any', 'status' => 'assigned'];

    protected function casts(): array
    {
        return ['due_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'grade' => 'integer'];
    }

    /** @return HasMany<HomeworkSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class)->orderBy('submitted_at');
    }
}
