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
 * @property string $trial_booking family | staff: who books a free trial asked for on the page
 * @property int $response_hours how soon the academy promises to answer a request
 * @property int $trial_notice_hours a family books its own trial at least this long ahead
 * @property ?string $default_trial_subject_id the subject of a trial whose family asked for help choosing
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

    public const TRIAL_BOOKING = ['family', 'staff'];

    protected $fillable = ['enabled', 'content', 'trial_booking', 'response_hours', 'trial_notice_hours', 'default_trial_subject_id', 'updated_by'];

    protected $attributes = ['enabled' => false, 'trial_booking' => 'family', 'response_hours' => 4, 'trial_notice_hours' => 3];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'content' => 'array', 'response_hours' => 'integer', 'trial_notice_hours' => 'integer', 'updated_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** Whether families book their own free trial from the page (the page on, and the academy lets them). */
    public function familyBooks(): bool
    {
        return $this->exists && $this->enabled && $this->trial_booking === 'family';
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
