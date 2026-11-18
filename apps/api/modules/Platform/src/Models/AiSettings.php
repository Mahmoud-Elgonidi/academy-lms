<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\Capability;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Which AI helpers the academy turned on (Settings › AI). Each of the first
 * four is off until the academy turns it on (a column each); a capability
 * registered after them is on unless the academy switched it off (its key in
 * `disabled`). One row, made on first save. Its `recitation_check` column
 * is no longer read (its helper left the platform).
 *
 * @property string $academy_id
 * @property bool $practice_items
 * @property bool $report_draft
 * @property bool $digest_wording
 * @property bool $curriculum_outline
 * @property list<string> $disabled
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

    protected $attributes = ['practice_items' => false, 'report_draft' => false, 'digest_wording' => false, 'curriculum_outline' => false, 'disabled' => '[]'];

    protected function casts(): array
    {
        return ['practice_items' => 'boolean', 'report_draft' => 'boolean', 'digest_wording' => 'boolean', 'curriculum_outline' => 'boolean', 'disabled' => 'array'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    /** The first four by their own column (off at first); any other capability unless the academy switched it off. */
    public function isOn(AiFeature|string|Capability $capability): bool
    {
        $key = $capability instanceof Capability ? $capability->key : Capabilities::key($capability);
        if (AiFeature::tryFrom($key) !== null) {
            return (bool) $this->getAttribute($key);
        }

        return ! in_array($key, (array) $this->disabled, true);
    }

    public function setOn(Capability $capability, bool $on): void
    {
        if ($capability->feature() !== null) {
            $this->setAttribute($capability->key, $on);

            return;
        }
        $off = array_values(array_diff((array) $this->disabled, [$capability->key]));
        $this->disabled = $on ? $off : [...$off, $capability->key];
    }
}
