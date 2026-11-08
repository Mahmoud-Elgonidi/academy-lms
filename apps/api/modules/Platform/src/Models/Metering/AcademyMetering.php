<?php

namespace Modules\Platform\Models\Metering;

use Illuminate\Database\Eloquent\Model;

/**
 * An academy's metering choices: the console's hard limit on video (at zero,
 * new lessons open on the teacher's outside link) and the academy's own 50%
 * alert. No row: soft limit, every alert. Platform connection only (the
 * academy reads its own row).
 *
 * @property string $academy_id
 * @property bool $video_hard_limit
 * @property bool $half_alert
 * @property ?string $updated_by
 */
final class AcademyMetering extends Model
{
    protected $connection = 'pgsql_platform';

    protected $table = 'academy_metering';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $attributes = ['video_hard_limit' => false, 'half_alert' => true];

    protected function casts(): array
    {
        return ['video_hard_limit' => 'boolean', 'half_alert' => 'boolean'];
    }

    public static function for(string $academyId): self
    {
        return self::query()->find($academyId) ?? new self(['academy_id' => $academyId]);
    }
}
