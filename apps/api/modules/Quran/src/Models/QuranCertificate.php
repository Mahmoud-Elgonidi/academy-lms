<?php

namespace Modules\Quran\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A certificate a passed exam earned (docs/architecture/02, section 26): its
 * number, the code anyone verifies it by, and the portion, riwaya, grade and
 * examiner as they were at issue. Issued only through
 * Actions\Certificates\IssueQuranCertificate and never changed but to revoke
 * it once (RevokeQuranCertificate; the model and a trigger refuse the rest).
 *
 * @property string $id
 * @property string $exam_id
 * @property string $student_id
 * @property string $program_id
 * @property int $seq
 * @property string $number
 * @property string $code
 * @property array{kind: string, number: ?int, program: string, track: string, scope: array<string, ?int>} $portion
 * @property string $riwaya
 * @property string $grade
 * @property ?string $examiner_id
 * @property ?string $title
 * @property ?string $signature
 * @property CarbonImmutable $issued_at
 * @property ?CarbonImmutable $revoked_at
 * @property ?string $revoke_reason
 */
final class QuranCertificate extends Model
{
    use BelongsToTenant;
    use HasUuids;

    private const REVOCATION = ['revoked_at', 'revoke_reason', 'revoked_by', 'updated_at'];

    protected $fillable = [
        'exam_id', 'student_id', 'program_id', 'seq', 'number', 'code', 'portion', 'riwaya', 'grade', 'examiner_id', 'title', 'signature',
        'issued_at', 'issued_by', 'revoked_at', 'revoke_reason', 'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'seq' => 'integer', 'portion' => 'array', 'issued_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (self $certificate) {
            if ($certificate->getOriginal('revoked_at') !== null || array_diff(array_keys($certificate->getDirty()), self::REVOCATION) !== []) {
                throw new LogicException('An issued certificate is only revoked, once.');
            }
        });
        self::deleting(fn () => throw new LogicException('A certificate is never deleted.'));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
