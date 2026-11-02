<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Queries\Collections\PayerReach;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Credits\Contracts\Events\CreditsExpiringSoon;

/**
 * The collection timeline's messages (docs/architecture/02, section 27, J5a;
 * sent by Actions\Collections\FollowUpCollections and SendPayLink), and the
 * payer told once that a course's paid-for lessons lapse within a week
 * (CreditsExpiringSoon, from ExpireCredits): never while every course on that
 * balance is paused or over (a pause moves the date, and is told again then).
 */
final class SendCollectionNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly PayerReach $reach,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $invoice = ['number' => 'NR-000002', 'student' => 'زياد', 'left' => '560 ر.س.', 'due' => 'الخميس 8 أكتوبر', 'link' => 'https://noor.example.com/pay/invoice/…'];
        foreach ([
            ['renewal_preview', ['email', 'whatsapp', 'push'], ['student' => 'زياد', 'subject' => 'القرآن', 'plan' => '8 حصص', 'lessons' => 'حصتين', 'amount' => '400 ر.س.', 'on' => 'الأحد 18 أكتوبر']],
            ['due_tomorrow', ['email', 'whatsapp', 'push'], $invoice],
            ['due_today', ['email', 'whatsapp', 'push'], ['due' => 'الخميس 8 أكتوبر 2:00 م'] + $invoice],
            ['overdue', ['email', 'whatsapp', 'push'], $invoice],
            ['lesson_at_risk', ['email', 'whatsapp', 'push'], ['lesson' => 'الثلاثاء 6 أكتوبر 6:00 م', 'until' => 'الاثنين 5 أكتوبر 6:00 م'] + $invoice],
            ['pay_link', ['email', 'whatsapp', 'push'], array_diff_key($invoice, ['due' => 1])],
            ['credits_expiring', ['email', 'whatsapp', 'push'], ['student' => 'زياد', 'subject' => 'القرآن', 'lessons' => '3 حصص', 'on' => 'الخميس 22 أكتوبر']],
        ] as [$name, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "billing.{$name}", category: 'billing',
                title: "billing::notifications.{$name}.title", body: "billing::notifications.{$name}.body",
                audience: 'billing::notifications.audience.payer', channels: $channels, sample: $sample,
            ));
        }
        $notifications->define(new TemplateDefinition(
            key: 'billing.overdue_staff', category: 'staff',
            title: 'billing::notifications.overdue_staff.title', body: 'billing::notifications.overdue_staff.body',
            audience: 'billing::notifications.audience.collections', channels: ['push', 'email'],
            sample: ['payer' => 'أ. منى', 'number' => 'NR-000002', 'student' => 'زياد', 'left' => '560 ر.س.', 'days' => 'يومين'],
        ));
    }

    public function creditsExpiring(CreditsExpiringSoon $event): void
    {
        $subscription = Subscription::query()->with(['plan', 'billingAccount'])->where('credit_account_id', $event->accountId)
            ->where('status', '<>', 'cancelled')->latest()->first();
        $payerId = $subscription?->billingAccount?->payer_id;
        $on = DB::table('enrollments')->where('credit_account_id', $event->accountId)->whereIn('status', ['pending_payment', 'active'])->exists();
        if ($subscription === null || $payerId === null || ! $on) {
            return;
        }
        [$subjectAr, $subjectEn] = $subscription->subjectNames();
        $student = (string) DB::table('credit_accounts as a')->join('users as u', 'u.id', '=', 'a.student_id')->where('a.id', $event->accountId)->value('u.name');
        $per = max(1, (int) ($subscription->plan->session_minutes ?? 0));

        $this->notifications->send(new Message('billing.credits_expiring', $this->reach->recipients($payerId), [
            'student' => $student,
            'subject' => new Localized($subjectAr, $subjectEn),
            'lessons' => Translatable::count('billing::collections.count.lessons', max(1, intdiv($event->minutes, $per))),
            'on' => new Day($event->expiresAt->setTimezone($this->reach->timezone($payerId))->toDateString()),
        ], ['screen' => 'course_balance', 'id' => $subscription->enrollment_id], "credits-expiring:{$event->lotId}:".$event->expiresAt->getTimestamp()));
    }
}
