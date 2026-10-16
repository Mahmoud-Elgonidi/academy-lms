<?php

namespace Modules\Learning\Http\V1\Resources;

use Modules\Learning\Models\RecitationMistake;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** A lesson's shared mushaf page and a learner's mistakes notebook, as the apps, the portal and the class page read them. */
final class MushafPresenter
{
    public function __construct(private readonly LocalTimePresenter $time) {}

    /**
     * @param  array<string, mixed>  $page  Queries\Mushaf\LessonMushaf::page
     * @return array<string, mixed>
     */
    public function page(array $page): array
    {
        return [
            'session_id' => $page['session_id'],
            /** teacher (turns the pages and marks words), student or guardian (follow and read) */
            'role' => $page['role'],
            /** Whether I may mark words now (the teacher, from the room's opening until the report is handed in). */
            'can_mark' => $page['can_mark'],
            /** The page shown (1 to 604). */
            'page' => $page['page'],
            /** The page the teacher has open: the student's screen follows it; null until the teacher turns one. */
            'teacher_page' => $page['teacher_page'],
            /** Where the lesson starts (its plan's passage of the Quran, else the oldest page due for review). */
            'start_page' => $page['start_page'],
            'pages_total' => $page['pages_total'],
            'juz' => $page['juz'],
            'title' => $page['title'],
            'surahs' => $page['surahs'],
            /** The basmala's words from the platform's copy, shown at the head of a surah that begins on this page (not al-Fatiha, not at-Tawba); null when none begins here. */
            'basmala' => $page['basmala'],
            /** The page's verses, each word as written; a word is its surah, its verse and its place in the verse from 1. */
            'verses' => collect($page['verses'])->map(fn (array $v) => [
                'surah' => (int) $v['surah'],
                'verse' => (int) $v['verse'],
                /** Each word as written, from the first. */
                'words' => collect($v['words'])->map(fn ($w) => (string) $w)->values()->all(),
            ])->values()->all(),
            /** Words marked in this lesson: kind wrong, hesitation or tajweed. */
            'marks' => $page['marks'],
            /** Words on this page marked in the student's earlier lessons (shown faintly): the latest kind and how many times. */
            'earlier' => $page['earlier'],
            'kinds' => $this->kinds(),
            /** The attribution the text is shown with. */
            'attribution' => $page['attribution'],
        ];
    }

    /**
     * @param  array<string, mixed>  $book  Queries\Mushaf\MistakesBook::for
     * @return array<string, mixed>
     */
    public function book(array $book): array
    {
        return [
            /** Words marked in the last six months. */
            'words' => $book['words'],
            /** By surah and verse: each verse's words as written, and its marked words (by place, from 1). */
            'surahs' => collect($book['surahs'])->map(fn (array $s) => [
                'surah' => (int) $s['surah'],
                'name_ar' => (string) $s['name_ar'],
                'name_en' => (string) $s['name_en'],
                /** Words marked in this surah. */
                'words' => (int) $s['words'],
                'verses' => collect($s['verses'])->map(fn (array $v) => [
                    'verse' => (int) $v['verse'],
                    /** The verse's words as written, from the first. */
                    'words' => collect($v['words'])->map(fn ($w) => (string) $w)->values()->all(),
                    'marks' => collect($v['marks'])->map(fn (array $m) => [
                        /** Its place in the verse, from 1. */
                        'word' => (int) $m['word'],
                        'times' => (int) $m['times'],
                        /** wrong, hesitation, tajweed */
                        'kinds' => collect($m['kinds'])->map(fn ($k) => (string) $k)->values()->all(),
                        'last_at' => $this->time->present($m['last_at']),
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
            'kinds' => $this->kinds(),
        ];
    }

    /** @return array<string, string> each kind of mistake (wrong, hesitation, tajweed) in the reader's language */
    private function kinds(): array
    {
        $kinds = [];
        foreach (RecitationMistake::KINDS as $kind) {
            $kinds[$kind] = (string) __('learning::mushaf.kinds.'.$kind);
        }

        return $kinds;
    }
}
