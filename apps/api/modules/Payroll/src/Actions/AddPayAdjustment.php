<?php

namespace Modules\Payroll\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\RateCard;
use Modules\Payroll\Models\TeacherEarning;
use Modules\Payroll\Support\PayrollMonths;
use Modules\SharedKernel\Money\Money;

/**
 * A bonus or a deduction for a teacher, with the reason they will read on
 * their statement, in the currency of their rate card and in the month asked
 * for (or the first open one after it). A mistake is undone with a
 * reversal, never by editing the line.
 */
final class AddPayAdjustment
{
    public function __construct(private readonly PayrollMonths $months) {}

    public function handle(string $teacherId, string $kind, string|int|float $amount, string $note, string $actorId, ?CarbonImmutable $month = null): TeacherEarning
    {
        if (! in_array($kind, TeacherEarning::ADJUSTMENTS, true)) {
            throw ValidationException::withMessages(['kind' => __('payroll::payroll.errors.kind')]);
        }
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 500) {
            throw ValidationException::withMessages(['note' => __('payroll::payroll.errors.note')]);
        }
        $isTeacher = DB::table('user_roles')->where('user_id', $teacherId)->where('role', 'teacher')->exists();
        $card = $isTeacher ? RateCard::forTeacher($teacherId) : null;
        if ($card === null) {
            throw ValidationException::withMessages(['teacher' => __('payroll::payroll.errors.no_card')]);
        }
        $money = Money::parse(is_string($amount) ? trim($amount) : $amount, $card->currency);
        if ($money->minor <= 0) {
            throw ValidationException::withMessages(['amount' => __('payroll::payroll.errors.amount')]);
        }

        return DB::transaction(function () use ($teacherId, $kind, $money, $note, $actorId, $month) {
            $this->months->lock();

            return TeacherEarning::query()->create([
                'teacher_id' => $teacherId,
                'period' => $this->months->firstOpenFrom($month ?? $this->months->current()),
                'kind' => $kind,
                'amount_minor' => $kind === 'deduction' ? -$money->minor : $money->minor,
                'currency' => $money->currency,
                'note' => $note,
                'created_by' => $actorId,
            ]);
        });
    }

    /** Takes back a bonus or deduction made by mistake: a reversal in the first open month. */
    public function reverse(string $entryId, string $actorId): TeacherEarning
    {
        return DB::transaction(function () use ($entryId, $actorId) {
            $this->months->lock();
            $entry = TeacherEarning::query()->live()->whereKey($entryId)->whereIn('kind', TeacherEarning::ADJUSTMENTS)->first();
            if ($entry === null) {
                throw ValidationException::withMessages(['entry' => __('payroll::payroll.errors.cannot_reverse')]);
            }

            return TeacherEarning::query()->create([
                'teacher_id' => $entry->teacher_id,
                'period' => $this->months->firstOpenFrom($entry->period),
                'kind' => 'reversal',
                'reverses_id' => $entry->id,
                'amount_minor' => -$entry->amount_minor,
                'currency' => $entry->currency,
                'note' => $entry->note,
                'created_by' => $actorId,
            ]);
        });
    }
}
