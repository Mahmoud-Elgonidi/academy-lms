<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Actions\SaveSubject;
use Modules\Catalog\Models\Subject;

/** The demo subjects' levels, in order, and their categories (tracks and curricula). Inside the demo academy. */
final class DemoCatalogSeeder extends Seeder
{
    public function run(SaveSubject $save): void
    {
        $items = fn (array $pairs) => array_map(fn (array $p) => ['name_ar' => $p[0], 'name_en' => $p[1]], $pairs);
        $subjects = [
            'Quran memorisation' => [
                [['القاعدة النورانية', 'Noorani Qaida'], ['جزء عمّ', 'Juz Amma'], ['جزء تبارك', 'Juz Tabarak'], ['5 أجزاء', '5 juz'], ['10 أجزاء', '10 juz'], ['القرآن كامل', 'The whole Quran']],
                [['حفظ جديد', 'New memorisation'], ['مراجعة', 'Revision'], ['إجازة', 'Ijazah']],
            ],
            'Tajweed' => [
                [['مبتدئ', 'Beginner'], ['متوسط', 'Intermediate'], ['متقدم', 'Advanced']],
                [['رواية حفص', 'Hafs'], ['تحفة الأطفال', 'Tuhfat al-Atfal'], ['الجزرية', 'Al-Jazariyya']],
            ],
            'Arabic' => [
                [['تأسيس', 'Foundations'], ['المستوى الأول', 'Level 1'], ['المستوى الثاني', 'Level 2'], ['المستوى الثالث', 'Level 3']],
                [['لغير الناطقين بها', 'For non-native speakers'], ['منهج مصري', 'Egyptian curriculum'], ['منهج سعودي', 'Saudi curriculum']],
            ],
            'English' => [
                [['A1', 'A1'], ['A2', 'A2'], ['B1', 'B1'], ['B2', 'B2'], ['C1', 'C1']],
                [['محادثة', 'Conversation'], ['IELTS', 'IELTS'], ['منهج بريطاني', 'British curriculum'], ['منهج أمريكي', 'American curriculum']],
            ],
            'Arabic grammar' => [
                [['الآجرومية', 'Al-Ajurrumiyya'], ['قطر الندى', 'Qatr al-Nada'], ['ألفية ابن مالك', 'Alfiyyat Ibn Malik']],
                [],
            ],
        ];
        foreach ($subjects as $en => [$levels, $categories]) {
            $subject = Subject::query()->where('name_en', $en)->first();
            if ($subject !== null) {
                $save->handle($subject->id, $subject->name_ar, $subject->name_en, $items($levels), $items($categories));
            }
        }
    }
}
