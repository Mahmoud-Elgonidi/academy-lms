<?php

namespace Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One lesson a report covered, and how it went.
 *
 * @property string $id
 * @property string $report_id
 * @property string $node_id
 * @property string $outcome introduced | practiced | mastered | needs_review
 * @property ?int $score 1–5
 * @property ?string $note
 */
final class ReportItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['report_id', 'node_id', 'outcome', 'score', 'note'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }
}
