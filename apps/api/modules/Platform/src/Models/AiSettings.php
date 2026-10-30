<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Which AI helpers the academy turned on (Settings › AI). Every one is off
 * until the academy turns it on. One row, made on first save.
 *
 * @property string $academy_id
 * @property bool $practice_items
 * @property bool $report_draft
 * @property bool $recitation_check
 * @property bool $digest_wording
 * @property bool $curriculum_outline
 * @property ?string $updated_by
 */
final class AiSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'ai_settings';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $guarded = [];

    protected $attributes = ['practice_items' => false, 'report_draft' => false, 'recitation_check' => false, 'digest_wording' => false, 'curriculum_outline' => false];

    protected function casts(): array
    {
        return ['practice_items' => 'boolean', 'report_draft' => 'boolean', 'recitation_check' => 'boolean', 'digest_wording' => 'boolean', 'curriculum_outline' => 'boolean'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    public function isOn(AiFeature $feature): bool
    {
        return (bool) $this->getAttribute($feature->value);
    }
}
