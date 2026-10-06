<?php

namespace Modules\Curriculum\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Actions\SaveProgram;

/**
 * Demo programs through the real actions: Arabic grammar, Juz' Amma
 * memorisation and Nour Al-Bayan (published), and an English course still
 * in draft. Arabic has no program, to show that state too.
 */
final class DemoCurriculumSeeder extends Seeder
{
    /** [ar, en, [[level ar, level en, [[unit ar, unit en, [[lesson ar, lesson en, objectives?, weight?, sessions?, rule?], …]], …]], …]] */
    private const PROGRAMS = [
        'Arabic grammar' => ['النحو الميسّر', 'Easy Arabic Grammar', true, [
            ['المستوى الأول: الكلمة', 'Level 1: The word', [
                ['الاسم', 'Nouns', [
                    ['الاسم وعلاماته', 'The noun and its signs', "يميّز الاسم من الفعل والحرف\nيعرف علامات الاسم: التنوين وأل والجر"],
                    ['المفرد والمثنى والجمع', 'Singular, dual and plural', 'يثنّي ويجمع الأسماء الشائعة'],
                    ['المذكر والمؤنث', 'Masculine and feminine', null],
                ]],
                ['الفعل', 'Verbs', [
                    ['الفعل الماضي', 'The past tense', 'يصرّف الماضي مع الضمائر'],
                    ['الفعل المضارع', 'The present tense', null, 1, 2],
                    ['فعل الأمر', 'The imperative', null],
                ]],
            ]],
            ['المستوى الثاني: الجملة', 'Level 2: The sentence', [
                ['الجملة الاسمية', 'Nominal sentences', [
                    ['المبتدأ والخبر', 'Subject and predicate', 'يعرب جملة اسمية بسيطة'],
                    ['كان وأخواتها', 'Kana and its sisters', null, 1, 2],
                    ['إنّ وأخواتها', 'Inna and its sisters', null, 1, 2],
                ]],
                ['الجملة الفعلية', 'Verbal sentences', [
                    ['الفعل والفاعل', 'Verb and subject', null],
                    ['المفعول به', 'The object', null],
                    ['تطبيق: إعراب فقرة كاملة', 'Practice: parsing a paragraph', 'يعرب فقرة من خمس جمل من غير مساعدة', 2, 2, 'score_twice'],
                ]],
            ]],
        ]],
        'Quran memorisation' => ['حفظ جزء عمّ', 'Juz\' Amma memorisation', true, [
            ['قصار السور', 'The shortest surahs', [
                ['الفاتحة', 'Al-Fatiha', 'حفظ متقن بأحكام التجويد الأساسية', 1, 2, 'score_twice'],
                ['الناس والفلق', 'An-Nas and Al-Falaq', null, 1, 2, 'score_twice'],
                ['الإخلاص والمسد', 'Al-Ikhlas and Al-Masad', null, 1, 2, 'score_twice'],
                ['النصر والكافرون', 'An-Nasr and Al-Kafirun', null, 1, 2, 'score_twice'],
            ]],
            ['سور أطول', 'Longer surahs', [
                ['الكوثر والماعون', 'Al-Kawthar and Al-Ma\'un', null, 1, 2, 'score_twice'],
                ['قريش والفيل', 'Quraysh and Al-Fil', null, 1, 2, 'score_twice'],
                ['الهمزة والعصر', 'Al-Humaza and Al-Asr', null, 1, 2, 'score_twice'],
                ['التكاثر والقارعة', 'At-Takathur and Al-Qari\'a', null, 2, 3, 'score_twice'],
            ]],
        ]],
        'Tajweed' => ['نور البيان', 'Nour Al-Bayan', true, [
            ['الحروف والحركات', 'Letters and vowels', [
                ['الحروف المفردة', 'Single letters', 'ينطق الحروف من مخارجها الصحيحة', 1, 3],
                ['الحروف المركبة', 'Joined letters', null, 1, 2],
                ['الحركات والتنوين', 'Vowels and tanween', null, 1, 2],
            ]],
            ['أحكام النون الساكنة والتنوين', 'Noon sakinah and tanween', [
                ['الإظهار', 'Izhar', null],
                ['الإدغام', 'Idgham', null, 1, 2],
                ['الإقلاب', 'Iqlab', null],
                ['الإخفاء', 'Ikhfa', null, 1, 2],
            ]],
        ]],
        'English' => ['إنجليزي للحياة اليومية', 'Everyday English', false, [
            ['Starter', 'Starter', [
                ['التعارف', 'Greetings and introductions', null],
                ['الأرقام والوقت', 'Numbers and time', null],
            ]],
        ]],
    ];

    public function run(SaveProgram $programs, SaveNode $nodes, PublishVersion $publish): void
    {
        $owner = DB::table('users')->where('email', 'owner@demo.test')->value('id');

        foreach (self::PROGRAMS as $subjectEn => [$ar, $en, $published, $levels]) {
            $subjectId = DB::table('subjects')->where('name_en', $subjectEn)->value('id');
            if ($subjectId === null) {
                continue;
            }
            $program = $programs->handle(null, ['subject_id' => $subjectId, 'name_ar' => $ar, 'name_en' => $en]);
            $version = $program->workingVersion();

            foreach ($levels as [$lar, $len, $children]) {
                $level = $nodes->handle($version, null, ['kind' => 'level', 'title_ar' => $lar, 'title_en' => $len]);
                foreach ($children as $child) {
                    // A unit with lessons, or a lesson straight in the level.
                    if (is_array($child[2] ?? null)) {
                        $unit = $nodes->handle($version, null, ['kind' => 'unit', 'parent_id' => $level->id, 'title_ar' => $child[0], 'title_en' => $child[1]]);
                        foreach ($child[2] as $lesson) {
                            $this->lesson($nodes, $version, $unit->id, $lesson);
                        }
                    } else {
                        $this->lesson($nodes, $version, $level->id, $child);
                    }
                }
            }

            if ($published) {
                $publish->handle($version, $owner);
            }
        }
    }

    private function lesson(SaveNode $nodes, $version, string $parentId, array $l): void
    {
        $nodes->handle($version, null, [
            'kind' => 'lesson', 'parent_id' => $parentId, 'title_ar' => $l[0], 'title_en' => $l[1], 'objectives' => $l[2] ?? null,
            'weight' => $l[3] ?? 1, 'est_sessions' => $l[4] ?? 1, 'mastery_rule' => $l[5] ?? 'mastered_once',
        ]);
    }
}
