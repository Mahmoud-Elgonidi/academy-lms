<?php

namespace Modules\Quran\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The current academy's Quran settings; defaults when it has no row.
 *
 * @property int $hifz_daily_pages pages a day at most in a learner's daily hifz review
 */
final class QuranSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'quran_settings';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['hifz_daily_pages'];

    protected $attributes = ['hifz_daily_pages' => 3];

    protected function casts(): array
    {
        return ['hifz_daily_pages' => 'integer'];
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
