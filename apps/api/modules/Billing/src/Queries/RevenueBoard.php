<?php

namespace Modules\Billing\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Discounts\InvoiceDiscounts;
use Modules\Billing\Domain\Revenue\Split;
use Modules\Billing\Domain\TaxCalculator;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Support\RevenuePeriod;
use Modules\Credits\Contracts\CreditsApi;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The academy's money at a glance (docs/architecture/01, section 17), one
 * currency at a time (amounts in different currencies are never added):
 * what came in (payments, by the day they arrived on the academy's clock),
 * what was invoiced, what is still owed and late, and the renewals coming.
 * Money on a family invoice is shared between its courses in proportion to
 * their lines (each course's block less its own discounts), so the parts
 * add up to what was paid. Read from the source tables every time.
 */
final class RevenueBoard
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CreditsApi $credits,
        private readonly InvoiceDiscounts $discounts,
    ) {}

    public function timezone(): string
    {
        return $this->tenant->require()->defaultTimezone;
    }

    public function period(?string $preset, ?string $from = null, ?string $to = null): RevenuePeriod
    {
        return RevenuePeriod::make($preset, $from, $to, $this->timezone(), CarbonImmutable::now());
    }

    /** @return list<string> the currencies the academy invoices in, the most used first; its prices' currency when nothing is invoiced yet */
    public function currencies(): array
    {
        $used = DB::table('invoices')->whereIn('status', ['issued', 'paid'])->groupBy('currency')
            ->orderByRaw('count(*) desc')->orderBy('currency')->pluck('currency')->map(fn ($c) => (string) $c)->all();

        return $used !== [] ? $used : DB::table('price_regions')->orderByDesc('is_default')->orderBy('sort')->limit(1)->pluck('currency')->map(fn ($c) => (string) $c)->all();
    }

    /**
     * The tiles: collected (and its VAT), invoiced, discounts given, owed and
     * late now, each compared with the period before where it is a flow.
     *
     * @return array{collected: Money, collected_before: Money, tax: Money, net: Money, payments: int, payers: int, invoiced: Money, invoiced_before: Money, invoices: int, discounts: Money, outstanding: Money, outstanding_count: int, overdue: Money, overdue_count: int}
     */
    public function summary(RevenuePeriod $period, string $currency): array
    {
        $money = fn ($minor) => Money::of((int) $minor, $currency);
        $in = $this->collected($period, $currency);
        $before = $this->collected($period->previous(), $currency);
        $issued = $this->invoiced($period, $currency);
        $issuedBefore = $this->invoiced($period->previous(), $currency);

        [$from, $to] = $period->bounds();
        $discounts = (int) DB::table('invoice_lines as l')
            ->join('invoices as i', fn ($j) => $j->on('i.academy_id', '=', 'l.academy_id')->on('i.id', '=', 'l.invoice_id'))
            ->where('l.kind', 'discount')->where('i.currency', $currency)->whereIn('i.status', ['issued', 'paid'])
            ->where('i.issued_at', '>=', $from)->where('i.issued_at', '<', $to)
            ->sum('l.amount_minor');

        $now = CarbonImmutable::now();
        $owed = DB::table('invoices')->where('status', 'issued')->where('currency', $currency)
            ->selectRaw('coalesce(sum(total_minor - paid_minor), 0) as amount, count(*) as invoices')
            ->selectRaw('coalesce(sum(total_minor - paid_minor) filter (where due_at < ?), 0) as late, count(*) filter (where due_at < ?) as late_invoices', [$now, $now])
            ->first();

        return [
            'collected' => $money($in->amount),
            'collected_before' => $money($before->amount),
            'tax' => $money($in->tax),
            'net' => $money($in->amount - $in->tax),
            'payments' => (int) $in->payments,
            'payers' => (int) $in->payers,
            'invoiced' => $money($issued->amount),
            'invoiced_before' => $money($issuedBefore->amount),
            'invoices' => (int) $issued->invoices,
            'discounts' => $money(-$discounts),
            'outstanding' => $money($owed->amount),
            'outstanding_count' => (int) $owed->invoices,
            'overdue' => $money($owed->late),
            'overdue_count' => (int) $owed->late_invoices,
        ];
    }

    /**
     * The chart: what came in and what was invoiced, bar by bar.
     *
     * @return list<array{first: CarbonImmutable, last: CarbonImmutable, collected: int, invoiced: int}>
     */
    public function series(RevenuePeriod $period, string $currency): array
    {
        $bars = array_map(fn (array $b) => ['first' => $b['first'], 'last' => $b['last'], 'collected' => 0, 'invoiced' => 0], $period->buckets());
        $index = $period->dayIndex();
        $tz = $period->timezone;

        $byDay = fn (Builder $q, string $column, string $amount) => $q
            ->selectRaw("to_char(({$column} at time zone ?)::date, 'YYYY-MM-DD') as day, sum({$amount}) as amount", [$tz])
            ->groupBy('day')->pluck('amount', 'day');

        foreach ($byDay($this->payments($period, $currency), 'p.received_at', 'p.amount_minor') as $day => $amount) {
            if (isset($index[$day])) {
                $bars[$index[$day]]['collected'] += (int) $amount;
            }
        }
        foreach ($byDay($this->issued($period, $currency), 'i.issued_at', 'i.total_minor') as $day => $amount) {
            if (isset($index[$day])) {
                $bars[$index[$day]]['invoiced'] += (int) $amount;
            }
        }

        return $bars;
    }

    /**
     * Where the money that came in went, four ways: how it was paid, the kind
     * of sale (a new course, a renewal, extra lessons), the subject and the plan.
     *
     * @return array{methods: list<array{key: string, label: string, minor: int, count: int}>, kinds: list<array{key: string, label: string, minor: int}>, subjects: list<array{key: string, label: string, minor: int}>, plans: list<array{key: string, label: string, minor: int}>}
     */
    public function breakdown(RevenuePeriod $period, string $currency): array
    {
        $payments = $this->payments($period, $currency)
            ->leftJoin('payment_checkouts as c', fn ($j) => $j->on('c.academy_id', '=', 'p.academy_id')->on('c.id', '=', 'p.checkout_id'))
            ->leftJoin('gateway_accounts as g', fn ($j) => $j->on('g.academy_id', '=', 'c.academy_id')->on('g.id', '=', 'c.gateway_account_id'))
            ->get(['p.invoice_id', 'p.amount_minor', 'p.method', 'g.gateway']);

        $methods = [];
        foreach ($payments as $p) {
            $key = $p->method === Payment::ONLINE && $p->gateway ? 'online:'.$p->gateway : (string) $p->method;
            $methods[$key] ??= ['key' => $key, 'label' => $this->methodLabel((string) $p->method, $p->gateway), 'minor' => 0, 'count' => 0];
            $methods[$key]['minor'] += (int) $p->amount_minor;
            $methods[$key]['count']++;
        }

        $parts = $this->parts($this->payments($period, $currency)->select('p.invoice_id'));
        $groups = ['kinds' => [], 'subjects' => [], 'plans' => []];
        foreach ($payments as $p) {
            $onInvoice = $parts[(string) $p->invoice_id] ?? [];
            if ($onInvoice === []) {
                continue;
            }
            $shares = Split::byWeight((int) $p->amount_minor, array_map(fn (array $part) => max(0, $part['minor']), $onInvoice));
            foreach ($shares as $key => $share) {
                $part = $onInvoice[$key];
                foreach (['kinds' => 'kind', 'subjects' => 'subject', 'plans' => 'plan'] as $group => $by) {
                    $groups[$group][$part[$by]['key']] ??= ['key' => $part[$by]['key'], 'label' => $part[$by]['label'], 'minor' => 0];
                    $groups[$group][$part[$by]['key']]['minor'] += $share;
                }
            }
        }

        $sorted = function (array $rows): array {
            $rows = array_values($rows);
            usort($rows, fn (array $a, array $b) => [$b['minor'], $a['label']] <=> [$a['minor'], $b['label']]);

            return $rows;
        };

        return [
            'methods' => $sorted($methods),
            'kinds' => $sorted($groups['kinds']),
            'subjects' => $sorted($groups['subjects']),
            'plans' => $sorted($groups['plans']),
        ];
    }

    /**
     * The latest money in, newest first.
     *
     * @return list<array{id: string, invoice_id: string, number: ?string, payer: string, students: string, method: string, minor: int, received_at: CarbonImmutable, reference: ?string}>
     */
    public function recentPayments(RevenuePeriod $period, string $currency, int $limit = 8): array
    {
        $rows = $this->payments($period, $currency)
            ->join('billing_accounts as a', fn ($j) => $j->on('a.academy_id', '=', 'i.academy_id')->on('a.id', '=', 'i.billing_account_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'a.academy_id')->on('u.id', '=', 'a.payer_id'))
            ->leftJoin('payment_checkouts as c', fn ($j) => $j->on('c.academy_id', '=', 'p.academy_id')->on('c.id', '=', 'p.checkout_id'))
            ->leftJoin('gateway_accounts as g', fn ($j) => $j->on('g.academy_id', '=', 'c.academy_id')->on('g.id', '=', 'c.gateway_account_id'))
            ->orderByDesc('p.received_at')->orderByDesc('p.created_at')->limit($limit)
            ->get(['p.id', 'p.invoice_id', 'i.number', 'u.name as payer', 'p.method', 'g.gateway', 'p.amount_minor', 'p.received_at', 'p.reference']);
        $courses = InvoiceCourses::of($rows->pluck('invoice_id')->map(fn ($id) => (string) $id)->unique()->values()->all());

        return $rows->map(fn ($r) => [
            'id' => (string) $r->id,
            'invoice_id' => (string) $r->invoice_id,
            'number' => $r->number,
            'payer' => (string) $r->payer,
            'students' => InvoiceCourses::students($courses[(string) $r->invoice_id] ?? []),
            'method' => $this->methodLabel((string) $r->method, $r->gateway),
            'minor' => (int) $r->amount_minor,
            'received_at' => CarbonImmutable::parse($r->received_at),
            'reference' => $r->reference,
        ])->all();
    }

    /**
     * Invoices past their due date and not paid in full, the oldest first.
     *
     * @return list<array{id: string, number: ?string, payer: string, students: string, owed: int, due_at: CarbonImmutable, days_late: int}>
     */
    public function overdue(string $currency, int $limit = 8): array
    {
        $now = CarbonImmutable::now();
        $rows = DB::table('invoices as i')
            ->join('billing_accounts as a', fn ($j) => $j->on('a.academy_id', '=', 'i.academy_id')->on('a.id', '=', 'i.billing_account_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'a.academy_id')->on('u.id', '=', 'a.payer_id'))
            ->where('i.status', 'issued')->where('i.currency', $currency)->where('i.due_at', '<', $now)
            ->orderBy('i.due_at')->orderBy('i.number')->limit($limit)
            ->get(['i.id', 'i.number', 'u.name as payer', 'i.total_minor', 'i.paid_minor', 'i.due_at']);
        $courses = InvoiceCourses::of($rows->pluck('id')->map(fn ($id) => (string) $id)->all());
        $today = CarbonImmutable::parse($now->setTimezone($this->timezone())->toDateString());

        return $rows->map(function ($r) use ($courses, $today) {
            $due = CarbonImmutable::parse($r->due_at);

            return [
                'id' => (string) $r->id,
                'number' => $r->number,
                'payer' => (string) $r->payer,
                'students' => InvoiceCourses::students($courses[(string) $r->id] ?? []),
                'owed' => (int) $r->total_minor - (int) $r->paid_minor,
                'due_at' => $due,
                'days_late' => max(1, (int) CarbonImmutable::parse($due->setTimezone($this->timezone())->toDateString())->diffInDays($today)),
            ];
        })->all();
    }

    /**
     * Renewals not invoiced yet that should fall due within $days: a course's
     * paid-for minutes left, less its renewal mark, at its plan's pace (the
     * same reckoning IssueRenewal uses), priced as its next invoice would be
     * (its discounts and VAT). An estimate: lessons cancelled or moved shift it.
     *
     * @return array{total: Money, count: int, days: int, items: list<array{subscription_id: string, enrollment_id: string, student: string, subject: string, payer: string, on: CarbonImmutable, minor: int}>}
     */
    public function forecast(string $currency, int $days = 30): array
    {
        $subscriptions = Subscription::query()->with('plan')
            ->where('currency', $currency)->where('status', 'active')->where('block_no', '>=', 1)
            ->whereExists(fn ($q) => $q->from('enrollments as e')->whereColumn('e.academy_id', 'subscriptions.academy_id')
                ->whereColumn('e.credit_account_id', 'subscriptions.credit_account_id')->where('e.status', 'active'))
            ->whereNotExists(fn ($q) => $q->from('invoice_lines as l')
                ->join('invoices as i', fn ($j) => $j->on('i.academy_id', '=', 'l.academy_id')->on('i.id', '=', 'l.invoice_id'))
                ->whereColumn('l.academy_id', 'subscriptions.academy_id')->whereColumn('l.subscription_id', 'subscriptions.id')
                ->where('l.kind', 'block')->where('i.status', 'issued'))
            ->get();
        $balances = $this->credits->balances($subscriptions->pluck('credit_account_id')->map(fn ($id) => (string) $id)->all());
        $today = CarbonImmutable::parse(CarbonImmutable::now()->setTimezone($this->timezone())->toDateString());
        $settings = BillingSettings::current();

        $due = [];
        foreach ($subscriptions as $subscription) {
            $balance = $balances[$subscription->credit_account_id] ?? null;
            $plan = $subscription->plan;
            if ($balance === null || $plan === null) {
                continue;
            }
            $above = $balance->renewableMin() - $plan->terms()->lowBalanceMinutes();
            $perWeek = max(1, $plan->sessions_per_week * $plan->session_minutes);
            $in = $above <= 0 ? 0 : (int) ceil($above * 7 / $perWeek);
            if ($in <= $days) {
                $due[] = [$subscription, $today->addDays($in)];
            }
        }

        $names = $this->courseNames(array_map(fn (array $d) => $d[0], $due));
        $items = [];
        foreach ($due as [$subscription, $on]) {
            $lines = $subscription->amount_minor + array_sum(array_column($this->discounts->lines($subscription, $subscription->amount_minor, withCoupon: true), 'amount_minor'));
            $items[] = $names[$subscription->id] + [
                'subscription_id' => $subscription->id,
                'on' => $on,
                'minor' => TaxCalculator::compute(max(0, $lines), $settings->tax_rate_bp, $settings->prices_include_tax)['total'],
            ];
        }
        usort($items, fn (array $a, array $b) => [$a['on'], $a['student']] <=> [$b['on'], $b['student']]);

        return [
            'total' => Money::of(array_sum(array_column($items, 'minor')), $currency),
            'count' => count($items),
            'days' => $days,
            'items' => $items,
        ];
    }

    /** Payments that arrived in the period, in this currency (aliases p and i). */
    public function payments(RevenuePeriod $period, string $currency): Builder
    {
        [$from, $to] = $period->bounds();

        return DB::table('payments as p')
            ->join('invoices as i', fn ($j) => $j->on('i.academy_id', '=', 'p.academy_id')->on('i.id', '=', 'p.invoice_id'))
            ->where('p.currency', $currency)->where('p.received_at', '>=', $from)->where('p.received_at', '<', $to);
    }

    /** Invoices issued in the period and not cancelled, in this currency (alias i). */
    public function issued(RevenuePeriod $period, string $currency): Builder
    {
        [$from, $to] = $period->bounds();

        return DB::table('invoices as i')->whereIn('i.status', ['issued', 'paid'])->where('i.currency', $currency)
            ->where('i.issued_at', '>=', $from)->where('i.issued_at', '<', $to);
    }

    public function methodLabel(string $method, ?string $gateway): string
    {
        $label = __('billing::billing.methods.'.$method);

        return $method === Payment::ONLINE && $gateway ? $label.' · '.__('billing::payments.gateways.'.$gateway) : $label;
    }

    private function collected(RevenuePeriod $period, string $currency): object
    {
        // VAT in each payment, in proportion to its invoice's.
        return $this->payments($period, $currency)
            ->selectRaw('coalesce(sum(p.amount_minor), 0) as amount, count(*) as payments, count(distinct i.billing_account_id) as payers')
            ->selectRaw('coalesce(sum(round(p.amount_minor::numeric * i.tax_minor / nullif(i.total_minor, 0))), 0) as tax')
            ->first();
    }

    private function invoiced(RevenuePeriod $period, string $currency): object
    {
        return $this->issued($period, $currency)->selectRaw('coalesce(sum(i.total_minor), 0) as amount, count(*) as invoices')->first();
    }

    /**
     * Each invoice's courses with what their lines come to, and what each is:
     * the kind of sale, its subject and plan. Lines for no course are one part each.
     *
     * @return array<string, array<string, array{minor: int, kind: array{key: string, label: string}, subject: array{key: string, label: string}, plan: array{key: string, label: string}}>>
     */
    private function parts(Builder $invoiceIds): array
    {
        $on = fn (string $alias, string $key, string $from) => fn ($j) => $j->on("{$alias}.academy_id", '=', "{$from}.academy_id")->on("{$alias}.id", '=', $key);
        $lines = DB::table('invoice_lines as l')
            ->leftJoin('subscriptions as s', $on('s', 'l.subscription_id', 'l'))
            ->leftJoin('plans as pl', $on('pl', 's.plan_id', 's'))
            ->leftJoin('enrollments as e', $on('e', 's.enrollment_id', 's'))
            ->leftJoin('subjects as sj', $on('sj', 'e.subject_id', 'e'))
            ->whereIn('l.invoice_id', $invoiceIds)
            ->orderBy('l.invoice_id')->orderBy('l.id')
            ->get(['l.id', 'l.invoice_id', 'l.subscription_id', 'l.kind', 'l.amount_minor', DB::raw("(l.meta->>'block_no')::int as block_no"),
                'pl.id as plan_id', 'pl.name_ar as plan_ar', 'pl.name_en as plan_en', 'pl.credit_scope', 'sj.id as subject_id', 'sj.name_ar', 'sj.name_en']);

        $ar = app()->getLocale() === 'ar';
        $other = ['key' => 'other', 'label' => __('billing::revenue.other')];
        $parts = [];
        foreach ($lines as $line) {
            $key = $line->subscription_id !== null ? (string) $line->subscription_id : 'line:'.$line->id;
            $part = &$parts[(string) $line->invoice_id][$key];
            $part ??= ['minor' => 0, 'kind' => null, 'subject' => $other, 'plan' => $other];
            $part['minor'] += (int) $line->amount_minor;
            if ($line->kind !== 'discount' && $part['kind'] === null) {
                $kind = match ($line->kind) {
                    'block' => (int) $line->block_no <= 1 ? 'new' : 'renewal',
                    'extra' => 'extra',
                    default => 'other',
                };
                $part['kind'] = ['key' => $kind, 'label' => __('billing::revenue.kinds.'.$kind)];
            }
            if ($line->plan_id !== null) {
                $part['plan'] = ['key' => (string) $line->plan_id, 'label' => (string) ($ar ? $line->plan_ar : $line->plan_en)];
                $part['subject'] = $line->credit_scope === Plan::SHARED
                    ? ['key' => 'shared', 'label' => __('billing::billing.lines.shared')]
                    : ($line->subject_id !== null ? ['key' => (string) $line->subject_id, 'label' => (string) ($ar ? $line->name_ar : $line->name_en)] : $other);
            }
            unset($part);
        }
        foreach ($parts as &$invoice) {
            foreach ($invoice as &$part) {
                $part['kind'] ??= ['key' => 'other', 'label' => __('billing::revenue.kinds.other')];
            }
        }

        return $parts;
    }

    /**
     * @param  list<Subscription>  $subscriptions
     * @return array<string, array{enrollment_id: string, student: string, subject: string, payer: string}>
     */
    private function courseNames(array $subscriptions): array
    {
        if ($subscriptions === []) {
            return [];
        }
        $on = fn (string $alias, string $key, string $from) => fn ($j) => $j->on("{$alias}.academy_id", '=', "{$from}.academy_id")->on("{$alias}.id", '=', $key);
        $rows = DB::table('subscriptions as s')
            ->join('enrollments as e', $on('e', 's.enrollment_id', 's'))
            ->join('users as st', $on('st', 'e.student_id', 'e'))
            ->join('billing_accounts as a', $on('a', 's.billing_account_id', 's'))
            ->join('users as py', $on('py', 'a.payer_id', 'a'))
            ->leftJoin('subjects as sj', $on('sj', 'e.subject_id', 'e'))
            ->join('plans as pl', $on('pl', 's.plan_id', 's'))
            ->whereIn('s.id', array_map(fn (Subscription $s) => $s->id, $subscriptions))
            ->get(['s.id', 'e.id as enrollment_id', 'st.name as student', 'py.name as payer', 'sj.name_ar', 'sj.name_en', 'pl.credit_scope']);
        $ar = app()->getLocale() === 'ar';

        return $rows->mapWithKeys(fn ($r) => [(string) $r->id => [
            'enrollment_id' => (string) $r->enrollment_id,
            'student' => (string) $r->student,
            'subject' => $r->credit_scope === Plan::SHARED ? __('billing::billing.lines.shared') : (string) ($ar ? $r->name_ar : $r->name_en),
            'payer' => (string) $r->payer,
        ]])->all();
    }
}
