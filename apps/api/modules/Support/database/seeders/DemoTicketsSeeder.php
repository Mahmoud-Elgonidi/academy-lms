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
            $t = $at($when, fn () => $open->handle($ranasFather, 'family', 'other', 'هل توجد حصص في العيد؟',
                'أريد أن أعرف هل تستمر الحصص كالمعتاد أيام العيد أم تتوقف.', $rana));
            $at($when->addHours(2), fn () => $resolve->handle($t, $owner, 'تتوقف الحصص أول يومين فقط، ويُمدّد رصيدكم بعدد هذه الأيام. كل عام وأنتم بخير!'));
            $at($when->addDays(Ticket::CLOSE_AFTER_DAYS)->addHour(), fn () => $close->handle($t->refresh(), null));
        }

        // Yousef's father: the teacher came a quarter of an hour late. Confirmed, the lost minutes made up, resolved.
        $yousef = $id('yousef@demo.test');
        $yousefsFather = $id('yousef.guardian@demo.test');
        $when = $now->subDays(6)->setTime(21, 10);
        if ($yousef && $yousefsFather && ($l = $lesson($yousef, $when))) {
            $t = $at($when, fn () => $open->handle($yousefsFather, 'family', 'complaint', 'تأخّر المعلّم ربع ساعة',
                "بدأت حصة يوسف متأخرة ربع ساعة، وهذه ثاني مرة هذا الشهر. ظلّ يوسف ينتظر أمام الشاشة.\nأرجو أن تنظروا في الأمر.", $yousef, $l->id));
            $at($when->addMinutes(40), fn () => $assign->handle($t, $owner, $owner));
            $at($when->addMinutes(45), fn () => $reply->handle($t->refresh(), $owner, 'قال المعلّم إن الإنترنت انقطع عنده ربع ساعة. سجل الحضور يؤكد أنه دخل متأخرًا. إنها الثانية هذا الشهر فعلًا.', null, note: true));
            $at($when->addHours(1), fn () => $reply->handle($t->refresh(), $owner,
                "نعتذر كثيرًا عمّا حدث. راجعنا الحضور ووجدنا أن المعلّم دخل متأخرًا فعلًا، وتحدثنا معه.\nسنعوّض يوسف الدقائق التي فاتته في الحصة القادمة."));
            $at($when->addHours(2), fn () => $reply->handle($t->refresh(), $yousefsFather, 'شكرًا على الاهتمام 🙏'));
            $at($when->addHours(3), fn () => $decide->handle($t->refresh(), true, 'تأخّر والسبب من جهته (الإنترنت). ثاني مرة هذا الشهر، واتفقنا معه على خطة تحسين.', $owner));
            $at($when->addHours(3)->addMinutes(5), fn () => $resolve->handle($t->refresh(), $owner, 'عُوّضت الدقائق في الحصة التالية. إن احتجتم إلى أي شيء آخر فنحن هنا.'));
        }

        // Ziad: no sound in class. Its first answer is late.
        $ziad = $id('ziad@demo.test');
        $when = $now->subHours(30);
        if ($ziad) {
            $at($when, fn () => $open->handle($ziad, 'family', 'technical', 'الصوت لا يعمل في الفصل',
                'عندما أدخل الحصة لا أسمع المعلّم، والمعلّم لا يسمعني أيضًا. جرّبت الإغلاق والفتح مرة أخرى.', $ziad));
        }

        // Omar's father paid by transfer and sent the receipt: when will it count? The accountant answered; his turn.
        $omar = $id('omar@demo.test');
        $omarsFather = $id('omar.guardian@demo.test');
        $when = $now->subHours(20);
        if ($omar && $omarsFather) {
            $t = $at($when, fn () => $open->handle($omarsFather, 'family', 'billing', 'أرسلت إيصال التحويل، متى يُؤكَّد؟',
                'رُفضت البطاقة فحوّلت عبر البنك وأرسلت صورة الإيصال من التطبيق. متى تظهر الفاتورة مدفوعة؟', $omar));
            $at($when->addHours(3), fn () => $assign->handle($t, $accountant, $accountant));
            $at($when->addHours(3)->addMinutes(10), fn () => $reply->handle($t->refresh(), $accountant,
                'وصلنا الإيصال، ونراجع التحويلات مع البنك كل يوم الساعة 2. حين يتأكد ستظهر الفاتورة مدفوعة ويصلك إشعار. هل يمكنك إرسال رقم العملية إن كان لديك؟'));
        }

        // A teacher: the camera turns off by itself. The owner has it, with a note for colleagues.
        $teacher = $id('khaled@demo.test');
        $when = $now->subHours(5);
        if ($teacher) {
            $t = $at($when, fn () => $open->handle($teacher, 'teacher', 'technical', 'الكاميرا تنطفئ وحدها',
                'في منتصف الحصة تنطفئ الكاميرا وحدها وأحتاج إلى الخروج والدخول مرة أخرى. حدث ذلك في آخر حصتين.'));
            $at($when->addMinutes(20), fn () => $assign->handle($t, $owner, $owner));
            $at($when->addMinutes(25), fn () => $reply->handle($t->refresh(), $owner, 'غالبًا من إعدادات توفير الطاقة في الحاسوب المحمول. سأتصل به بعد حصة الساعة 6.', null, note: true));
        }

        // Maryam's father: the teacher left early. Waiting for the academy's answer and a decision.
        $maryam = $id('maryam@demo.test');
        $maryamsFather = $id('maryam.guardian@demo.test');
        $when = $now->subHours(2);
        if ($maryam && $maryamsFather && ($l = $lesson($maryam, $when))) {
            $at($when, fn () => $open->handle($maryamsFather, 'family', 'complaint', 'خرجت المعلّمة قبل انتهاء الحصة',
                'تقول مريم إن المعلّمة أغلقت الحصة قبل موعدها بنحو 10 دقائق دون أن تذكر السبب.', $maryam, $l->id));
        }

        // What the five-minute follow-up would have done by now: Ziad's late first answer raised.
        app(FollowUpTickets::class)->handle();
    }
}
