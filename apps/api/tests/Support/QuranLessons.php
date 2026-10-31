<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Quran module's footing for a test (inside the academy): a subject of
 * the Quran kind, and lessons reading a passage (quran_lesson_passages).
 */
trait QuranLessons
{
    /** The subject becomes a Quran subject (the hifz map follows its courses). */
    protected function makeQuranSubject(string $subjectId, string $track = 'hifz'): void
    {
        DB::table('subjects')->where('id', $subjectId)->update(['kind' => 'quran', 'quran_track' => $track]);
    }

    /** A curriculum lesson reads surah $surah, verses $from to $to. */
    protected function lessonReads(string $nodeId, int $surah, int $from, int $to): void
    {
        DB::table('quran_lesson_passages')->updateOrInsert(['node_id' => $nodeId], [
            'id' => (string) Str::uuid7(), 'surah' => $surah, 'from_verse' => $from, 'to_verse' => $to, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Nothing here is the Quran any more: no subject of its kind, no lesson reading a passage. */
    protected function noQuranCourses(): void
    {
        DB::table('quran_lesson_passages')->delete();
        DB::table('subjects')->update(['kind' => 'general', 'quran_track' => null]);
    }
}
