<?php

namespace Modules\Platform\Models\Metering;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * What one AI capability costs in credits, as the platform's owners set it
 * (console › AI credit rates, SaveAiCreditRates): a base each request and so
 * much each minute of audio heard. With no row, the capability's own rates.
 * Platform connection only.
 *
 * @property string $capability
 * @property int $base
 * @property int $per_minute
 * @property ?string $updated_by
 * @property CarbonImmutable $updated_at
 */
final class AiCreditRate extends Model
{
    protected $connection = 'pgsql_platform';

    protected $table = 'ai_credit_rates';

    protected $primaryKey = 'capability';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['base' => 'integer', 'per_minute' => 'integer', 'updated_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
