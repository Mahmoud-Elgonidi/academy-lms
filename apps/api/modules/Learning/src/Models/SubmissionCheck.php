<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * How a recording handed in went against what the homework asked to recite,
 * word by word (CheckRecitation, in the background). For the teacher alone,
 * as a help while marking: it never marks the homework itself.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $submission_id
 * @property string $homework_id
 * @property string $status pending | ready | failed
 * @property ?string $reason why it failed (AiFailed's reason)
 * @property ?string $engine
 * @property ?string $heard
 * @property ?array{verses: list<array<string, mixed>>, extra: list<array{after: int, text: string}>, notes: list<array<string, mixed>>, summary: array<string, int>} $result
 * @property ?float $seconds
 * @property CarbonImmutable $created_at
 * @property ?CarbonImmutable $checked_at
 */
final class SubmissionCheck extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['submission_id', 'homework_id', 'status', 'reason', 'engine', 'heard', 'result', 'seconds', 'checked_at'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['result' => 'array', 'seconds' => 'float', 'created_at' => 'immutable_datetime', 'checked_at' => 'immutable_datetime'];
    }
}
