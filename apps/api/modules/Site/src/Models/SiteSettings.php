<?php

namespace Modules\Site\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;
use Modules\Site\Domain\SiteContent;

/**
 * The current academy's public page: on or off, and what it says
 * (Domain\SiteContent). Off, with the defaults, while it has no row.
 *
 * @property bool $enabled
 * @property array<string, mixed> $content
 * @property ?string $updated_by
 * @property ?CarbonImmutable $updated_at
 */
final class SiteSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'site_settings';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['enabled', 'content', 'updated_by'];

    protected $attributes = ['enabled' => false];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'content' => 'array', 'updated_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    public static function isOn(): bool
    {
        return self::query()->where('enabled', true)->exists();
    }

    /** @return array<string, mixed> what the page says now: the saved content over the defaults */
    public function said(): array
    {
        $saved = $this->exists ? $this->content : null;

        return is_array($saved) ? $saved + SiteContent::defaults() : SiteContent::defaults();
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
