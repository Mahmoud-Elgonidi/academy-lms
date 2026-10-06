<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * What the student handed in. Files live on the private disk; they are only
 * served through an authorised download.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $homework_id
 * @property ?string $body
 * @property ?string $file_path
 * @property ?string $file_name
 * @property ?string $mime
 * @property ?int $size
 * @property string $submitted_by
 * @property CarbonImmutable $submitted_at
 */
final class HomeworkSubmission extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['homework_id', 'body', 'file_path', 'file_name', 'mime', 'size', 'submitted_by', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime', 'size' => 'integer'];
    }

    public function isAudio(): bool
    {
        return str_starts_with((string) $this->mime, 'audio/');
    }
}
