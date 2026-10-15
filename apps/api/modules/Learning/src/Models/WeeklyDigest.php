<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A learner's weekly note to their guardians (Actions\Digest\SendWeeklyDigest):
 * one per learner and week, what went out and what it was written from. The
 * words keep the child as {child}; the name goes in only as it is sent.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $student_id
 * @property CarbonImmutable $week_start the week's Saturday on the learner's clock
 * @property string $status pending | sent | skipped
 * @property ?string $reason why it was skipped (off, quiet, no_guardians)
 * @property ?string $source ai | facts
 * @property ?string $text_ar
 * @property ?string $text_en
 * @property ?array<string, mixed> $facts Digest\WeekFacts
 * @property ?string $voice_submission_id the recording of the week
 * @property int $sent_to
 * @property CarbonImmutable $created_at
 * @property ?CarbonImmutable $sent_at
 */
final class WeeklyDigest extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['student_id', 'week_start', 'status', 'reason', 'source', 'text_ar', 'text_en', 'facts', 'voice_submission_id', 'sent_to', 'sent_at'];

    protected $attributes = ['status' => 'pending', 'sent_to' => 0];

    protected function casts(): array
    {
        return [
            'week_start' => 'immutable_date', 'facts' => 'array', 'sent_to' => 'integer',
            'created_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime',
        ];
    }
}
