<?php

namespace Modules\Curriculum\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Texts\QuranText;

/**
 * The demo's Juz' Amma lessons read their surahs (by number and verses: the
 * words come from the platform's copy), so the hifz map and the shared
 * mushaf have a course to follow. Only once the server has the Quran's text,
 * and only a lesson with no text yet: it runs again on every deploy of a
 * server with the demo, after the text is fetched.
 */
final class DemoQuranTextsSeeder extends Seeder
{
    /** Each lesson by its title: its first surah's number and verses. */
    private const TEXTS = [
        'الفاتحة' => [1, 7],
        'الناس والفلق' => [114, 6],
        'الإخلاص والمسد' => [112, 4],
        'النصر والكافرون' => [110, 3],
        'الكوثر والماعون' => [108, 3],
        'قريش والفيل' => [106, 4],
        'الهمزة والعصر' => [104, 9],
        'التكاثر والقارعة' => [102, 8],
    ];

    public function run(SaveNode $nodes, QuranText $quran): void
    {
        if (! $quran->available()) {
            return;
        }
        $lessons = Node::query()->where('kind', 'lesson')->whereNull('text_source')->whereIn('title_ar', array_keys(self::TEXTS))->get();
        foreach ($lessons as $lesson) {
            $version = CurriculumVersion::query()->find($lesson->version_id);
            if ($version === null || $version->status === 'retired') {
                continue;
            }
            [$surah, $verses] = self::TEXTS[$lesson->title_ar];
            try {
                $nodes->handle($version, $lesson, ['title_ar' => $lesson->title_ar, 'title_en' => $lesson->title_en, 'objectives' => $lesson->objectives, 'text_source' => ['kind' => 'quran', 'surah' => $surah, 'from' => 1, 'to' => $verses]]);
            } catch (ValidationException) {
                // A copy without that surah's verses (a test's): the lesson stays without a text.
            }
        }
    }
}
