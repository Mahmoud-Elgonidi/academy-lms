<?php

namespace Modules\Quran\Domain\Programs;

use InvalidArgumentException;

/**
 * The ready tracks a program starts from (docs/architecture/02, section 26),
 * as ProgramRules takes them; their names and hints are in
 * quran::programs.presets.{key}:
 *
 * - juz_amma_talqin: Juz Amma by dictation for children, backward from
 *   an-Nas to an-Naba, about four verses a lesson (three to five as surahs
 *   end), an exam at each surah.
 * - full_backward: the whole Quran from an-Nas back to al-Fatiha, half a
 *   page a lesson, an exam at each part.
 * - full_forward: the whole Quran from al-Fatiha on, half a page a lesson.
 * - tilawa: reading and tajweed through the mushaf, a page a lesson,
 *   nothing to memorize.
 * - noorani: the Noorani Qaida's lessons (quran::tracks.noorani.lessons),
 *   no text of the mushaf.
 * - review: review and consolidation only: five memorized pages a lesson.
 * - ijaza: a full recitation for an ijaza, two quarters of a hizb a lesson.
 */
final class Presets
{
    /** The Noorani Qaida's lessons (as many as quran::tracks.noorani.lessons). */
    public const NOORANI_LESSONS = 17;

    private const ALL = [
        'juz_amma_talqin' => ['track' => 'talqin', 'scope_from_surah' => 78, 'scope_from_verse' => 1, 'scope_to_surah' => 114, 'scope_to_verse' => 6,
            'direction' => 'backward', 'unit' => 'verses', 'new_amount' => 4, 'near_portions' => 2, 'far_pages' => 1, 'pass_grade' => 'good',
            'exam_stops' => 'surah', 'counts_for_certificate' => true],
        'full_backward' => ['track' => 'hifz', 'scope_from_surah' => 1, 'scope_from_verse' => 1, 'scope_to_surah' => 114, 'scope_to_verse' => 6,
            'direction' => 'backward', 'unit' => 'page', 'new_amount' => 0.5, 'near_portions' => 3, 'far_pages' => 4, 'pass_grade' => 'very_good',
            'exam_stops' => 'juz', 'counts_for_certificate' => true],
        'full_forward' => ['track' => 'hifz', 'scope_from_surah' => 1, 'scope_from_verse' => 1, 'scope_to_surah' => 114, 'scope_to_verse' => 6,
            'direction' => 'forward', 'unit' => 'page', 'new_amount' => 0.5, 'near_portions' => 3, 'far_pages' => 4, 'pass_grade' => 'very_good',
            'exam_stops' => 'juz', 'counts_for_certificate' => true],
        'tilawa' => ['track' => 'tilawa', 'scope_from_surah' => 1, 'scope_from_verse' => 1, 'scope_to_surah' => 114, 'scope_to_verse' => 6,
            'direction' => 'forward', 'unit' => 'page', 'new_amount' => 1, 'near_portions' => 0, 'far_pages' => 0, 'pass_grade' => 'acceptable',
            'exam_stops' => 'none', 'counts_for_certificate' => false],
        'noorani' => ['track' => 'noorani', 'scope_from_surah' => null, 'scope_from_verse' => 1, 'scope_to_surah' => null, 'scope_to_verse' => self::NOORANI_LESSONS,
            'direction' => 'forward', 'unit' => 'lesson', 'new_amount' => 1, 'near_portions' => 0, 'far_pages' => 0, 'pass_grade' => 'good',
            'exam_stops' => 'none', 'counts_for_certificate' => false],
        'review' => ['track' => 'review', 'scope_from_surah' => 1, 'scope_from_verse' => 1, 'scope_to_surah' => 114, 'scope_to_verse' => 6,
            'direction' => 'forward', 'unit' => 'page', 'new_amount' => 0, 'near_portions' => 0, 'far_pages' => 5, 'pass_grade' => 'good',
            'exam_stops' => 'none', 'counts_for_certificate' => false],
        'ijaza' => ['track' => 'ijaza', 'scope_from_surah' => 1, 'scope_from_verse' => 1, 'scope_to_surah' => 114, 'scope_to_verse' => 6,
            'direction' => 'forward', 'unit' => 'quarter_hizb', 'new_amount' => 2, 'near_portions' => 0, 'far_pages' => 0, 'pass_grade' => 'very_good',
            'exam_stops' => 'juz', 'counts_for_certificate' => true],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /** @return array<string, mixed> a program's fields but its name */
    public static function get(string $key): array
    {
        return self::ALL[$key] ?? throw new InvalidArgumentException("No ready track {$key}");
    }
}
