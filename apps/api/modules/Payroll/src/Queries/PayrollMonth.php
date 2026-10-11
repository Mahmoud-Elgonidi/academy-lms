<?php

namespace Modules\Payroll\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Domain\Pay;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Support\PayrollMonths;
use Modules\SharedKernel\Money\Money;

/**
 * A payroll month as the screens and the export read it: each teacher's
 * totals, a teacher's statement (lessons netted per lesson, bonuses and
 * deductions), and the lessons that should pay but have no price yet.
 */
final class PayrollMonth
{
    public function __construct(
        private readonly PayrollMonths $months,
        private readonly LessonPayFacts $lessons,
    ) {}

    /**
     * @return list<array{teacher_id: string, name: string, currency: string, lessons: int, minutes: float, lesson_pay: Money, bonuses: Money, deductions: Money, total: Money}>
     */
    public function teachers(CarbonImmutable $month, ?string $teacherId = null): array
    {
        return DB::table('teacher_earnings as e')
            ->leftJoin('teacher_earnings as o', fn ($j) => $j->on('o.academy_id', '=', 'e.academy_id')->on('o.id', '=', 'e.reverses_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.teacher_id'))
            ->whereDate('e.period', $month->format('Y-m-d'))
            ->when($teacherId, fn ($q) => $q->where('e.teacher_id', $teacherId))
            ->groupBy('e.teacher_id', 'u.name', 'e.currency')
            ->orderBy('u.name')
            ->get([
                'e.teacher_id', 'u.name', 'e.currency',
                DB::raw("sum(case when e.kind = 'lesson' then 1 when o.kind = 'lesson' then -1 else 0 end) as lessons"),
                DB::raw("sum(case when e.kind = 'lesson' then e.booked_min * e.pay_pct when o.kind = 'lesson' then -(o.booked_min * o.pay_pct) else 0 end) / 100.0 as minutes"),
                DB::raw("sum(case when e.kind = 'lesson' or o.kind = 'lesson' then e.amount_minor else 0 end) as lesson_pay"),
                DB::raw("sum(case when e.kind = 'bonus' or o.kind = 'bonus' then e.amount_minor else 0 end) as bonuses"),
                DB::raw("sum(case when e.kind = 'deduction' or o.kind = 'deduction' then e.amount_minor else 0 end) as deductions"),
                DB::raw('sum(e.amount_minor) as total'),
            ])
            ->map(fn ($r) => [
                'teacher_id' => $r->teacher_id,
                'name' => $r->name,
                'currency' => $r->currency,
                'lessons' => (int) $r->lessons,
                'minutes' => (float) $r->minutes,
                'lesson_pay' => Money::of((int) $r->lesson_pay, $r->currency),
                'bonuses' => Money::of((int) $r->bonuses, $r->currency),
                'deductions' => Money::of((int) $r->deductions, $r->currency),
                'total' => Money::of((int) $r->total, $r->currency),
            ])->values()->all();
    }

    /** @return array<string, Money> currency => the month's total */
    public function totals(CarbonImmutable $month): array
    {
        $totals = [];
        foreach ($this->teachers($month) as $row) {
            $totals[$row['currency']] = isset($totals[$row['currency']]) ? $totals[$row['currency']]->plus($row['total']) : $row['total'];
        }
        ksort($totals);

        return $totals;
    }

    /**
     * A teacher's month, line by line. A lesson shows once with what it adds
     * to this month: a lesson paid and then corrected inside the month nets
     * out, a correction to a closed month shows as its own line.
     *
     * @return array{lessons: list<array<string, mixed>>, adjustments: list<array<string, mixed>>}
     */
    public function statement(CarbonImmutable $month, string $teacherId): array
    {
        $rows = DB::table('teacher_earnings as e')
            ->leftJoin('teacher_earnings as o', fn ($j) => $j->on('o.academy_id', '=', 'e.academy_id')->on('o.id', '=', 'e.reverses_id'))
            // Taken back in this month: a reversal paid in a later month (this one was closed) leaves this one as it was.
            ->leftJoin('teacher_earnings as later', fn ($j) => $j->on('later.academy_id', '=', 'e.academy_id')->on('later.reverses_id', '=', 'e.id')
                ->where('later.period', '<=', $month->format('Y-m-d')))
            ->leftJoin('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.session_id'))
            ->leftJoin('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')->where('p.role', 'student'))
            ->leftJoin('users as st', fn ($j) => $j->on('st.academy_id', '=', 'p.academy_id')->on('st.id', '=', 'p.user_id'))
            ->leftJoin('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 'e.academy_id')->on('sub.id', '=', 'e.subject_id'))
            ->leftJoin('subjects as osub', fn ($j) => $j->on('osub.academy_id', '=', 'o.academy_id')->on('osub.id', '=', 'o.subject_id'))
            ->whereDate('e.period', $month->format('Y-m-d'))
            ->where('e.teacher_id', $teacherId)
            ->orderBy('s.starts_at')->orderBy('e.created_at')
            ->get([
                'e.id', 'e.kind', 'e.session_id', 'e.lesson_on', 'e.outcome', 'e.rule', 'e.session_type', 'e.booked_min', 'e.pay_pct', 'e.unit', 'e.rate_minor',
                'e.amount_minor', 'e.currency', 'e.note', 'e.created_at', 'e.period',
                'o.kind as reversed_kind', 'o.lesson_on as reversed_lesson_on', 'o.outcome as reversed_outcome', 'o.session_type as reversed_type', 'o.booked_min as reversed_booked_min',
                'o.pay_pct as reversed_pay_pct', 'o.note as reversed_note', 'o.period as reversed_period',
                'later.id as reversed_by', 's.starts_at', 's.status as status_now', 'st.name as student',
                DB::raw('coalesce(sub.name_ar, osub.name_ar) as subject_ar'), DB::raw('coalesce(sub.name_en, osub.name_en) as subject_en'),
            ]);

        $lessons = [];
        $adjustments = [];
        foreach ($rows as $r) {
            $isLesson = $r->kind === 'lesson' || $r->reversed_kind === 'lesson';
            if ($isLesson) {
                $key = $r->session_id;
                $lessons[$key] ??= [
                    'session_id' => $r->session_id,
                    'starts_at' => $r->starts_at ? CarbonImmutable::parse($r->starts_at) : null,
                    'lesson_on' => CarbonImmutable::parse($r->lesson_on ?? $r->reversed_lesson_on),
                    'student' => $r->student,
                    'subject_ar' => $r->subject_ar,
                    'subject_en' => $r->subject_en,
                    'amount_minor' => 0,
                    'currency' => $r->currency,
                    'line' => null,
                    'corrected' => false,
                    'was' => null,
                ];
                $lessons[$key]['amount_minor'] += (int) $r->amount_minor;
                if ($r->kind === 'lesson' && $r->reversed_by === null) {
                    $lessons[$key]['line'] = [
                        'outcome' => $r->outcome, 'rule' => $r->rule === null ? null : (int) $r->rule, 'type' => $r->session_type,
                        'booked_min' => (int) $r->booked_min, 'pay_pct' => (int) $r->pay_pct, 'unit' => $r->unit,
                        'rate' => $r->rate_minor === null ? null : Money::of((int) $r->rate_minor, $r->currency),
                    ];
                } elseif ($r->kind === 'reversal') {
                    $lessons[$key]['corrected'] = true;
                    $lessons[$key]['was'] ??= ['outcome' => $r->reversed_outcome, 'type' => $r->reversed_type, 'booked_min' => (int) $r->reversed_booked_min, 'pay_pct' => (int) $r->reversed_pay_pct];
                }

                continue;
            }

            $adjustments[] = [
                'id' => $r->id,
                'kind' => $r->kind === 'reversal' ? 'reversal' : $r->kind,
                'of' => $r->reversed_kind,
                'note' => $r->note ?? $r->reversed_note,
                'money' => Money::of((int) $r->amount_minor, $r->currency),
                'at' => CarbonImmutable::parse($r->created_at),
                'live' => $r->kind !== 'reversal' && $r->reversed_by === null,
                'reversed' => $r->kind !== 'reversal' && $r->reversed_by !== null,
            ];
        }

        $lessons = array_values(array_filter($lessons, fn (array $l) => $l['line'] !== null || $l['amount_minor'] !== 0));
        foreach ($lessons as &$l) {
            $l['money'] = Money::of($l['amount_minor'], $l['currency']);
            $l['earlier'] = $l['lesson_on']->format('Y-m') < $month->format('Y-m');
        }

        return ['lessons' => $lessons, 'adjustments' => $adjustments];
    }

    /**
     * Lessons of the month that should pay but have no line: no price on
     * their teacher's card when they were decided.
     *
     * @return array<string, int> teacher id => how many
     */
    public function unpriced(CarbonImmutable $month, ?string $teacherId = null): array
    {
        [$from, $to] = $this->months->bounds($month);
        $rows = DB::table('sessions as s')
            ->whereIn('s.status', LessonPayFacts::FINAL)->where('s.starts_at', '>=', $from)->where('s.starts_at', '<', $to)
            ->when($teacherId, fn ($q) => $q->where('s.teacher_id', $teacherId))
            ->whereNotExists(fn ($q) => $q->from('teacher_earnings as e')->whereColumn('e.academy_id', 's.academy_id')->whereColumn('e.session_id', 's.id')->where('e.kind', 'lesson')
                ->whereNotExists(fn ($r) => $r->from('teacher_earnings as r')->whereColumn('r.academy_id', 'e.academy_id')->whereColumn('r.reverses_id', 'e.id')))
            ->get(['s.teacher_id', 's.status', 's.decision']);

        $counts = [];
        foreach ($rows as $r) {
            if (Pay::share($r->status, $r->decision === null ? null : json_decode($r->decision, true)) > 0) {
                $counts[$r->teacher_id] = ($counts[$r->teacher_id] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /** @return array{closed: bool, closed_at: ?CarbonImmutable, closed_by: ?string, over: bool, pending: int} */
    public function state(CarbonImmutable $month): array
    {
        $period = PayrollPeriod::query()->whereDate('month', $month->format('Y-m-d'))->first();
        [$from, $to] = $this->months->bounds($month);

        return [
            'closed' => $period !== null,
            'closed_at' => $period?->closed_at,
            'closed_by' => $period ? DB::table('users')->where('id', $period->closed_by)->value('name') : null,
            'over' => $month->lessThan($this->months->current()),
            'pending' => $this->lessons->pendingBetween($from, $to->min(CarbonImmutable::now())),
        ];
    }
}
