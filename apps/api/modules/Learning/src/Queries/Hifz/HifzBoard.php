<?php

namespace Modules\Learning\Queries\Hifz;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Domain\Hifz\Scheduler;
use Modules\Learning\Hifz\HifzPages;
use Modules\Learning\Models\HifzPage;
use Modules\Learning\Queries\Mushaf\MistakesBook;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The academy's hifz at a glance (the back office's "Hifz"): each course a
 * hifz map follows (its curriculum reads the Quran, or its student has
 * pages), not ended, with its student's map in numbers: memorized, being
 * learned, weak, due by the end of today on the academy's clock, the last
 * review and the words marked in the last months. Read each time; the most
 * in need first.
 */
final class HifzBoard
{
    public function __construct(
        private readonly HifzPages $pages,
        private readonly HifzMap $map,
        private readonly TenantContext $tenants,
    ) {}

    /** Whether the platform has the Quran's text (else there is no map to show). */
    public function available(): bool
    {
        return $this->pages->count() > 0;
    }

    /**
     * @return list<array{enrollment: string, student: string, teacher_id: string, teacher: string, subject: string, memorized: int,
     *     learning: int, weak: int, due: int, last_review: ?CarbonImmutable, words: int}>
     */
    public function rows(CarbonImmutable $now, string $search = '', ?string $teacherId = null): array
    {
        if (! $this->available()) {
            return [];
        }
        $name = app()->getLocale() === 'ar' ? 's.name_ar' : 's.name_en';
        $courses = DB::table('enrollments as e')
            ->join('users as st', fn ($j) => $j->on('st.academy_id', '=', 'e.academy_id')->on('st.id', '=', 'e.student_id'))
            ->join('users as te', fn ($j) => $j->on('te.academy_id', '=', 'e.academy_id')->on('te.id', '=', 'e.teacher_id'))
            ->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.subject_id'))
            ->where('e.status', '!=', 'ended')
            ->when($teacherId !== null && $teacherId !== '', fn ($q) => $q->where('e.teacher_id', $teacherId))
            ->when(trim($search) !== '', fn ($q) => $q->where('st.name', 'ilike', '%'.addcslashes(trim($search), '%_\\').'%'))
            ->get(['e.id', 'e.student_id', 'e.teacher_id', 'e.curriculum_version_id', 'st.name as student', 'te.name as teacher', DB::raw("{$name} as subject")]);
        $mapped = HifzPage::query()->whereIn('student_id', $courses->pluck('student_id')->unique()->all())->distinct()->pluck('student_id')->flip();
        $readsQuran = [];
        $courses = $courses->filter(function ($c) use ($mapped, &$readsQuran) {
            if ($mapped->has($c->student_id)) {
                return true;
            }
            if ($c->curriculum_version_id === null) {
                return false;
            }

            return $readsQuran[$c->curriculum_version_id] ??= $this->map->readsQuran((string) $c->curriculum_version_id);
        })->values();
        if ($courses->isEmpty()) {
            return [];
        }

        $students = $courses->pluck('student_id')->unique()->values()->all();
        $endOfDay = $now->setTimezone($this->tenants->require()->defaultTimezone)->endOfDay();
        $cards = HifzPage::query()->whereIn('student_id', $students)->get()->groupBy('student_id');
        $last = DB::table('hifz_reviews')->whereIn('student_id', $students)->groupBy('student_id')->selectRaw('student_id, max(reviewed_at) as at')->pluck('at', 'student_id');
        $words = DB::table('recitation_mistakes')->whereIn('student_id', $students)->where('updated_at', '>=', $now->subMonths(MistakesBook::MONTHS))
            ->groupBy('student_id')->selectRaw('student_id, count(distinct (surah, verse, word)) as n')->pluck('n', 'student_id');

        $rows = $courses->map(function ($c) use ($cards, $last, $words, $endOfDay, $now) {
            $mine = $cards->get($c->student_id, collect());

            return [
                'enrollment' => (string) $c->id,
                'student' => (string) $c->student,
                'teacher_id' => (string) $c->teacher_id,
                'teacher' => (string) $c->teacher,
                'subject' => (string) $c->subject,
                'memorized' => $mine->where('state', 'memorized')->count(),
                'learning' => $mine->where('state', 'learning')->count(),
                'weak' => $mine->filter(fn (HifzPage $p) => $p->state === 'memorized' && Scheduler::strength($p->card(), $now) === 'weak')->count(),
                'due' => $mine->filter(fn (HifzPage $p) => $p->due_at->lessThanOrEqualTo($endOfDay))->count(),
                'last_review' => isset($last[$c->student_id]) ? CarbonImmutable::parse($last[$c->student_id]) : null,
                'words' => (int) ($words[$c->student_id] ?? 0),
            ];
        })->all();
        usort($rows, fn (array $a, array $b) => [$b['weak'] + $b['due'], $a['student']] <=> [$a['weak'] + $a['due'], $b['student']]);

        return $rows;
    }
}
