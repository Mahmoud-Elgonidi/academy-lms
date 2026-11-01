<?php

namespace Modules\Quran\Mushaf;

use Illuminate\Validation\Rule;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Models\QuranSettings;

/** What a change to a lesson's shared mushaf takes, the same from the apps' API and the class page. */
final class MushafInput
{
    public const CHANGES = ['turn', 'mark', 'unmark'];

    /** @return array<string, list<mixed>> */
    public static function rules(string $change): array
    {
        $word = [
            'surah' => ['required', 'integer', 'between:1,114'], 'verse' => ['required', 'integer', 'min:1', 'max:300'],
            'word' => ['required', 'integer', 'min:1', 'max:200'], 'page' => ['nullable', 'integer', 'between:1,604'],
        ];

        return match ($change) {
            'turn' => ['page' => ['required', 'integer', 'between:1,604']],
            'mark' => $word + ['kind' => ['required', Rule::in(QuranSettings::current()->mistakeKinds())]],
            'unmark' => $word,
        };
    }

    /** The page to answer with: the one given, else the marked word's. */
    public static function pageOf(array $data, QuranApi $quran): ?int
    {
        if (isset($data['page'])) {
            return (int) $data['page'];
        }

        return isset($data['surah'], $data['verse'])
            ? ($quran->quranPagesOf(VerseRange::of((int) $data['surah'], (int) $data['verse'], (int) $data['verse']))[0] ?? null)
            : null;
    }
}
