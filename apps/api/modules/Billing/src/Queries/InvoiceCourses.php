<?php

namespace Modules\Billing\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\Plan;

/**
 * What an invoice is for, from its lines: the courses (subscriptions) on
 * it, each with its student and subject (a shared package is named as one).
 * One course on most invoices; a family invoice carries several children's.
 */
final class InvoiceCourses
{
    /**
     * @param  list<string>  $invoiceIds
     * @return array<string, list<array{subscription_id: string, enrollment_id: string, student_id: string, student: string, subject: string, shared: bool}>> by invoice, by student then subject
     */
    public static function of(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }
        $on = fn (string $alias, string $key, string $from) => fn ($j) => $j->on("{$alias}.academy_id", '=', "{$from}.academy_id")->on("{$alias}.id", '=', $key);
        $rows = DB::table('invoice_lines as l')
            ->join('subscriptions as s', $on('s', 'l.subscription_id', 'l'))
            ->join('plans as p', $on('p', 's.plan_id', 's'))
            ->join('enrollments as e', $on('e', 's.enrollment_id', 's'))
            ->join('users as u', $on('u', 'e.student_id', 'e'))
            ->leftJoin('subjects as sj', $on('sj', 'e.subject_id', 'e'))
            ->whereIn('l.invoice_id', $invoiceIds)
            ->distinct()
            ->get(['l.invoice_id', 's.id as subscription_id', 'e.id as enrollment_id', 'u.id as student_id', 'u.name as student', 'sj.name_ar', 'sj.name_en', 'p.credit_scope']);

        $shared = __('billing::billing.lines.shared');
        $courses = [];
        foreach ($rows as $row) {
            $isShared = $row->credit_scope === Plan::SHARED;
            $courses[(string) $row->invoice_id][] = [
                'subscription_id' => (string) $row->subscription_id,
                'enrollment_id' => (string) $row->enrollment_id,
                'student_id' => (string) $row->student_id,
                'student' => (string) $row->student,
                'subject' => $isShared ? $shared : (string) (app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en),
                'shared' => $isShared,
            ];
        }
        foreach ($courses as &$list) {
            usort($list, fn (array $a, array $b) => [$a['student'], $a['subject']] <=> [$b['student'], $b['subject']]);
        }

        return $courses;
    }

    /** @return list<array{subscription_id: string, enrollment_id: string, student_id: string, student: string, subject: string, shared: bool}> */
    public static function ofOne(string $invoiceId): array
    {
        return self::of([$invoiceId])[$invoiceId] ?? [];
    }

    /** @param  list<array{student: string, subject: string}>  $courses  "Ziad · Quran", or "Ziad · Quran، Maryam · Arabic" for a family invoice */
    public static function label(array $courses): string
    {
        return implode(app()->getLocale() === 'ar' ? '، ' : ', ', array_map(fn (array $c) => trim($c['student'].' · '.$c['subject'], ' ·'), $courses));
    }

    /** @param  list<array{student: string}>  $courses  the students, each once: "Ziad", or "Ziad، Maryam" */
    public static function students(array $courses): string
    {
        return implode(app()->getLocale() === 'ar' ? '، ' : ', ', array_values(array_unique(array_column($courses, 'student'))));
    }

    /**
     * Adds to an invoice query the students and subjects its lines are for (student_name, subject_name,
     * course_count), for lists that show and search them.
     */
    public static function withNames(Builder $query): Builder
    {
        $lines = 'from invoice_lines l
            join subscriptions s on s.academy_id = l.academy_id and s.id = l.subscription_id
            join plans p on p.academy_id = s.academy_id and p.id = s.plan_id
            join enrollments e on e.academy_id = s.academy_id and e.id = s.enrollment_id
            join users u on u.academy_id = e.academy_id and u.id = e.student_id
            left join subjects sj on sj.academy_id = e.academy_id and sj.id = e.subject_id
            where l.academy_id = invoices.academy_id and l.invoice_id = invoices.id';
        $subject = app()->getLocale() === 'ar' ? 'sj.name_ar' : 'sj.name_en';
        $joiner = app()->getLocale() === 'ar' ? '، ' : ', ';

        return $query
            ->selectRaw("(select string_agg(distinct u.name, ? order by u.name) {$lines}) as student_name", [$joiner])
            ->selectRaw("(select string_agg(distinct case when p.credit_scope = ? then ? else {$subject} end, ?) {$lines}) as subject_name", [Plan::SHARED, __('billing::billing.lines.shared'), $joiner])
            ->selectRaw("(select count(distinct l.subscription_id) {$lines}) as course_count");
    }

    /** Invoices with a line for a student whose name has this in it. */
    public static function whereStudentNamed(Builder $query, string $search): Builder
    {
        return $query->whereExists(fn ($q) => $q->from('invoice_lines as l')
            ->join('subscriptions as s', fn ($j) => $j->on('s.academy_id', '=', 'l.academy_id')->on('s.id', '=', 'l.subscription_id'))
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.id', '=', 's.enrollment_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.student_id'))
            ->whereColumn('l.academy_id', 'invoices.academy_id')->whereColumn('l.invoice_id', 'invoices.id')
            ->where('u.name', 'ilike', "%{$search}%"));
    }
}
