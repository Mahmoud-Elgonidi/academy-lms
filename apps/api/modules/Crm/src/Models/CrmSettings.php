<?php

namespace Modules\Crm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The academy's lead settings: whether leads from its page are handed out
 * in turn among those who work leads (on, while it has no row); the offer a
 * family gets with its trial report (off at first; a percentage for some
 * hours); and the free second trial with another teacher (off at first).
 *
 * @property bool $assign_website_leads
 * @property bool $trial_offer
 * @property int $trial_offer_percent 5–50
 * @property int $trial_offer_hours 24–336
 * @property bool $second_trial
 * @property ?string $updated_by
 */
final class CrmSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'crm_settings';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['assign_website_leads', 'trial_offer', 'trial_offer_percent', 'trial_offer_hours', 'second_trial', 'updated_by'];

    protected $attributes = ['assign_website_leads' => true, 'trial_offer' => false, 'trial_offer_percent' => 10, 'trial_offer_hours' => 72, 'second_trial' => false];

    protected function casts(): array
    {
        return [
            'assign_website_leads' => 'boolean',
            'trial_offer' => 'boolean',
            'trial_offer_percent' => 'integer',
            'trial_offer_hours' => 'integer',
            'second_trial' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
