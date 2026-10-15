<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A page recited for the daily review (Actions\Hifz\ReciteHifzPage): checked
 * word by word in the background, its grade a review of the page, and its
 * recording deleted once heard (the result stays).
 *
 * @property string $id
 * @property string $student_id
 * @property int $page
 * @property string $status pending | ready | failed
 * @property ?string $reason
 * @property ?string $file_path
 * @property ?string $mime
 * @property ?array<string, mixed> $result {verses, extra, summary}
 * @property ?int $grade
 * @property string $recited_by
 * @property CarbonImmutable $created_at
 * @property ?CarbonImmutable $checked_at
 */
final class HifzRecitation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['student_id', 'page', 'status', 'reason', 'file_path', 'mime', 'result', 'grade', 'recited_by', 'checked_at'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['page' => 'integer', 'grade' => 'integer', 'result' => 'array', 'created_at' => 'immutable_datetime', 'checked_at' => 'immutable_datetime'];
    }
}
