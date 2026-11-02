<?php

namespace Modules\Billing\Actions\Collections;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\UnpaidLessons;
use Modules\Billing\Domain\Collections\CollectionRules;
use Modules\Billing\Models\CollectionStep;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Queries\Collections\PayerReach;
use Modules\Billing\Queries\InvoiceCourses;
use Modules\Billing\Queries\Renewals\NextRenewals;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Every hour (billing:follow-up): the collection timeline (docs/architecture/02,
 * section 27, J5a). Each invoice waiting to be paid gets the step
 * Domain\Collections\CollectionRules says it is owed, on its payer's clock,
 * once (collection_steps): due tomorrow, due today, two days overdue (with a
 * copy to whoever collects: billing.collect), and a day before rule 16 would
 * cancel its first unpaid lesson ("pay to keep Tuesday's lesson", from
 * Billing's UnpaidLessons, which Scheduling answers). And each course whose
 * renewal comes within a few days hears what its next block will be
 * (Renewals\NextRenewals, the forecast's reckoning), once per block.
 *
 * Nothing goes for an invoice paid (also by hand, by the office), voided, or
 * with a transfer receipt waiting (the academy is checking it); an online
 * payment page open waits for the next hour. An invoice none of whose courses
 * is on (all paused, or ended) is quiet: a pause stops the reminders, an
 * ended course's invoice is the office's (closeEndedCourse voids it when it
 * can). The payer hears it (PayerReach: never a child's own account).
 */
final class FollowUpCollections
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly IdentityApi $identity,
        private readonly UnpaidLessons $lessons,
        private readonly NextRenewals $renewals,
        private readonly PayerReach $reach,
    ) {}

    /** @return int how many steps went out */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        return $this->invoices($now) + $this->previews($now);
    }

    private function invoices(CarbonImmutable $now): int
    {
        $open = Invoice::query()->with(['lines', 'billingAccount'])
            ->where('status', 'issued')->whereColumn('paid_minor', '<', 'total_minor')->whereNotNull('due_at')
            // Long overdue is the office's: the timeline never wakes it up.
            ->where('due_at', '>', $now->subDays(CollectionRules::OVERDUE_DAYS + CollectionRules::LATE_DAYS + 2))
            ->whereNotExists(fn ($q) => $q->from('manual_payment_proofs as p')->whereColumn('p.academy_id', 'invoices.academy_id')
                ->whereColumn('p.invoice_id', 'invoices.id')->where('p.status', 'pending'))
            ->whereNotExists(fn ($q) => $q->from('payment_checkouts as c')->whereColumn('c.academy_id', 'invoices.academy_id')
                ->whereColumn('c.invoice_id', 'invoices.id')->where('c.status', 'open')->where('c.expires_at', '>', $now))
            ->orderBy('due_at')->get();
        if ($open->isEmpty()) {
            return 0;
        }
        $sent = CollectionStep::sentFor($open->pluck('id')->all());
        $courses = $this->coursesOf($open);

        $count = 0;
        foreach ($open as $invoice) {
            $payerId = $invoice->billingAccount?->payer_id;
            $on = $courses[$invoice->id] ?? [];
            if ($payerId === null || $on === []) {
                continue;   // no course of it is on: paused, or ended
            }
            $timezone = $this->reach->timezone($payerId);
            $atRisk = ! isset($sent[$invoice->id][CollectionRules::LESSON_AT_RISK]) && $invoice->lines->contains('kind', 'block')
                ? $this->lessons->firstAtRisk($on, $now) : null;
            $step = CollectionRules::due($now, $timezone, $invoice->issued_at, $invoice->due_at, $sent[$invoice->id] ?? [], $atRisk?->cancelsAt);
            if ($step === null) {
                continue;
            }
            $count += DB::transaction(function () use ($invoice, $payerId, $step, $timezone, $atRisk) {
                if (! CollectionStep::claim("{$invoice->id}:{$step}", ['invoice_id' => $invoice->id, 'payer_id' => $payerId, 'step' => $step])) {
                    return 0;
                }
                $data = [
                    'number' => (string) $invoice->number,
                    'student' => InvoiceCourses::students(InvoiceCourses::ofOne($invoice->id)),
                    'left' => $invoice->outstanding(),
                    'due' => new Day($invoice->due_at->setTimezone($timezone)->toDateString()),
                ];
                $data = match ($step) {
                    CollectionRules::DUE_TODAY => ['due' => $invoice->due_at] + $data,
                    CollectionRules::LESSON_AT_RISK => ['lesson' => $atRisk->startsAt, 'until' => $atRisk->cancelsAt] + $data,
                    default => $data,
                };
                $to = $this->reach->recipients($payerId);
                $heard = $this->notifications->send(new Message("billing.{$step}", $to, $data, ['screen' => 'invoice', 'id' => $invoice->id], "collection:{$invoice->id}:{$step}"));
                if ($step === CollectionRules::OVERDUE) {
                    $this->notifications->send(new Message('billing.overdue_staff', $this->identity->usersWithAbility(Ability::CollectPayments), [
                        'payer' => (string) DB::table('users')->where('id', $payerId)->value('name'),
                        'number' => $data['number'],
                        'student' => $data['student'],
                        'left' => $data['left'],
                        'days' => (string) CollectionRules::OVERDUE_DAYS,
                    ], ['screen' => 'invoice', 'id' => $invoice->id], "collection-staff:{$invoice->id}"));
                }
                CollectionStep::query()->where('key', "{$invoice->id}:{$step}")->update(['recipients' => $heard]);

                return 1;
            });
        }

        return $count;
    }

    /**
     * The courses of each invoice that are on now (waiting for their first payment, or running): a shared package's
     * every course on its balance.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array<string, list<string>> enrollment ids by invoice id
     */
    private function coursesOf(Collection $invoices): array
    {
        $rows = DB::table('invoice_lines as l')
            ->join('subscriptions as s', fn ($j) => $j->on('s.academy_id', '=', 'l.academy_id')->on('s.id', '=', 'l.subscription_id'))
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.credit_account_id', '=', 's.credit_account_id'))
            ->whereIn('l.invoice_id', $invoices->pluck('id')->all())
            ->whereIn('e.status', ['pending_payment', 'active'])
            ->orderBy('e.created_at')
            ->get(['l.invoice_id', 'e.id']);
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->invoice_id][] = (string) $row->id;
        }

        return array_map(fn (array $ids) => array_values(array_unique($ids)), $out);
    }

    /** Courses whose next block's invoice comes within a few days: what it will be, once per block. */
    private function previews(CarbonImmutable $now): int
    {
        $subscriptions = Subscription::query()->with(['plan', 'billingAccount'])
            ->where('status', 'active')->where('block_no', '>=', 1)->taught()
            ->whereNotExists(fn ($q) => $q->from('invoice_lines as l')
                ->join('invoices as i', fn ($j) => $j->on('i.academy_id', '=', 'l.academy_id')->on('i.id', '=', 'l.invoice_id'))
                ->whereColumn('l.academy_id', 'subscriptions.academy_id')->whereColumn('l.subscription_id', 'subscriptions.id')
                ->where('l.kind', 'block')->where('i.status', 'issued'))
            ->whereNotExists(fn ($q) => $q->from('collection_steps as c')->whereColumn('c.academy_id', 'subscriptions.academy_id')
                ->whereColumn('c.subscription_id', 'subscriptions.id')->where('c.step', CollectionRules::RENEWAL_PREVIEW)
                ->whereRaw("c.key = subscriptions.id::text || ':renewal_preview:' || (subscriptions.block_no + 1)::text"))
            ->get();
        $next = $this->renewals->of($subscriptions, $now);

        $count = 0;
        foreach ($subscriptions as $subscription) {
            $renewal = $next[$subscription->id] ?? null;
            $payerId = $subscription->billingAccount?->payer_id;
            if ($renewal === null || $payerId === null || ! CollectionRules::previewDue($renewal->days, $now, $this->reach->timezone($payerId))) {
                continue;
            }
            $block = $subscription->block_no + 1;
            $count += DB::transaction(function () use ($subscription, $renewal, $payerId, $block) {
                $key = "{$subscription->id}:renewal_preview:{$block}";
                if (! CollectionStep::claim($key, ['subscription_id' => $subscription->id, 'payer_id' => $payerId, 'step' => CollectionRules::RENEWAL_PREVIEW])) {
                    return 0;
                }
                [$subjectAr, $subjectEn] = $subscription->subjectNames();
                $student = (string) DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->where('e.id', $subscription->enrollment_id)->value('u.name');
                $heard = $this->notifications->send(new Message('billing.renewal_preview', $this->reach->recipients($payerId), [
                    'student' => $student,
                    'subject' => new Localized($subjectAr, $subjectEn),
                    'plan' => new Localized((string) $subscription->plan->name_ar, (string) $subscription->plan->name_en),
                    'lessons' => (string) $renewal->pace->markLessons(),
                    'amount' => $renewal->amount,
                    'on' => new Day($renewal->on->toDateString()),
                ], ['screen' => 'course_balance', 'id' => $subscription->enrollment_id], "renewal-preview:{$subscription->id}:{$block}"));
                CollectionStep::query()->where('key', $key)->update(['recipients' => $heard]);

                return 1;
            });
        }

        return $count;
    }
}
