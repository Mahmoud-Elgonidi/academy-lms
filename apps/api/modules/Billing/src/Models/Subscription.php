<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $billing_account_id
 * @property string $enrollment_id
 * @property string $plan_id
 * @property string $credit_account_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $status active | past_due | paused | cancelled
 * @property int $block_no blocks paid so far
 */
final class Subscription extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['billing_account_id', 'enrollment_id', 'plan_id', 'credit_account_id', 'amount_minor', 'currency', 'status', 'block_no'];

    protected $attributes = ['status' => 'active', 'block_no' => 0];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'block_no' => 'integer'];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<BillingAccount, $this> */
    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    public function price(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }

    /** @return array{0: string, 1: string} the course's subject as invoice lines name it, in Arabic and English */
    public function subjectNames(): array
    {
        $subject = DB::table('enrollments as e')->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.subject_id'))
            ->where('e.id', $this->enrollment_id)->first(['s.name_ar', 's.name_en']);

        return [(string) ($subject->name_ar ?? ''), (string) ($subject->name_en ?? '')];
    }

    /** What one lesson of its block costs at the agreed price, rounded half up. */
    public function lessonPrice(): Money
    {
        $sessions = max(1, (int) $this->plan->sessions_per_block);

        return Money::of(intdiv(2 * $this->amount_minor + $sessions, 2 * $sessions), $this->currency);
    }
}
