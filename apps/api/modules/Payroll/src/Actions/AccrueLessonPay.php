<?php

namespace Modules\Payroll\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Domain\Pay;
use Modules\Payroll\Models\RateCard;
use Modules\Payroll\Models\TeacherEarning;
use Modules\Payroll\Queries\LessonPayFacts;
use Modules\Payroll\Support\PayrollMonths;

/**
 * Brings a lesson's line in the pay ledger in step with the lesson
 * (docs/architecture/02, "مرتبات المدرسين"). Called whenever Scheduling says
 * a lesson was decided, cancelled or sent back for review, and safe to call
 * again: when nothing changed, nothing is written.
 *
 *  - over and paid (a share above 0%): one live "lesson" line for whoever
 *    taught it, priced from their rate card;
 *  - anything else (cancelled at 0%, the teacher absent, back under review):
 *    no live line;
 *  - a change (another outcome, share or teacher) reverses the old line and
 *    adds the new one; both go to the first month still open, so a closed
 *    month never moves.
 *
 * A line keeps the price it was written at: a later change to the rate card
 * reaches it only through $reprice (the month's "recalculate"). A lesson with
 * no price on its teacher's card gets no line until one is added; the month
 * shows it as unpriced.
 */
final class AccrueLessonPay
{
    private const BASIS = ['teacher_id', 'outcome', 'pay_pct', 'booked_min', 'session_type', 'subject_id', 'lesson_on'];

    private const PRICE = ['unit', 'rate_minor', 'amount_minor', 'currency', 'rate_card_id'];

    public function __construct(
        private readonly LessonPayFacts $lessons,
        private readonly PayrollMonths $months,
    ) {}

    /** @return TeacherEarning|null the lesson's live line afterwards */
    public function handle(string $sessionId, bool $reprice = false, ?string $actorId = null): ?TeacherEarning
    {
        return DB::transaction(function () use ($sessionId, $reprice, $actorId) {
            $this->months->lock();
            $lesson = $this->lessons->find($sessionId);
            if ($lesson === null) {
                return null;
            }

            $current = TeacherEarning::query()->live()->where('session_id', $sessionId)->where('kind', 'lesson')->first();
            $target = $this->target($lesson);
            $priced = $target !== null && isset($target['amount_minor']);

            if ($current !== null && $target !== null && $this->same($current, $target, self::BASIS)) {
                // Same lesson, same outcome: keep the price it was written at, unless asked to reprice and there is a price to use.
                if (! $reprice || ! $priced || $this->same($current, $target, self::PRICE)) {
                    return $current;
                }
            }
            if ($current === null && ! $priced) {
                return null;
            }

            if ($current !== null) {
                TeacherEarning::query()->create([
                    'teacher_id' => $current->teacher_id,
                    'period' => $this->months->firstOpenFrom($current->period),
                    'kind' => 'reversal',
                    'session_id' => $current->session_id,
                    'reverses_id' => $current->id,
                    'lesson_on' => $current->lesson_on,
                    'outcome' => $lesson->status,
                    'amount_minor' => -$current->amount_minor,
                    'currency' => $current->currency,
                    'created_by' => $actorId,
                ]);
            }

            return $priced ? TeacherEarning::query()->create($target + [
                'period' => $this->months->firstOpenFrom($this->months->of($lesson->starts_at)),
                'kind' => 'lesson',
                'session_id' => $lesson->id,
                'created_by' => $actorId,
            ]) : null;
        });
    }

    /**
     * What the lesson should pay now: null when it pays nothing, the basis
     * alone when it should pay but its teacher's card has no price for it.
     *
     * @return array<string, mixed>|null
     */
    private function target(object $lesson): ?array
    {
        $share = Pay::share($lesson->status, $lesson->decision);
        if ($share === 0) {
            return null;
        }
        $basis = [
            'teacher_id' => $lesson->teacher_id,
            'outcome' => $lesson->status,
            'rule' => isset($lesson->decision['rule']) ? (int) $lesson->decision['rule'] : null,
            'pay_pct' => $share,
            'booked_min' => $lesson->booked_min,
            'session_type' => $lesson->type,
            'subject_id' => $lesson->subject_id,
            'lesson_on' => CarbonImmutable::parse($lesson->starts_at->setTimezone($this->months->timezone())->format('Y-m-d'), 'UTC'),
        ];

        $card = RateCard::forTeacher($lesson->teacher_id);
        $line = $card ? Pay::pick($card->rateLines(), $lesson->subject_id, $lesson->type) : null;
        if ($card === null || $line === null) {
            return $basis;
        }

        return $basis + [
            'unit' => $line->unit,
            'rate_minor' => $line->amountMinor,
            'amount_minor' => Pay::amount($line, $lesson->booked_min, $share),
            'currency' => $card->currency,
            'rate_card_id' => $card->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  list<string>  $fields
     */
    private function same(TeacherEarning $current, array $target, array $fields): bool
    {
        foreach ($fields as $field) {
            $was = $current->{$field};
            $now = $target[$field] ?? null;
            if ($was instanceof CarbonImmutable || $now instanceof CarbonImmutable) {
                [$was, $now] = [$was?->format('Y-m-d'), $now?->format('Y-m-d')];
            }
            if ($was !== $now) {
                return false;
            }
        }

        return true;
    }
}
