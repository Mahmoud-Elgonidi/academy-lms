<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Curriculum\Contracts\DTOs\CurriculumNode;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A level, unit or lesson of a curriculum version.
 *
 * @property string $id
 * @property string $version_id
 * @property ?string $parent_id
 * @property string $kind level | unit | lesson
 * @property int $ordinal
 * @property string $title_ar
 * @property string $title_en
 * @property ?string $objectives
 * @property int $weight
 * @property int $est_sessions
 * @property string $mastery_rule mastered_once | score_twice
 * @property ?array<string, mixed> $text_source a lesson's text (Contracts\DTOs\TextSource)
 */
final class Node extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const KINDS = ['level', 'unit', 'lesson'];

    public const MASTERY_RULES = ['mastered_once', 'score_twice'];

    protected $table = 'curriculum_nodes';

    protected $fillable = ['version_id', 'parent_id', 'kind', 'ordinal', 'title_ar', 'title_en', 'objectives', 'weight', 'est_sessions', 'mastery_rule', 'text_source'];

    protected function casts(): array
    {
        return ['ordinal' => 'integer', 'weight' => 'integer', 'est_sessions' => 'integer', 'text_source' => 'array'];
    }

    /** @return BelongsTo<CurriculumVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class, 'version_id');
    }

    public function toData(): CurriculumNode
    {
        return new CurriculumNode($this->id, $this->parent_id, $this->kind, $this->ordinal, $this->title_ar, $this->title_en, $this->weight, $this->est_sessions, $this->mastery_rule, $this->objectives);
    }
}
