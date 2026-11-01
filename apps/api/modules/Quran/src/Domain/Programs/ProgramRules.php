<?php

namespace Modules\Quran\Domain\Programs;

use Modules\Quran\Domain\Grading\Grades;

/**
 * What a Quran program may be (docs/architecture/02, section 26), purely:
 * a name; a track; a scope from a surah's verse to another's in the
 * mushaf's order (on the Noorani Qaida, from one of its lessons to another);
 * forward or backward; a unit and the new amount a lesson in it (verses
 * 1–50, lines 1–60, pages in quarters 0.25–5, quarters of a hizb in halves
 * 0.5–8; one Noorani lesson; none on review and consolidation, which only
 * reviews); the near review in portions (0–10) and the far review in pages
 * (0–40, at least one on review); the grade a new portion needs to count
 * as memorized; the exam stops (none, each surah, each part; none on the
 * Noorani Qaida or review); and whether it leads to a certificate.
 * errors() names each field that is wrong with a reason's key
 * (quran::programs.errors.*); normalize() gives the fields as kept.
 */
final class ProgramRules
{
    public const TRACKS = ['hifz', 'tilawa', 'talqin', 'noorani', 'review', 'ijaza'];

    public const DIRECTIONS = ['forward', 'backward'];

    public const UNITS = ['verses', 'lines', 'page', 'quarter_hizb'];

    public const EXAM_STOPS = ['none', 'juz', 'surah'];

    public const NAME_MAX = 120;

    public const NEAR_MAX = 10;

    public const FAR_MAX = 40;

    /** Per unit: the least, the most, and the step an amount moves in. */
    public const AMOUNTS = [
        'verses' => [1, 50, 1],
        'lines' => [1, 60, 1],
        'page' => [0.25, 5, 0.25],
        'quarter_hizb' => [0.5, 8, 0.5],
        'lesson' => [1, 1, 1],
    ];

    /** The fields a plan already on a program holds it to: another shape is another program. */
    public const SHAPE = ['track', 'scope_from_surah', 'scope_from_verse', 'scope_to_surah', 'scope_to_verse', 'direction', 'unit'];

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $surahVerses  surah => its verses (the platform's copy)
     * @param  int  $nooraniLessons  the Noorani Qaida's lessons
     * @return array<string, string> field => reason
     */
    public static function errors(array $data, array $surahVerses, int $nooraniLessons): array
    {
        $errors = [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            $errors['name'] = 'name';
        }
        $track = $data['track'] ?? null;
        if (! in_array($track, self::TRACKS, true)) {
            return $errors + ['track' => 'track'];
        }
        $int = fn (string $key): ?int => isset($data[$key]) && is_numeric($data[$key]) && (int) $data[$key] == $data[$key] ? (int) $data[$key] : null;

        if ($track === 'noorani') {
            [$from, $to] = [$int('scope_from_verse'), $int('scope_to_verse')];
            if (($data['unit'] ?? 'lesson') !== 'lesson') {
                $errors['unit'] = 'unit';
            }
            if ($from === null || $to === null || $from < 1 || $to < $from || $to > $nooraniLessons) {
                $errors['scope_from_verse'] = 'lessons';
            }
            if (($data['direction'] ?? 'forward') !== 'forward') {
                $errors['direction'] = 'direction';
            }
        } else {
            if (! in_array($data['unit'] ?? null, self::UNITS, true)) {
                $errors['unit'] = 'unit';
            }
            if (! in_array($data['direction'] ?? null, self::DIRECTIONS, true)) {
                $errors['direction'] = 'direction';
            }
            $from = [$int('scope_from_surah'), $int('scope_from_verse')];
            $to = [$int('scope_to_surah'), $int('scope_to_verse')];
            $there = fn (array $at): bool => $at[0] !== null && $at[1] !== null && isset($surahVerses[$at[0]]) && $at[1] >= 1 && $at[1] <= $surahVerses[$at[0]];
            if (! $there($from)) {
                $errors['scope_from_surah'] = 'verse';
            }
            if (! $there($to)) {
                $errors['scope_to_surah'] = 'verse';
            }
            if ($there($from) && $there($to) && $from > $to) {
                $errors['scope_to_surah'] = 'order';
            }
        }

        $unit = $track === 'noorani' ? 'lesson' : ($data['unit'] ?? null);
        $amount = $data['new_amount'] ?? null;
        if (! is_numeric($amount)) {
            $errors['new_amount'] = 'amount';
        } elseif ($track === 'review') {
            if ((float) $amount != 0) {
                $errors['new_amount'] = 'review_new';
            }
        } elseif (! isset($errors['unit']) && isset(self::AMOUNTS[$unit])) {
            [$min, $max, $step] = self::AMOUNTS[$unit];
            $steps = (float) $amount / $step;
            if ((float) $amount < $min || (float) $amount > $max || abs($steps - round($steps)) > 1e-9) {
                $errors['new_amount'] = 'amount';
            }
        }

        $near = $int('near_portions') ?? -1;
        if ($near < 0 || $near > self::NEAR_MAX || ($near > 0 && in_array($track, ['noorani', 'review'], true))) {
            $errors['near_portions'] = 'near';
        }
        $far = $int('far_pages') ?? -1;
        if ($far < 0 || $far > self::FAR_MAX || ($far > 0 && $track === 'noorani') || ($far < 1 && $track === 'review')) {
            $errors['far_pages'] = 'far';
        }
        if (! in_array($data['pass_grade'] ?? null, Grades::PASSING, true)) {
            $errors['pass_grade'] = 'pass_grade';
        }
        $stops = $data['exam_stops'] ?? 'none';
        if (! in_array($stops, self::EXAM_STOPS, true) || ($stops !== 'none' && in_array($track, ['noorani', 'review'], true))) {
            $errors['exam_stops'] = 'exam_stops';
        }

        return $errors;
    }

    /**
     * The fields as kept (errors() is empty for them).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $noorani = $data['track'] === 'noorani';

        return [
            'name' => trim((string) $data['name']),
            'track' => $data['track'],
            'scope_from_surah' => $noorani ? null : (int) $data['scope_from_surah'],
            'scope_from_verse' => (int) $data['scope_from_verse'],
            'scope_to_surah' => $noorani ? null : (int) $data['scope_to_surah'],
            'scope_to_verse' => (int) $data['scope_to_verse'],
            'direction' => $noorani ? 'forward' : $data['direction'],
            'unit' => $noorani ? 'lesson' : $data['unit'],
            'new_amount' => (float) $data['new_amount'],
            'near_portions' => (int) $data['near_portions'],
            'far_pages' => (int) $data['far_pages'],
            'pass_grade' => $data['pass_grade'],
            'exam_stops' => $data['exam_stops'] ?? 'none',
            'counts_for_certificate' => (bool) ($data['counts_for_certificate'] ?? false),
        ];
    }

    /**
     * A learner's own pace on a plan: the new amount in the program's unit, the near and far reviews (null keeps
     * the program's). errors() as above.
     *
     * @param  array{new_amount?: mixed, near_portions?: mixed, far_pages?: mixed}  $pace
     * @return array<string, string>
     */
    public static function paceErrors(string $track, string $unit, array $pace): array
    {
        $program = ['name' => 'pace', 'track' => $track, 'unit' => $unit, 'direction' => 'forward', 'scope_from_surah' => 1, 'scope_from_verse' => 1,
            'scope_to_surah' => 1, 'scope_to_verse' => 1, 'pass_grade' => 'good', 'exam_stops' => 'none',
            'new_amount' => $pace['new_amount'] ?? ($track === 'review' ? 0 : self::AMOUNTS[$unit][0]),
            'near_portions' => $pace['near_portions'] ?? 0, 'far_pages' => $pace['far_pages'] ?? ($track === 'review' ? 1 : 0)];

        return array_intersect_key(self::errors($program, [1 => 1], 1), array_flip(['new_amount', 'near_portions', 'far_pages']));
    }
}
