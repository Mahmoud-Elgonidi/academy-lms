<?php

namespace Modules\Payroll\Listeners;

use Carbon\CarbonImmutable;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Payroll\Contracts\Events\PayrollMonthClosed;
use Modules\Payroll\Queries\PayrollMonth;
use Modules\Payroll\Support\PayrollMonths;

/**
 * Payroll's message (wording in lang/{ar,en}/notifications.php): when a
 * month is closed, each teacher paid in it hears their total. Pay goes to
 * the app and email only, never to WhatsApp.
 */
final class SendPayrollNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly PayrollMonth $payroll,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $notifications->define(new TemplateDefinition(
            key: 'payroll.month_closed', category: 'staff',
            title: 'payroll::notifications.month_closed.title', body: 'payroll::notifications.month_closed.body',
            audience: 'payroll::notifications.audience.teacher', channels: ['push', 'email'],
            sample: ['month' => 'سبتمبر 2026', 'total' => '4,200 ج.م.', 'lessons' => '28'],
        ));
    }

    public function monthClosed(PayrollMonthClosed $event): void
    {
        $month = CarbonImmutable::parse($event->month);
        $byTeacher = [];
        foreach ($this->payroll->teachers($month) as $row) {
            $byTeacher[$row['teacher_id']][] = $row;
        }

        foreach ($byTeacher as $teacherId => $rows) {
            $this->notifications->send(new Message('payroll.month_closed', [$teacherId], [
                'month' => new Localized(PayrollMonths::label($month, 'ar'), PayrollMonths::label($month, 'en')),
                'total' => count($rows) === 1 ? $rows[0]['total'] : new Localized(
                    implode(' + ', array_map(fn (array $r) => $r['total']->format('ar'), $rows)),
                    implode(' + ', array_map(fn (array $r) => $r['total']->format('en'), $rows)),
                ),
                'lessons' => (string) array_sum(array_column($rows, 'lessons')),
            ], ['screen' => 'payroll_month', 'id' => $month->format('Y-m')], "payroll-closed:{$teacherId}:{$month->format('Y-m')}"));
        }
    }
}
