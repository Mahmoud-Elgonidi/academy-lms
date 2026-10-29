<?php

namespace Modules\People\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\People\Models\TeacherPublicCard;

/**
 * Three demo teachers show on the academy's public page, each with a card in
 * their own words (no photos: the page draws their initial). Once per
 * teacher: a card already there, even one switched off, stays as it is.
 */
final class DemoTeacherCardsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'nour@demo.test' => ['أ. نور الهدى', 'محفّظة قرآن بإجازة في رواية حفص، أحب العمل مع الأطفال وأجعل الحفظ لعبة ينتظرونها كل أسبوع.'],
            'sara@demo.test' => ['أ. سارة', 'أدرّس التجويد والقرآن منذ 8 سنوات، للكبار والصغار. الصبر والتشجيع هما سرّ كل حصة عندي.'],
            'khaled@demo.test' => ['أ. خالد', 'معلّم لغة عربية ونحو. أشرح القاعدة بأمثلة من حياة الطالب حتى تصبح سهلة ومفهومة.'],
        ] as $email => [$name, $bio]) {
            $teacher = DB::table('users')->where('email', $email)->value('id');
            if ($teacher === null || ! DB::table('teacher_profiles')->where('user_id', $teacher)->exists()
                || TeacherPublicCard::query()->where('teacher_id', $teacher)->exists()) {
                continue;
            }
            TeacherPublicCard::query()->create(['teacher_id' => $teacher, 'display_name' => $name, 'bio' => $bio, 'shown' => true]);
        }
    }
}
