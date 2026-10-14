<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A course's money agreement: its plan, price and blocks. Its courses are
 * the enrollments on its credit account: one, or every course a student
 * takes on a shared package ("12 lessons in any subject"), whose first
 * course is the enrollment it was opened for.
 *
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

    /** The live subscription an enrollment's lessons are paid from: its own, or the shared package it draws on. */
    public static function ofEnrollment(string $enrollmentId): ?self
    {
        $account = DB::table('enrollments')->where('id', $enrollmentId)->value('credit_account_id');

        return self::query()->with('plan')->where('status', '<>', 'cancelled')
            ->where(fn ($q) => $account === null ? $q->where('enrollment_id', $enrollmentId) : $q->where('credit_account_id', $account))
            ->latest()->first();
    }

    /** @return list<string> the enrollments drawing on it (one, or a shared package's courses), the first first */
    public function enrollmentIds(): array
    {
        return DB::table('enrollments')->where('credit_account_id', $this->credit_account_id)->orderBy('created_at')->orderBy('id')
            ->pluck('id')->map(fn ($id) => (string) $id)->all() ?: [$this->enrollment_id];
    }

    public function isShared(): bool
    {
        return $this->plan?->isShared() ?? false;
    }

    /**
     * Those with a course on (one of a shared package's will do): not waiting
     * for its first payment, paused, ended or set to end. Only these renew.
     */
    public function scopeTaught(Builder $query): void
    {
        $query->whereExists(fn ($q) => $q->from('enrollments as e')->whereColumn('e.academy_id', 'subscriptions.academy_id')
            ->whereColumn('e.credit_account_id', 'subscriptions.credit_account_id')->where('e.status', 'active')->whereNull('e.ends_on'));
    }

    /** A block of its waiting to be paid (on its own invoice, or with the family's). */
    public function hasWaitingBlock(): bool
    {
        return Invoice::query()->forSubscription($this->id, 'block')->where('status', 'issued')->exists();
    }

    public function price(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }

    /** @return array{0: string, 1: string} the course's subject as invoice lines name it, in Arabic and English ("shared package" for one) */
    public function subjectNames(): array
    {
        if ($this->isShared()) {
            return [__('billing::billing.lines.shared', [], 'ar'), __('billing::billing.lines.shared', [], 'en')];
        }
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
