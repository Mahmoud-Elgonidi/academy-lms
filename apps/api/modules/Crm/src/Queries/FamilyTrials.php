<?php

namespace Modules\Crm\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Crm\Domain\NextTrialRules;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Support\NextTrials;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * A learner's free trials as their family sees them (docs/architecture/02,
 * section 27, J2), latest first: the subject, when, the teacher's first name,
 * how it went (to come, took place, missed by the learner or the teacher,
 * cancelled by the family or the academy), whether its report is ready, and,
 * in the classic look, one more trial it allows (the second trial after a
 * report, or booking again after one that did not take place) with why not.
 */
final class FamilyTrials
{
    public function __construct(private readonly NextTrials $next, private readonly LocalTimePresenter $time) {}

    /** @return list<array<string, mixed>> */
    public function of(string $learnerId, bool $classic, ?string $timezone = null): array
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $trials = LeadTrial::query()->with('lead')
            ->join('leads as l', fn ($j) => $j->on('l.academy_id', '=', 'lead_trials.academy_id')->on('l.id', '=', 'lead_trials.lead_id'))
            ->where('l.learner_id', $learnerId)
            ->orderByDesc('lead_trials.starts_at')->limit(20)
            ->get(['lead_trials.*']);
        $sessions = DB::table('sessions as s')
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->leftJoin('users as u', fn ($j) => $j->on('u.academy_id', '=', 's.academy_id')->on('u.id', '=', 's.teacher_id'))
            ->whereIn('s.id', $trials->pluck('session_id'))
            ->get(['s.id', 's.subject_id', 'su.name_ar', 'su.name_en', 'u.name as teacher'])->keyBy('id');

        $out = [];
        foreach ($trials as $trial) {
            /** @var LeadTrial $trial */
            $s = $sessions->get($trial->session_id);
            $out[] = [
                /** The trial lesson's id: its report is family/trials/{id}. */
                'id' => $trial->session_id,
                'subject' => ['id' => (string) ($s->subject_id ?? ''), 'name' => (string) ($locale === 'en' ? ($s->name_en ?? '') : ($s->name_ar ?? ''))],
                'starts_at' => $this->time->present($trial->starts_at, $timezone),
                /** The teacher's first name, with a title before it. */
                'teacher' => TrialReport::firstName((string) ($s->teacher ?? '')),
                /** first, rebook (booked again after one that did not take place) or second (the free second trial). */
                'kind' => $trial->kind,
                /** booked (to come), attended, missed or cancelled. */
                'status' => $trial->status,
                /** Why it did not take place: student_absent, teacher_absent (missed); family, academy (cancelled); else null. */
                'outcome' => $trial->outcome === null ? null : (string) $trial->outcome,
                /** Its report is ready to read. */
                'report_ready' => TrialReport::ready($trial),
                /** One more trial it allows (classic look only), or null. */
                'next' => $classic ? $this->next($trial) : null,
            ];
        }

        return $out;
    }

    /** @return ?array{kind: string, available: bool, why_not: ?string} */
    private function next(LeadTrial $trial): ?array
    {
        $facts = $this->next->facts($trial, true);
        if ($facts === null || ! NextTrialRules::shown($facts)) {
            return null;
        }
        $why = NextTrialRules::whyNot($facts);

        return [
            /** second (with another teacher) or rebook (after one that did not take place). */
            'kind' => $facts->kind,
            'available' => $why === null,
            /** Why not, in the family's words (what to do instead); null when it may be booked. */
            'why_not' => $why === null ? null : (string) __('crm::report.next.why.'.$why, ['count' => NextTrialRules::MAX_OPEN_PER_NUMBER]),
        ];
    }
}
