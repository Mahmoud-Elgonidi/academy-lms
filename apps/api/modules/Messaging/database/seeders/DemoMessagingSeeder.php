<?php

namespace Modules\Messaging\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Messaging\Actions\OpenConversation;
use Modules\Messaging\Actions\SendMessage;
use Modules\Messaging\Models\Conversation;

/**
 * Conversations in the demo, through the Actions at their own times: Nour
 * praises Yousef, his father and he answer, and she wrote again
 * forty minutes ago (unread for them); Layan's mother asks Ahmed to move a lesson and
 * gives a WhatsApp number, which is hidden and waits for the academy's look;
 * Sara and Maryam agree on her next essay.
 */
final class DemoMessagingSeeder extends Seeder
{
    public function run(OpenConversation $open, SendMessage $send): void
    {
        $id = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        $now = CarbonImmutable::now();
        $at = function (CarbonImmutable $when, callable $do) {
            CarbonImmutable::setTestNow($when);
            try {
                return $do();
            } finally {
                CarbonImmutable::setTestNow();
            }
        };
        $talk = function (string $teacher, string $student, array $lines) use ($id, $open, $send, $at) {
            [$teacherId, $studentId] = [$id($teacher), $id($student)];
            if ($teacherId === null || $studentId === null) {
                return;
            }
            $c = $open->handle($teacherId, $teacherId, $studentId);
            foreach ($lines as [$when, $who, $body]) {
                $author = $id($who);
                // Whoever writes has read what came before (SendMessage marks it).
                $at($when, fn () => $send->handle(Conversation::query()->findOrFail($c->id), $author, $body));
            }
        };

        $talk('nour@demo.test', 'yousef@demo.test', [
            [$now->subDays(2)->setTime(18, 40), 'nour@demo.test', 'برافو يا يوسف! حفظت سورة الفيل كلها النهارده 🌟 راجعها مع بابا قبل حصة الثلاثاء.'],
            [$now->subDays(2)->setTime(20, 5), 'yousef.guardian@demo.test', 'متشكرين يا أستاذة نور، هنراجعها كل يوم بالليل إن شاء الله.'],
            [$now->subDays(2)->setTime(20, 9), 'yousef@demo.test', 'حاضر يا أستاذة 😊'],
            [$now->subMinutes(40), 'nour@demo.test', 'صباح الخير يا يوسف! لو عندك أي سؤال في الواجب ابعتلي هنا.'],
        ]);

        $talk('ahmed@demo.test', 'layan@demo.test', [
            [$now->subDay()->setTime(16, 12), 'layan.guardian@demo.test', 'أستاذ أحمد، ممكن نبدّل حصة الخميس لأي يوم تاني؟ لو أسهل كلمني واتساب 0101 234 5678'],
            [$now->subDay()->setTime(17, 3), 'ahmed@demo.test', 'أكيد يا أفندم. التغيير بيتعمل من صفحة الحصص عشان الأكاديمية تظبط الرصيد، وأنا موجود هنا لأي سؤال.'],
        ]);

        $talk('sara@demo.test', 'maryam@demo.test', [
            [$now->subDays(3)->setTime(19, 0), 'sara@demo.test', 'مريم، المقال الجاي عن رحلة عملتيها. عايزاكي تستخدمي 5 كلمات جديدة من درس النهارده.'],
            [$now->subDays(3)->setTime(21, 14), 'maryam@demo.test', 'تمام يا ميس، هبعته يوم الأربعاء.'],
        ]);
    }
}
