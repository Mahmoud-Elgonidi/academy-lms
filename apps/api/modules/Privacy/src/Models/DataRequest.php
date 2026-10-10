<?php

namespace Modules\Privacy\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A person's request about their data (docs/architecture/03, section 11):
 * a copy of it (built on its own, there for a week), erasure (answered by
 * the academy within 30 days: refused with a reason, or approved and done a
 * day later), or correction of what is wrong (in their words; answered within
 * 30 days, done with a note or refused with a reason). Asked by the person,
 * by their guardian, or written down by the academy for them.
 *
 * @property string $id
 * @property string $kind export | erasure | correction
 * @property string $subject_id
 * @property string $requested_by
 * @property string $status
 * @property ?string $reason
 * @property ?string $answer
 * @property ?CarbonImmutable $due_at
 * @property ?CarbonImmutable $erase_after
 * @property ?string $decided_by
 * @property ?CarbonImmutable $decided_at
 * @property ?CarbonImmutable $done_at
 * @property ?string $file_path
 * @property ?int $file_size
 * @property ?CarbonImmutable $expires_at
 * @property array<string, mixed> $summary
 * @property CarbonImmutable $created_at
 */
final class DataRequest extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const EXPORT = 'export';

    public const ERASURE = 'erasure';

    public const CORRECTION = 'correction';

    /** The kinds the academy answers (within ANSWER_DAYS). */
    public const ANSWERED = [self::ERASURE, self::CORRECTION];

    /** An erasure or a correction is answered within this many days (PDPL, GDPR). */
    public const ANSWER_DAYS = 30;

    /** An approved erasure happens this many hours later: time for the last messages, and to take it back. */
    public const GRACE_HOURS = 24;

    /** A copy can be downloaded this many days. */
    public const KEEP_DAYS = 7;

    /** One copy a day per person. */
    public const EXPORT_EVERY_HOURS = 24;

    /** Where copies are kept: the private disk, under the academy's folder. */
    public const DISK = 'local';

    public const OPEN = ['pending', 'approved'];

    protected $fillable = ['kind', 'subject_id', 'requested_by', 'status', 'reason', 'answer', 'due_at', 'erase_after', 'decided_by', 'decided_at', 'done_at', 'file_path', 'file_size', 'expires_at', 'summary'];

    protected $attributes = ['status' => 'pending', 'summary' => '{}'];

    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime', 'erase_after' => 'immutable_datetime', 'decided_at' => 'immutable_datetime',
            'done_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
            'summary' => 'array', 'file_size' => 'integer',
        ];
    }

    /** The status in the reader's words ("done" is "erased" for an erasure, "corrected" for a correction). */
    public function statusLabel(): string
    {
        return __($this->kind === self::CORRECTION ? "privacy::requests.correction_statuses.{$this->status}" : "privacy::requests.statuses.{$this->status}");
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function isDownloadable(?CarbonImmutable $now = null): bool
    {
        return $this->kind === self::EXPORT && $this->status === 'ready' && $this->file_path !== null
            && ($this->expires_at === null || $this->expires_at->greaterThan($now ?? CarbonImmutable::now()));
    }

    public function isOverdue(?CarbonImmutable $now = null): bool
    {
        return in_array($this->kind, self::ANSWERED, true) && $this->status === 'pending' && $this->due_at !== null && $this->due_at->lessThan($now ?? CarbonImmutable::now());
    }
}
