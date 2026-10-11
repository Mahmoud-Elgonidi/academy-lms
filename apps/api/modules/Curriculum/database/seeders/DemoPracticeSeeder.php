<?php

namespace Modules\Curriculum\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Models\Node;

/**
 * Practice items in the demo academy, through the real action, on the
 * lessons the demo students are at or have just done: the verses of the
 * short surahs in order, the word that ends a verse, a verb and its present,
 * a tajweed rule and its example, a letter and its name, and English words
 * and phrases (they read left to right in an Arabic app). The games play
 * these (docs/architecture/02, section 18). Runs after the programs.
 */
final class DemoPracticeSeeder extends Seeder
{
    /** [subject, lesson (English title), [item, …]] */
    private const ITEMS = [
        ['Quran memorisation', 'Al-Fatiha', [
            ['kind' => 'order', 'prompt' => 'رتّب آيات سورة الفاتحة', 'parts' => ['بسم الله الرحمن الرحيم', 'الحمد لله رب العالمين', 'الرحمن الرحيم', 'مالك يوم الدين', 'إياك نعبد وإياك نستعين', 'اهدنا الصراط المستقيم', 'صراط الذين أنعمت عليهم…']],
            ['kind' => 'choice', 'prompt' => 'أكمل: الحمد لله رب …', 'options' => ['العالمين', 'الدين', 'المستقيم'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'أكمل: مالك يوم …', 'options' => ['الدين', 'العالمين', 'الرحيم'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'أكمل: إياك نعبد وإياك …', 'options' => ['نستعين', 'نعبد', 'الدين'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'أكمل: اهدنا الصراط …', 'options' => ['المستقيم', 'العالمين', 'الضالين'], 'answer' => 0],
            ['kind' => 'pair', 'left' => 'الحمد لله', 'right' => 'رب العالمين'],
            ['kind' => 'pair', 'left' => 'مالك', 'right' => 'يوم الدين'],
            ['kind' => 'pair', 'left' => 'إياك نعبد', 'right' => 'وإياك نستعين'],
            ['kind' => 'pair', 'left' => 'اهدنا', 'right' => 'الصراط المستقيم'],
        ]],
        ['Quran memorisation', 'An-Nas and Al-Falaq', [
            ['kind' => 'order', 'prompt' => 'رتّب آيات سورة الناس', 'parts' => ['قل أعوذ برب الناس', 'ملك الناس', 'إله الناس', 'من شر الوسواس الخناس', 'الذي يوسوس في صدور الناس', 'من الجنة والناس']],
            ['kind' => 'order', 'prompt' => 'رتّب آيات سورة الفلق', 'parts' => ['قل أعوذ برب الفلق', 'من شر ما خلق', 'ومن شر غاسق إذا وقب', 'ومن شر النفاثات في العقد', 'ومن شر حاسد إذا حسد']],
            ['kind' => 'choice', 'prompt' => 'سورة الناس: من شر الوسواس …', 'options' => ['الخناس', 'الناس', 'الفلق'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'سورة الفلق: قل أعوذ برب …', 'options' => ['الفلق', 'الناس', 'العالمين'], 'answer' => 0],
            ['kind' => 'pair', 'left' => 'ومن شر غاسق', 'right' => 'إذا وقب'],
            ['kind' => 'pair', 'left' => 'ومن شر حاسد', 'right' => 'إذا حسد'],
            ['kind' => 'pair', 'left' => 'من الجنة', 'right' => 'والناس'],
        ]],
        ['Quran memorisation', 'Al-Kawthar and Al-Ma\'un', [
            ['kind' => 'order', 'prompt' => 'رتّب آيات سورة الكوثر', 'parts' => ['إنا أعطيناك الكوثر', 'فصل لربك وانحر', 'إن شانئك هو الأبتر']],
            ['kind' => 'choice', 'prompt' => 'أكمل: إنا أعطيناك …', 'options' => ['الكوثر', 'الفلق', 'الماعون'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'أكمل: فصل لربك …', 'options' => ['وانحر', 'واسجد', 'واقترب'], 'answer' => 0],
            ['kind' => 'pair', 'left' => 'إن شانئك', 'right' => 'هو الأبتر'],
            ['kind' => 'pair', 'left' => 'ويمنعون', 'right' => 'الماعون'],
        ]],
        ['Quran memorisation', 'Quraysh and Al-Fil', [
            ['kind' => 'order', 'prompt' => 'رتّب آيات سورة الفيل', 'parts' => ['ألم تر كيف فعل ربك بأصحاب الفيل', 'ألم يجعل كيدهم في تضليل', 'وأرسل عليهم طيرا أبابيل', 'ترميهم بحجارة من سجيل', 'فجعلهم كعصف مأكول']],
            ['kind' => 'order', 'prompt' => 'رتّب آيات سورة قريش', 'parts' => ['لإيلاف قريش', 'إيلافهم رحلة الشتاء والصيف', 'فليعبدوا رب هذا البيت', 'الذي أطعمهم من جوع وآمنهم من خوف']],
            ['kind' => 'choice', 'prompt' => 'أكمل: وأرسل عليهم طيرا …', 'options' => ['أبابيل', 'سجيل', 'مأكول'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'أكمل: ترميهم بحجارة من …', 'options' => ['سجيل', 'تضليل', 'أبابيل'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'أكمل: فجعلهم كعصف …', 'options' => ['مأكول', 'أبابيل', 'سجيل'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'أكمل: إيلافهم رحلة الشتاء و…', 'options' => ['الصيف', 'الربيع', 'الخريف'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'أكمل: فليعبدوا رب هذا …', 'options' => ['البيت', 'الفيل', 'البلد'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'أكمل: الذي أطعمهم من جوع وآمنهم من …', 'options' => ['خوف', 'جوع', 'برد'], 'answer' => 0],
            ['kind' => 'pair', 'left' => 'ألم يجعل كيدهم', 'right' => 'في تضليل'],
            ['kind' => 'pair', 'left' => 'وأرسل عليهم', 'right' => 'طيرا أبابيل'],
            ['kind' => 'pair', 'left' => 'ترميهم بحجارة', 'right' => 'من سجيل'],
            ['kind' => 'pair', 'left' => 'فجعلهم', 'right' => 'كعصف مأكول'],
            ['kind' => 'pair', 'left' => 'لإيلاف', 'right' => 'قريش'],
            ['kind' => 'pair', 'left' => 'فليعبدوا', 'right' => 'رب هذا البيت'],
        ]],
        ['Arabic grammar', 'The past tense', [
            ['kind' => 'choice', 'prompt' => 'أي كلمة فعل ماضٍ؟', 'options' => ['ذهبَ', 'يذهبُ', 'اذهبْ'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'هي … الباب (فتح)', 'options' => ['فتحتْ', 'فتحوا', 'فتحنا'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'نحن … الدرس (كتب)', 'options' => ['كتبنا', 'كتبتُ', 'كتبوا'], 'answer' => 0],
            ['kind' => 'pair', 'left' => 'أنا', 'right' => 'كتبتُ'],
            ['kind' => 'pair', 'left' => 'نحن', 'right' => 'كتبنا'],
            ['kind' => 'pair', 'left' => 'هو', 'right' => 'كتبَ'],
            ['kind' => 'pair', 'left' => 'هي', 'right' => 'كتبتْ'],
            ['kind' => 'pair', 'left' => 'هم', 'right' => 'كتبوا'],
        ]],
        ['Arabic grammar', 'The present tense', [
            ['kind' => 'choice', 'prompt' => 'أي كلمة فعل مضارع؟', 'options' => ['يكتبُ', 'كتبَ', 'اكتبْ'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'المضارع من «لعب» هو:', 'options' => ['يلعب', 'لاعب', 'ملعب'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'نحن … الدرس كل يوم (درس)', 'options' => ['ندرس', 'درسنا', 'ادرس'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'حرف المضارعة في «تكتب» هو:', 'options' => ['التاء', 'الكاف', 'الباء'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'هي … القصة الآن (قرأ)', 'options' => ['تقرأ', 'يقرأ', 'أقرأ'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'علامة رفع الفعل المضارع:', 'options' => ['الضمة', 'الفتحة', 'السكون'], 'answer' => 0],
            ['kind' => 'order', 'prompt' => 'رتّب أحرف المضارعة كما في كلمة «أنيت»', 'parts' => ['أ', 'ن', 'ي', 'ت']],
            ['kind' => 'pair', 'left' => 'كتب', 'right' => 'يكتب'],
            ['kind' => 'pair', 'left' => 'ذهب', 'right' => 'يذهب'],
            ['kind' => 'pair', 'left' => 'شرب', 'right' => 'يشرب'],
            ['kind' => 'pair', 'left' => 'جلس', 'right' => 'يجلس'],
            ['kind' => 'pair', 'left' => 'فتح', 'right' => 'يفتح'],
            ['kind' => 'pair', 'left' => 'سمع', 'right' => 'يسمع'],
        ]],
        ['Tajweed', 'Single letters', [
            ['kind' => 'choice', 'prompt' => 'ما اسم هذا الحرف؟ ب', 'options' => ['باء', 'تاء', 'ثاء'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'ما اسم هذا الحرف؟ ج', 'options' => ['جيم', 'حاء', 'خاء'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'ما اسم هذا الحرف؟ س', 'options' => ['سين', 'شين', 'صاد'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'ما اسم هذا الحرف؟ ع', 'options' => ['عين', 'غين', 'فاء'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'ما اسم هذا الحرف؟ ق', 'options' => ['قاف', 'فاء', 'كاف'], 'answer' => 0],
            ['kind' => 'order', 'prompt' => 'رتّب الحروف كما في الأبجدية', 'parts' => ['أ', 'ب', 'ت', 'ث', 'ج']],
            ['kind' => 'order', 'prompt' => 'رتّب الحروف كما في الأبجدية', 'parts' => ['ح', 'خ', 'د', 'ذ', 'ر']],
            ['kind' => 'pair', 'left' => 'ب', 'right' => 'باء'],
            ['kind' => 'pair', 'left' => 'ت', 'right' => 'تاء'],
            ['kind' => 'pair', 'left' => 'ث', 'right' => 'ثاء'],
            ['kind' => 'pair', 'left' => 'ج', 'right' => 'جيم'],
            ['kind' => 'pair', 'left' => 'ح', 'right' => 'حاء'],
            ['kind' => 'pair', 'left' => 'خ', 'right' => 'خاء'],
        ]],
        ['Tajweed', 'Izhar', [
            ['kind' => 'choice', 'prompt' => 'أي حرف من حروف الإظهار؟', 'options' => ['ح', 'ب', 'م'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'حكم النون في «مَنْ آمن»', 'options' => ['إظهار', 'إدغام', 'إخفاء'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'حكم النون في «مِنْ عِلْم»', 'options' => ['إظهار', 'إقلاب', 'إخفاء'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'حكم التنوين في «عليمٌ حكيم»', 'options' => ['إظهار', 'إدغام', 'إقلاب'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'كم عدد حروف الإظهار الحلقي؟', 'options' => ['ستة', 'أربعة', 'خمسة'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'سُمّي الإظهار حلقيًا لأن حروفه تخرج من:', 'options' => ['الحلق', 'الشفتين', 'طرف اللسان'], 'answer' => 0],
            ['kind' => 'order', 'prompt' => 'رتّب حروف الإظهار من أقصى الحلق إلى أدناه', 'parts' => ['الهمزة والهاء', 'العين والحاء', 'الغين والخاء']],
            ['kind' => 'pair', 'left' => 'أقصى الحلق', 'right' => 'الهمزة والهاء'],
            ['kind' => 'pair', 'left' => 'وسط الحلق', 'right' => 'العين والحاء'],
            ['kind' => 'pair', 'left' => 'أدنى الحلق', 'right' => 'الغين والخاء'],
            ['kind' => 'pair', 'left' => 'مِنْ عِلْم', 'right' => 'إظهار'],
            ['kind' => 'pair', 'left' => 'مَن يقول', 'right' => 'إدغام'],
            ['kind' => 'pair', 'left' => 'مِنْ بعد', 'right' => 'إقلاب'],
            ['kind' => 'pair', 'left' => 'مِنْ قبل', 'right' => 'إخفاء'],
        ]],
        ['Tajweed', 'Idgham', [
            ['kind' => 'choice', 'prompt' => 'حروف الإدغام مجموعة في كلمة:', 'options' => ['يرملون', 'أنيت', 'سجيل'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'حروف الإدغام بغنة:', 'options' => ['ي ن م و', 'ل ر', 'ء هـ ع ح'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'حكم النون في «مِن ربهم»', 'options' => ['إدغام بغير غنة', 'إدغام بغنة', 'إظهار'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'حكم النون في «مَن يعمل»', 'options' => ['إدغام بغنة', 'إدغام بغير غنة', 'إخفاء'], 'answer' => 0],
        ]],
        ['English', 'Greetings and introductions', [
            ['kind' => 'choice', 'prompt' => '"How are you?" The answer:', 'options' => ["I'm fine, thanks.", "I'm ten.", "It's blue."], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => '"What\'s your name?" The answer:', 'options' => ['My name is Sara.', "I'm from Jeddah.", 'Goodbye!'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'You meet your teacher in the morning. You say:', 'options' => ['Good morning!', 'Good night!', 'See you!'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => '"Nice to meet you." The answer:', 'options' => ['Nice to meet you too.', 'Yes, please.', "I'm sorry."], 'answer' => 0],
            ['kind' => 'order', 'prompt' => 'Put the words in order', 'parts' => ['Where', 'are', 'you', 'from?']],
            ['kind' => 'order', 'prompt' => 'Put the words in order', 'parts' => ['Nice', 'to', 'meet', 'you']],
            ['kind' => 'pair', 'left' => 'Thank you', 'right' => 'شكرًا'],
            ['kind' => 'pair', 'left' => 'Good morning', 'right' => 'صباح الخير'],
            ['kind' => 'pair', 'left' => 'Goodbye', 'right' => 'مع السلامة'],
            ['kind' => 'pair', 'left' => 'Please', 'right' => 'من فضلك'],
        ]],
        ['English', 'Numbers and time', [
            ['kind' => 'choice', 'prompt' => 'Which number is "twelve"?', 'options' => ['12', '20', '2'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => '"It\'s half past three." The time is:', 'options' => ['3:30', '3:15', '2:30'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => '"It\'s quarter to five." The time is:', 'options' => ['4:45', '5:15', '5:45'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'How many days are there in a week?', 'options' => ['seven', 'five', 'ten'], 'answer' => 0],
            ['kind' => 'order', 'prompt' => 'Count up', 'parts' => ['one', 'two', 'three', 'four', 'five']],
            ['kind' => 'order', 'prompt' => 'Put the days in order', 'parts' => ['Saturday', 'Sunday', 'Monday', 'Tuesday']],
            ['kind' => 'pair', 'left' => 'eleven', 'right' => '11'],
            ['kind' => 'pair', 'left' => 'fifteen', 'right' => '15'],
            ['kind' => 'pair', 'left' => 'thirty', 'right' => '30'],
            ['kind' => 'pair', 'left' => 'a hundred', 'right' => '100'],
        ]],
        ['English', 'Daily routine', [
            ['kind' => 'choice', 'prompt' => "I ___ up at seven o'clock.", 'options' => ['get', 'go', 'have'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'She ___ breakfast at eight.', 'options' => ['has', 'have', 'having'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'We go to school ___ bus.', 'options' => ['by', 'on', 'at'], 'answer' => 0],
            ['kind' => 'order', 'prompt' => 'Put the day in order', 'parts' => ['wake up', 'have breakfast', 'go to school', 'do homework', 'go to bed']],
            ['kind' => 'pair', 'left' => 'brush', 'right' => 'my teeth'],
            ['kind' => 'pair', 'left' => 'take', 'right' => 'a shower'],
            ['kind' => 'pair', 'left' => 'do', 'right' => 'my homework'],
            ['kind' => 'pair', 'left' => 'go', 'right' => 'to bed'],
        ]],
    ];

    public function run(SaveNodeItems $save): void
    {
        $owner = (string) DB::table('users')->where('email', 'owner@demo.test')->value('id');

        foreach (self::ITEMS as [$subject, $lessonEn, $items]) {
            $lesson = Node::query()->where('kind', 'lesson')->where('title_en', $lessonEn)
                ->whereIn('version_id', DB::table('curriculum_versions as v')->join('programs as p', 'p.id', '=', 'v.program_id')
                    ->join('subjects as s', 's.id', '=', 'p.subject_id')->where('s.name_en', $subject)->where('v.status', 'published')->select('v.id'))
                ->first();
            if ($lesson !== null) {
                $save->handle($lesson, $items, $owner ?: null);
            }
        }
    }
}
