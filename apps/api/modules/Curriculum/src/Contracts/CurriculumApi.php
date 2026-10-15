<?php

namespace Modules\Curriculum\Contracts;

use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Curriculum\Contracts\DTOs\LessonResource;
use Modules\Curriculum\Contracts\DTOs\Passage;
use Modules\Curriculum\Contracts\DTOs\PracticeItem;
use Modules\Curriculum\Contracts\DTOs\ProgramOption;
use Modules\Curriculum\Contracts\DTOs\QuranPage;
use Modules\Curriculum\Contracts\DTOs\ResourceFile;
use Modules\Curriculum\Contracts\DTOs\TextSource;

/** What other modules may ask of Curriculum. */
interface CurriculumApi
{
    /** @return list<ProgramOption> active programs of a subject that have a published version */
    public function programsFor(string $subjectId): array;

    public function program(string $programId): ?ProgramOption;

    /** The version's tree; empty when it does not exist. */
    public function tree(string $versionId): CurriculumTree;

    /**
     * The files and links on these lessons, in their order; the teachers-only ones too only when asked.
     *
     * @param  list<string>  $nodeIds
     * @return array<string, list<LessonResource>> by lesson
     */
    public function resources(array $nodeIds, bool $withTeachersOnly): array;

    /** Where a lesson's file is kept, for whoever serves it; null for a link or one that is gone. */
    public function resourceFile(string $resourceId): ?ResourceFile;

    /**
     * The practice items on these lessons, in their order (what games play).
     *
     * @param  list<string>  $nodeIds
     * @return array<string, list<PracticeItem>> by lesson
     */
    public function practiceItems(array $nodeIds): array;

    /**
     * These lessons' texts, for those that have one.
     *
     * @param  list<string>  $nodeIds
     * @return array<string, TextSource> by lesson
     */
    public function textSources(array $nodeIds): array;

    /** A lesson's text as it is read (the Quran's words from the platform's copy); null when it is not there. */
    public function passage(TextSource $source): ?Passage;

    /**
     * The Quran's surahs the platform has, in order; empty until its copy is in place.
     *
     * @return list<array{number: int, name_ar: string, name_en: string, verses: int}>
     */
    public function surahs(): array;

    /**
     * The mushaf's pages (604) and the part each begins in, in order; empty until the platform's copy is in place.
     *
     * @return list<array{page: int, juz: int}>
     */
    public function quranPages(): array;

    /**
     * The pages a passage of the Quran is on, in order; empty for other texts or verses not there.
     *
     * @return list<int>
     */
    public function quranPagesOf(TextSource $source): array;

    /** A page of the mushaf with its text; null when it is not there. */
    public function quranPage(int $page): ?QuranPage;
}
