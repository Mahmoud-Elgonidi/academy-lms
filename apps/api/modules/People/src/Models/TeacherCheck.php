<?php

namespace Modules\People\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\People\Contracts\CheckKind;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A document a teacher's vetting rests on (a criminal record check, an ID, a
 * qualification), with its scan on the private disk, checked by the academy:
 * pending, verified (valid until expires_on, or with no end) or turned down
 * with a reason the teacher reads. Never deleted; a newer one takes over.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $teacher_id
 * @property CheckKind $kind
 * @property ?string $reference
 * @property ?CarbonImmutable $issued_on
 * @property ?CarbonImmutable $expires_on
 * @property ?string $file_path
 * @property ?string $file_name
 * @property ?string $mime
 * @property ?int $size
 * @property string $status pending | verified | rejected
 * @property string $uploaded_by
 * @property ?string $decided_by
 * @property ?CarbonImmutable $decided_at
 * @property ?string $decision_note
 * @property ?CarbonImmutable $expiry_warned_at
 * @property ?CarbonImmutable $expired_noted_at
 * @property CarbonImmutable $created_at
 */
final class TeacherCheck extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** Scans and certificates: a photo or the authority's PDF. */
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];

    public const MAX_KB = 10 * 1024;

    public const DISK = 'local';

    /** A verified check is mentioned to the teacher and the academy this long before it runs out. */
    public const WARN_DAYS = 30;

    protected $fillable = ['teacher_id', 'kind', 'reference', 'issued_on', 'expires_on', 'file_path', 'file_name', 'mime', 'size',
        'status', 'uploaded_by', 'decided_by', 'decided_at', 'decision_note', 'expiry_warned_at', 'expired_noted_at'];

    protected function casts(): array
    {
        return [
            'kind' => CheckKind::class,
            'size' => 'integer',
            'issued_on' => 'immutable_date',
            'expires_on' => 'immutable_date',
            'decided_at' => 'immutable_datetime',
            'expiry_warned_at' => 'immutable_datetime',
            'expired_noted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** Verified and, on the academy's calendar $today, not run out. */
    public function isValidOn(CarbonImmutable $today): bool
    {
        return $this->status === 'verified' && ($this->expires_on === null || $this->expires_on->toDateString() >= $today->toDateString());
    }
}
