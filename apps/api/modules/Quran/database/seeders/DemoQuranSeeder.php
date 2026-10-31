<?php

namespace Modules\Quran\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Actions\SaveSubject;
use Modules\Quran\Actions\Passages\SetLessonPassage;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Models\LessonPassage;
use Modules\Quran\Support\QuranArea;
use Modules\Quran\Texts\QuranText;

/**
 * The demo's Quran: its memorisation subject is a Quran subject (track
 * hifz), and its Juz' Amma lessons read their surahs (by number and verses:
 * the words come from the platform's copy), so the hifz map and the shared
 * mushaf have a course to follow. The passages only once the server has the
 * Quran's text, and only for a lesson with none yet: it runs again on every
 * deploy of a server with the demo, after the text is fetched.
 */
final class DemoQuranSeeder extends Seeder
{
    /** The demo's Quran subjects (by English name) and their tracks. */
    public const SUBJECTS = ['Quran memorisation' => 'hifz'];

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

    public function run(SaveSubject $subjects, SetLessonPassage $passages, QuranText $quran, QuranArea $area): void
    {
        // A demo whose plan leaves the Quran out stays without it (DemoQuranAreaSeeder gives the demo's plan the area).
        if (! $area->on()) {
            return;
        }
        foreach (self::SUBJECTS as $name => $track) {
            $subject = DB::table('subjects')->where('name_en', $name)->first(['id', 'name_ar', 'name_en', 'kind']);
            if ($subject !== null && $subject->kind !== 'quran') {
                $subjects->handle($subject->id, $subject->name_ar, $subject->name_en,
                    DB::table('subject_levels')->where('subject_id', $subject->id)->orderBy('ordinal')->get(['id', 'name_ar', 'name_en'])->map(fn ($l) => (array) $l)->all(),
                    DB::table('subject_categories')->where('subject_id', $subject->id)->orderBy('ordinal')->get(['id', 'name_ar', 'name_en'])->map(fn ($c) => (array) $c)->all(),
                    'quran', $track);
            }
        }

        if (! $quran->available()) {
            return;
        }
        $lessons = DB::table('curriculum_nodes as n')
            ->join('curriculum_versions as v', fn ($j) => $j->on('v.academy_id', '=', 'n.academy_id')->on('v.id', '=', 'n.version_id'))
            ->where('n.kind', 'lesson')->where('v.status', '!=', 'retired')->whereIn('n.title_ar', array_keys(self::TEXTS))
            ->whereNotIn('n.id', LessonPassage::query()->select('node_id'))
            ->get(['n.id', 'n.title_ar']);
        foreach ($lessons as $lesson) {
            [$surah, $verses] = self::TEXTS[$lesson->title_ar];
            try {
                $passages->handle((string) $lesson->id, VerseRange::of($surah, 1, $verses));
            } catch (ValidationException) {
                // A copy without that surah's verses (a test's): the lesson stays without a passage.
            }
        }
    }
}
