<?php

namespace Modules\Evaluation\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A lesson taken out of its teacher's numbers (docs/architecture/02,
 * section 7: what the platform or the video provider caused does not count
 * against the teacher), with who decided and why.
 *
 * @property string $id
 * @property string $session_id
 * @property string $teacher_id
 * @property string $reason platform | provider | academy | other
 * @property ?string $note
 * @property string $excluded_by
 * @property CarbonImmutable $created_at
 */
final class KpiExclusion extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const REASONS = ['platform', 'provider', 'academy', 'other'];

    protected $fillable = ['session_id', 'teacher_id', 'reason', 'note', 'excluded_by'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
