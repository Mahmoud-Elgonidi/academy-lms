<?php

namespace Modules\Import\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A row of an imported file, with what the dry run said of it (new, update,
 * skip, error) and then what the import did (created, updated, skipped,
 * failed).
 *
 * @property string $id
 * @property string $run_id
 * @property int $line
 * @property array<int|string, string> $cells
 * @property string $status
 * @property ?string $message
 * @property array<string, mixed> $refs
 */
final class ImportRow extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const CHECKED = ['new', 'update', 'skip', 'error'];

    public const DONE = ['created', 'updated', 'skipped', 'failed'];

    protected $fillable = ['run_id', 'line', 'cells', 'status', 'message', 'refs'];

    protected $attributes = ['status' => 'pending', 'refs' => '{}'];

    protected function casts(): array
    {
        return ['cells' => 'array', 'refs' => 'array', 'line' => 'integer'];
    }

    /**
     * The row's values by field, through the run's column mapping.
     *
     * @param  array<string, int|null>  $mapping
     * @return array<string, string>
     */
    public function values(array $mapping): array
    {
        $values = [];
        foreach ($mapping as $field => $column) {
            $values[$field] = $column === null ? '' : trim((string) ($this->cells[$column] ?? $this->cells[(string) $column] ?? ''));
        }

        return $values;
    }
}
