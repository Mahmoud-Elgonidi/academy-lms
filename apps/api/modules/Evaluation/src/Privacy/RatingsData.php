<?php

namespace Modules\Evaluation\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * The lessons a person rated: the stars stay for the teacher's numbers
 * (they never carried a name for the teacher anyway); erasing takes back
 * their words and tags.
 */
final class RatingsData implements PersonalDataSource
{
    public function key(): string
    {
        return 'ratings';
    }

    public function export(string $userId): PersonalDataPart
    {
        return new PersonalDataPart([
            'ratings' => DB::table('lesson_ratings as r')->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'r.academy_id')->on('s.id', '=', 'r.session_id'))
                ->where('r.rater_id', $userId)->orderBy('r.created_at')
                ->get(['s.starts_at as lesson_at', 'r.stars', 'r.tags', 'r.comment', 'r.created_at'])
                ->map(fn (object $r) => ['tags' => json_decode((string) $r->tags, true)] + (array) $r)->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        return ['rating_words' => DB::table('lesson_ratings')->where('rater_id', $userId)
            ->where(fn ($q) => $q->whereNotNull('comment')->orWhere('tags', '!=', '[]'))
            ->update(['comment' => null, 'tags' => '[]', 'updated_at' => now()])];
    }
}
