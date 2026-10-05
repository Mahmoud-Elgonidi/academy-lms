<?php

namespace Modules\Platform\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Platform\Enums\DomainKind;

/**
 * @property string $id
 * @property string $academy_id
 * @property string $host
 * @property DomainKind $kind
 * @property ?CarbonImmutable $verified_at
 */
final class AcademyDomain extends Model
{
    use HasUuids;

    protected $connection = 'pgsql_platform';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => DomainKind::class,
            'verified_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }
}
