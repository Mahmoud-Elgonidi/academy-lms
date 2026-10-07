<?php

namespace Modules\Import\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One file brought in: its kind, its columns and how they were read, the
 * dry run's verdict and the import's outcome, row by row.
 *
 * @property string $id
 * @property string $kind teachers | families | schedules
 * @property string $file_name
 * @property string $status staged | checked | done
 * @property list<string> $headers
 * @property array<string, int> $mapping
 * @property array<string, mixed> $options
 * @property array<string, int> $counts
 * @property string $created_by
 * @property ?CarbonImmutable $checked_at
 * @property ?CarbonImmutable $finished_at
 * @property CarbonImmutable $created_at
 */
final class ImportRun extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['kind', 'file_name', 'status', 'headers', 'mapping', 'options', 'counts', 'created_by', 'checked_at', 'finished_at'];

    protected $attributes = ['status' => 'staged', 'mapping' => '{}', 'options' => '{}', 'counts' => '{}'];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
            'options' => 'array',
            'counts' => 'array',
            'checked_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'run_id')->orderBy('line');
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
