<?php

namespace Modules\Crm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The academy's lead settings: whether leads from its page are handed out
 * in turn among those who work leads (on, while it has no row).
 *
 * @property bool $assign_website_leads
 * @property ?string $updated_by
 */
final class CrmSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'crm_settings';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['assign_website_leads', 'updated_by'];

    protected $attributes = ['assign_website_leads' => true];

    protected function casts(): array
    {
        return ['assign_website_leads' => 'boolean'];
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
