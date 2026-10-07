<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Domain\RateLine;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $rate_card_id
 * @property ?string $subject_id
 * @property ?string $session_type
 * @property string $unit hour | lesson
 * @property int $amount_minor
 */
final class RateCardLine extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['rate_card_id', 'subject_id', 'session_type', 'unit', 'amount_minor'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function toRateLine(): RateLine
    {
        return new RateLine($this->subject_id, $this->session_type, $this->unit, $this->amount_minor, $this->id);
    }
}
