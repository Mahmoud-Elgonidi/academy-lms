<?php

namespace Modules\Support\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Support\Actions\AssignTicket;
use Modules\Support\Actions\CloseTicket;
use Modules\Support\Actions\DecideComplaint;
use Modules\Support\Actions\FollowUpTickets;
use Modules\Support\Actions\OpenTicket;
use Modules\Support\Actions\ReplyToTicket;
use Modules\Support\Actions\ResolveTicket;
use Modules\Support\Models\Ticket;
use Modules\Support\SupportRules;

/**
 * Tickets in the demo, written and answered the way people do, through the
 * Actions and at their own times: Rana's father's question (closed after a
 * quiet week); Yousef's father complained that the teacher came late (the
 * owner looked into it, confirmed it and resolved it, so it counts in the
 * teacher's numbers); Ziad's sound does not work in class (its first answer
 * is late); Omar's father asks when his transfer will count (the accountant
 * answered; his turn); a teacher's camera keeps turning off (the owner has
 * it, with a note); and Maryam's father complains that the teacher left
 * early (waiting for a decision).
 */
final class DemoTicketsSeeder extends Seeder
{
    public function run(OpenTicket $open, ReplyToTicket $reply, AssignTicket $assign, DecideComplaint $decide, ResolveTicket $resolve, CloseTicket $close, SupportRules $rules): void
    {
        $id = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        $owner = $id('owner@demo.test');
        $accountant = $id('accountant@demo.test');
        $now = CarbonImmutable::now();
        $at = function (CarbonImmutable $when, callable $do) {
            CarbonImmutable::setTestNow($when);
            try {
                return $do();
            } finally {
                CarbonImmutable::setTestNow();
            }
        };
        // The learner's latest lesson that took place before $before (what a family complains about).
        $lesson = function (string $student, CarbonImmutable $before) use ($rules) {
            foreach ($rules->lessonChoices('family', $student, $student, $before) as $l) {
                if (in_array($l->status, ['completed', 'student_absent'], true) && $l->endsAt->lessThan($before)) {
                    return $l;
                }
            }

            return null;
        };

        // Rana's father: a question, answered, resolved, and closed after a quiet week.
        $rana = $id('rana@demo.test');
        $ranasFather = $id('rana.guardian@demo.test');
        $when = $now->subDays(12)->setTime(11, 0);
        if ($rana && $ranasFather) {
            $t = $at($when, fn () => $open->handle($ranasFather, 'family', 'other', 'هل فيه حصص في العيد؟',
                'عايز أعرف لو الحصص هتكمل عادي أيام العيد ولا هتقف.', $rana));
            $at($when->addHours(2), fn () => $resolve->handle($t, $owner, 'الحصص بتقف أول يومين بس، ورصيدكم بيتمد بعدد الأيام دي. كل سنة وانتم طيبين!'));
            $at($when->addDays(Ticket::CLOSE_AFTER_DAYS)->addHour(), fn () => $close->handle($t->refresh(), null));
        }

        // Yousef's father: the teacher came a quarter of an hour late. Confirmed, the lost minutes made up, resolved.
        $yousef = $id('yousef@demo.test');
        $yousefsFather = $id('yousef.guardian@demo.test');
        $when = $now->subDays(6)->setTime(21, 10);
        if ($yousef && $yousefsFather && ($l = $lesson($yousef, $when))) {
            $t = $at($when, fn () => $open->handle($yousefsFather, 'family', 'complaint', 'المدرس اتأخر ربع ساعة',
                "حصة يوسف بدأت متأخر ربع ساعة، وده تاني مرة الشهر ده. يوسف قعد مستني قدام الشاشة.\nياريت تشوفوا الموضوع.", $yousef, $l->id));
            $at($when->addMinutes(40), fn () => $assign->handle($t, $owner, $owner));
            $at($when->addMinutes(45), fn () => $reply->handle($t->refresh(), $owner, 'المدرس قال إن النت كان واقع عنده ربع ساعة. سجل الحضور بيأكد إنه دخل متأخر. التانية الشهر ده فعلًا.', null, note: true));
            $at($when->addHours(1), fn () => $reply->handle($t->refresh(), $owner,
                "آسفين جدًا على اللي حصل. راجعنا الحضور ولقينا إن المدرس دخل متأخر فعلًا، واتكلمنا معاه.\nهنعوّض يوسف الدقايق اللي راحت في الحصة الجاية."));
            $at($when->addHours(2), fn () => $reply->handle($t->refresh(), $yousefsFather, 'شكرًا على الاهتمام 🙏'));
            $at($when->addHours(3), fn () => $decide->handle($t->refresh(), true, 'اتأخر والسبب عنده (النت). تاني مرة الشهر ده، واتفقنا معاه على خطة تحسين.', $owner));
            $at($when->addHours(3)->addMinutes(5), fn () => $resolve->handle($t->refresh(), $owner, 'اتعوّضت الدقايق في الحصة اللي بعدها. لو فيه أي حاجة تانية إحنا موجودين.'));
        }

        // Ziad: no sound in class. Its first answer is late.
        $ziad = $id('ziad@demo.test');
        $when = $now->subHours(30);
        if ($ziad) {
            $at($when, fn () => $open->handle($ziad, 'family', 'technical', 'الصوت مش شغال في الفصل',
                'لما بدخل الحصة مش بسمع المدرس، والمدرس كمان مش سامعني. جربت أقفل وأفتح تاني.', $ziad));
        }

        // Omar's father paid by transfer and sent the receipt: when will it count? The accountant answered; his turn.
        $omar = $id('omar@demo.test');
        $omarsFather = $id('omar.guardian@demo.test');
        $when = $now->subHours(20);
        if ($omar && $omarsFather) {
            $t = $at($when, fn () => $open->handle($omarsFather, 'family', 'billing', 'بعت إيصال التحويل، إمتى هيتأكد؟',
                'الكارت اترفض فحوّلت بالبنك وبعت صورة الإيصال من التطبيق. إمتى الفاتورة هتبان إنها اتدفعت؟', $omar));
            $at($when->addHours(3), fn () => $assign->handle($t, $accountant, $accountant));
            $at($when->addHours(3)->addMinutes(10), fn () => $reply->handle($t->refresh(), $accountant,
                'الإيصال وصلنا، وبنراجع التحويلات مع البنك كل يوم الساعة 2. أول ما يتأكد الفاتورة هتبان مدفوعة ويوصلك إشعار. ممكن تبعتلنا رقم العملية لو معاك؟'));
        }

        // A teacher: the camera turns off by itself. The owner has it, with a note for colleagues.
        $teacher = $id('khaled@demo.test');
        $when = $now->subHours(5);
        if ($teacher) {
            $t = $at($when, fn () => $open->handle($teacher, 'teacher', 'technical', 'الكاميرا بتقفل لوحدها',
                'في نص الحصة الكاميرا بتقفل لوحدها وبحتاج أخرج وأدخل تاني. حصل في آخر حصتين.'));
            $at($when->addMinutes(20), fn () => $assign->handle($t, $owner, $owner));
            $at($when->addMinutes(25), fn () => $reply->handle($t->refresh(), $owner, 'غالبًا من إعدادات توفير الطاقة في اللابتوب. هكلمه بعد حصة الساعة 6.', null, note: true));
        }

        // Maryam's father: the teacher left early. Waiting for the academy's answer and a decision.
        $maryam = $id('maryam@demo.test');
        $maryamsFather = $id('maryam.guardian@demo.test');
        $when = $now->subHours(2);
        if ($maryam && $maryamsFather && ($l = $lesson($maryam, $when))) {
            $at($when, fn () => $open->handle($maryamsFather, 'family', 'complaint', 'المدرسة خرجت قبل الحصة ما تخلص',
                'مريم بتقول إن المدرسة قفلت الحصة قبل ميعادها بحوالي 10 دقايق من غير ما تقول السبب.', $maryam, $l->id));
        }

        // What the five-minute follow-up would have done by now: Ziad's late first answer raised.
        app(FollowUpTickets::class)->handle();
    }
}
