<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Discounts\InvoiceDiscounts;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;

/**
 * Issues the invoice for one block of a subscription: the plan's sessions
 * at the agreed price, less the family discount or the subscription's
 * coupon, VAT per the academy's settings, the next number. One invoice per
 * block: asking again returns the one already issued. Several courses'
 * blocks go on one invoice when a family's renewals fall due together
 * (IssueRenewal), each block with its own discounts and its student's name.
 */
final class IssueBlockInvoice
{
    public function __construct(private readonly WriteInvoice $write, private readonly InvoiceDiscounts $discounts) {}

    public function handle(Subscription $subscription, int $blockNo, string $subjectAr, string $subjectEn, ?string $actorId = null): Invoice
    {
        return $this->together([[$subscription, $blockNo, $subjectAr, $subjectEn]], $actorId);
    }

    /**
     * One invoice for these courses' blocks, all on one payer's account. The
     * first is the one that came due: if its block is already invoiced, that
     * invoice is the answer; another course whose block already is is left off.
     *
     * @param  non-empty-list<array{0: Subscription, 1: int, 2: string, 3: string}>  $blocks  each course, its block number and its subject's names
     */
    public function together(array $blocks, ?string $actorId = null): Invoice
    {
        return DB::transaction(function () use ($blocks, $actorId) {
            $ids = array_map(fn (array $b) => $b[0]->id, $blocks);
            $locked = Subscription::query()->with('plan')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $family = count($blocks) > 1;
            $lines = [];
            foreach ($blocks as $i => [$given, $blockNo, $subjectAr, $subjectEn]) {
                $subscription = $locked[$given->id];
                $existing = $this->invoiced($subscription, $blockNo);
                if ($existing !== null) {
                    if ($i === 0) {
                        return $existing;
                    }

                    continue;
                }
                $student = $family ? $this->studentName($subscription) : null;
                array_push($lines, ...$this->blockLines($subscription, $blockNo, $subjectAr, $subjectEn, $student));
            }

            $first = $locked[$blocks[0][0]->id];

            return $this->write->forAccount($first->billing_account_id, $first->currency, $lines, $actorId);
        });
    }

    private function invoiced(Subscription $subscription, int $blockNo): ?Invoice
    {
        return Invoice::query()
            ->whereIn('status', ['issued', 'paid'])
            ->whereHas('lines', fn ($q) => $q->where('subscription_id', $subscription->id)->where('kind', 'block')->whereRaw("(meta->>'block_no')::int = ?", [$blockNo]))
            ->first();
    }

    /** @return list<array<string, mixed>> the block and what comes off it, for this course */
    private function blockLines(Subscription $subscription, int $blockNo, string $subjectAr, string $subjectEn, ?string $student): array
    {
        $plan = $subscription->plan;
        if ($subscription->isShared()) {
            [$subjectAr, $subjectEn] = $subscription->subjectNames();     // a shared package is named as one, whichever course opened it
        }
        $describe = fn (string $locale, string $subject, string $planName) => ($student === null ? '' : $student.' · ')
            .__('billing::billing.lines.block', ['subject' => $subject, 'plan' => $planName, 'block' => $blockNo], $locale);
        $block = [
            'kind' => 'block',
            'description_ar' => $describe('ar', $subjectAr, $plan->name_ar),
            'description_en' => $describe('en', $subjectEn, $plan->name_en),
            'quantity' => 1,
            'unit_minor' => $subscription->amount_minor,
            'amount_minor' => $subscription->amount_minor,
            'meta' => [
                'block_no' => $blockNo,
                'minutes' => $plan->terms()->minutes(),
                'sessions' => $plan->sessions_per_block,
                'session_minutes' => $plan->session_minutes,
                'validity_days' => $plan->validity_days,
            ],
        ];

        return array_map(
            fn (array $line) => $line + ['subscription_id' => $subscription->id],
            [$block, ...$this->discounts->lines($subscription, $subscription->amount_minor, withCoupon: true, lateJoin: $blockNo === 1)],
        );
    }

    private function studentName(Subscription $subscription): string
    {
        return (string) DB::table('enrollments as e')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.student_id'))
            ->where('e.id', $subscription->enrollment_id)->value('u.name');
    }
}
