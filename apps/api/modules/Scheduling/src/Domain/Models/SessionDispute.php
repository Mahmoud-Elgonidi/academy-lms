<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The family or the teacher saying a decided lesson's outcome is wrong.
 * The lesson waits in "needs review" while it is open; the academy's
 * decision closes it (upheld when the outcome changed, rejected when it
 * stayed), with an answer the person reads.
 *
 * @property string $id
 * @property string $session_id
 * @property string $raised_by
 * @property string $side family | teacher
 * @property string $reason
 * @property string $note
 * @property string $outcome_before
 * @property string $status open | upheld | rejected
 * @property ?string $outcome_after
 * @property ?string $decided_by
 * @property ?CarbonImmutable $decided_at
 * @property ?string $decision_note
 * @property CarbonImmutable $created_at
 */
final class SessionDispute extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['session_id', 'raised_by', 'side', 'reason', 'note', 'outcome_before', 'status', 'outcome_after', 'decided_by', 'decided_at', 'decision_note'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Session, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
