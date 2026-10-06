<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * Everything the "student account" shows about one enrollment, read across
 * modules (docs/architecture/04, rule 2: reads may join): weekly times,
 * credit in sessions and minutes, upcoming sessions, the ledger, invoices.
 */
final class EnrollmentAccountQuery
{
    public function __construct(private readonly LocalTimeResolver $resolver) {}

    /** @return array<string, mixed>|null */
    public function header(string $enrollmentId): ?array
    {
        $on = fn (string $alias, string $key) => fn ($j) => $j->on("{$alias}.academy_id", '=', 'e.academy_id')->on("{$alias}.id", '=', $key);

        $row = DB::table('enrollments as e')
            ->join('users as st', $on('st', 'e.student_id'))
            ->join('users as te', $on('te', 'e.teacher_id'))
            ->join('subjects as su', $on('su', 'e.subject_id'))
            ->leftJoin('credit_accounts as ca', $on('ca', 'e.credit_account_id'))
            ->leftJoin('subscriptions as sb', fn ($j) => $j->on('sb.academy_id', '=', 'e.academy_id')->on('sb.enrollment_id', '=', 'e.id')->where('sb.status', '<>', 'cancelled'))
            ->leftJoin('plans as pl', fn ($j) => $j->on('pl.academy_id', '=', 'sb.academy_id')->on('pl.id', '=', 'sb.plan_id'))
            ->leftJoin('billing_accounts as ba', fn ($j) => $j->on('ba.academy_id', '=', 'sb.academy_id')->on('ba.id', '=', 'sb.billing_account_id'))
            ->leftJoin('users as pa', fn ($j) => $j->on('pa.academy_id', '=', 'ba.academy_id')->on('pa.id', '=', 'ba.payer_id'))
            ->where('e.id', $enrollmentId)
            ->first([
                'e.id', 'e.status', 'e.start_date', 'e.credit_account_id', 'e.created_at',
                'st.name as student', 'st.timezone as student_tz', 'te.name as teacher', 'te.timezone as teacher_tz',
                'su.name_ar as subject_ar', 'su.name_en as subject_en',
                'ca.available_min', 'ca.reserved_min', 'ca.consumed_min', 'ca.low_balance_min',
                'sb.id as subscription_id', 'sb.block_no', 'sb.amount_minor', 'sb.currency',
                'pl.name_ar as plan_ar', 'pl.name_en as plan_en', 'pl.session_minutes', 'pl.sessions_per_block', 'pl.renew_at_sessions',
                'pa.name as payer', 'pa.id as payer_id', 'st.id as student_id',
            ]);

        return $row ? (array) $row : null;
    }

    /**
     * Weekly times on the student's clock, with the teacher's clock beside.
     *
     * @return list<array{student_day: int, student_time: string, teacher_day: int, teacher_time: string, minutes: int}>
     */
    public function weeklyTimes(string $enrollmentId, string $studentTz): array
    {
        $slots = DB::table('recurring_slots')->where('enrollment_id', $enrollmentId)->where('status', 'active')->orderBy('weekday')->orderBy('local_start')->get();
        $today = CarbonImmutable::now();

        return $slots->map(function ($slot) use ($studentTz, $today) {
            $local = $today->setTimezone($slot->anchor_tz)->startOfDay();
            $date = $local->addDays(((int) $slot->weekday - $local->dayOfWeekIso + 7) % 7)->toDateString();
            $at = $this->resolver->resolve($date, substr($slot->local_start, 0, 5), $slot->anchor_tz)->utc->setTimezone($studentTz);

            return [
                'student_day' => $at->dayOfWeekIso,
                'student_time' => $at->format('H:i'),
                'teacher_day' => (int) $slot->weekday,
                'teacher_time' => substr($slot->local_start, 0, 5),
                'minutes' => (int) $slot->duration_min,
            ];
        })->sortBy(fn ($t) => ($t['student_day'] % 7) * 10000 + (int) str_replace(':', '', $t['student_time']))->values()->all();
    }

    /** @return list<object> the next sessions, soonest first */
    public function upcoming(string $enrollmentId, int $limit = 8): array
    {
        return DB::table('sessions')->where('enrollment_id', $enrollmentId)
            ->where('starts_at', '>=', CarbonImmutable::now()->subHours(2))
            ->orderBy('starts_at')->limit($limit)
            ->get(['id', 'starts_at', 'ends_at', 'status', 'funding_status', 'type', 'is_detached'])->all();
    }

    /** @return array{done: int, upcoming: int} */
    public function counts(string $enrollmentId): array
    {
        $row = DB::table('sessions')->where('enrollment_id', $enrollmentId)->selectRaw(
            "count(*) filter (where status in ('completed', 'student_absent')) as done, count(*) filter (where status = 'scheduled' and starts_at > now()) as upcoming",
        )->first();

        return ['done' => (int) $row->done, 'upcoming' => (int) $row->upcoming];
    }

    /**
     * Ledger lines that change what the student has (reservations only move
     * minutes from free to booked, so they stay out), newest first, with the
     * session's start or the invoice number they refer to.
     *
     * @return list<object>
     */
    public function statement(?string $creditAccountId, int $limit = 30): array
    {
        if ($creditAccountId === null) {
            return [];
        }

        return DB::table('credit_entries as ce')
            ->leftJoin('credit_lots as cl', fn ($j) => $j->on('cl.academy_id', '=', 'ce.academy_id')->on('cl.id', '=', 'ce.lot_id'))
            ->leftJoin('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'ce.academy_id')->on('s.id', '=', 'ce.session_id'))
            ->leftJoin('invoices as i', fn ($j) => $j->on('i.academy_id', '=', 'cl.academy_id')->on('i.id', '=', 'cl.source_id'))
            ->where('ce.account_id', $creditAccountId)
            ->where('ce.type', '<>', 'reserve')
            ->orderByDesc('ce.occurred_at')->orderByDesc('ce.id')
            ->limit($limit)
            ->get(['ce.type', 'ce.minutes', 'ce.available_after', 'ce.reserved_after', 'ce.reason', 'ce.meta', 'ce.occurred_at', 's.starts_at as session_at', 'i.number as invoice', 'cl.source', 'cl.expires_at'])
            ->all();
    }

    /** @return list<object> */
    public function invoices(?string $subscriptionId): array
    {
        if ($subscriptionId === null) {
            return [];
        }

        return DB::table('invoices')->where('subscription_id', $subscriptionId)->orderByDesc('issued_at')
            ->get(['id', 'number', 'status', 'total_minor', 'paid_minor', 'currency', 'issued_at', 'due_at', 'paid_at'])->all();
    }

    /** @return array{minutes: int, expires_at: ?string} makeup credit owed and when it lapses */
    public function makeupOwed(?string $creditAccountId): array
    {
        $row = $creditAccountId ? DB::table('credit_lots')->where('account_id', $creditAccountId)->where('source', 'makeup')->where('remaining_min', '>', 0)
            ->selectRaw('coalesce(sum(remaining_min), 0) as minutes, min(expires_at) as expires_at')->first() : null;

        return ['minutes' => (int) ($row->minutes ?? 0), 'expires_at' => $row->expires_at ?? null];
    }

    public function nextExpiry(?string $creditAccountId): ?object
    {
        return $creditAccountId ? DB::table('credit_lots')->where('account_id', $creditAccountId)->where('remaining_min', '>', 0)->where('source', '<>', 'makeup')
            ->whereNotNull('expires_at')->orderBy('expires_at')->first(['expires_at', 'remaining_min']) : null;
    }
}
