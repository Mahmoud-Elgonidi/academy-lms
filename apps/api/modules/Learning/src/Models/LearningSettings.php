<?php

namespace Modules\Learning\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\AcademyAudience;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Report and homework settings of the current academy; defaults when it has no row
 * (an adults academy's journey starts plain: no stars, no islands; it may change it).
 *
 * @property int $report_due_hours after the lesson ends
 * @property int $homework_days default time to hand homework in
 * @property string $journey_style playful | calm | plain (how students see their journey)
 * @property bool $weekly_digest the weekly note to guardians goes out (Actions\Digest\SendWeeklyDigest; never in an adults academy)
 */
final class LearningSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['report_due_hours', 'homework_days', 'journey_style', 'weekly_digest'];

    protected $attributes = ['report_due_hours' => 12, 'homework_days' => 3, 'journey_style' => 'playful', 'weekly_digest' => false];

    protected function casts(): array
    {
        return ['report_due_hours' => 'integer', 'homework_days' => 'integer', 'weekly_digest' => 'boolean'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self(app(AcademyAudience::class)->adultsOnly() ? ['journey_style' => 'plain'] : []);
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
