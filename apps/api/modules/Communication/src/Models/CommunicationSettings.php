<?php

namespace Modules\Communication\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Which outside channels the academy uses; defaults when it has no row.
 *
 * @property bool $email_enabled
 * @property bool $whatsapp_enabled
 * @property bool $push_enabled
 */
final class CommunicationSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['email_enabled', 'whatsapp_enabled', 'push_enabled'];

    protected $attributes = ['email_enabled' => true, 'whatsapp_enabled' => false, 'push_enabled' => true];

    protected function casts(): array
    {
        return ['email_enabled' => 'boolean', 'whatsapp_enabled' => 'boolean', 'push_enabled' => 'boolean'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    public function allows(string $channel): bool
    {
        return (bool) $this->getAttribute("{$channel}_enabled");
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
