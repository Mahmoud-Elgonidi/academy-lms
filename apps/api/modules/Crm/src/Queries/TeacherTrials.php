<?php

namespace Modules\Crm\Queries;

use Carbon\CarbonImmutable;
use Modules\Billing\Contracts\BillingApi;
use Modules\Crm\Models\LeadTrial;

/**
 * A teacher's trial lessons: to assess (attended, no assessment yet), coming
 * up, and assessed in the last 30 days (latest first). No contact details:
 * the learner's name, age and what the family asked for.
 */
final class TeacherTrials
{
    public function __construct(private readonly BillingApi $billing) {}

    /** @return array{to_assess: list<array<string, mixed>>, upcoming: list<array<string, mixed>>, done: list<array<string, mixed>>} */
    public function of(string $teacherId): array
    {
        $now = CarbonImmutable::now();
        $locale = app()->getLocale();
        $plans = collect($this->billing->planOptions(null))->keyBy('id');
        $rows = LeadTrial::query()
            ->join('leads as l', fn ($j) => $j->on('l.academy_id', '=', 'lead_trials.academy_id')->on('l.id', '=', 'lead_trials.lead_id'))
            ->leftJoin('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'l.academy_id')->on('s.id', '=', 'l.subject_id'))
            ->where('lead_trials.teacher_id', $teacherId)
            ->where(fn ($q) => $q->where('lead_trials.status', 'booked')
                ->orWhere(fn ($q) => $q->where('lead_trials.status', 'attended')->where(fn ($q) => $q->whereNull('lead_trials.assessed_at')->orWhere('lead_trials.assessed_at', '>', $now->subDays(30)))))
            ->orderBy('lead_trials.starts_at')
            ->get(['lead_trials.*', 'l.contact_name', 'l.for_child', 'l.learner_name', 'l.learner_birth_year', 'l.notes as lead_notes', 's.name_ar as subject_ar', 's.name_en as subject_en']);

        $groups = ['to_assess' => [], 'upcoming' => [], 'done' => []];
        foreach ($rows as $t) {
            /** @var LeadTrial $t */
            $group = match (true) {
                $t->status === 'booked' => 'upcoming',
                $t->assessed_at === null => 'to_assess',
                default => 'done',
            };
            $groups[$group][] = [
                'id' => $t->id,
                'session_id' => $t->session_id,
                'name' => $t->getAttribute('for_child') && $t->getAttribute('learner_name') ? $t->getAttribute('learner_name') : $t->getAttribute('contact_name'),
                'age' => $t->getAttribute('learner_birth_year') ? __('crm::trials.age', ['years' => $now->year - (int) $t->getAttribute('learner_birth_year')]) : null,
                'subject' => $locale === 'ar' ? $t->getAttribute('subject_ar') : $t->getAttribute('subject_en'),
                'starts_at' => $t->starts_at,
                'notes' => $t->getAttribute('lead_notes'),
                'level' => $t->level,
                'plan_id' => $t->recommended_plan_id,
                'plan' => $t->recommended_plan_id ? ($plans[$t->recommended_plan_id]->name ?? null) : null,
                'per_week' => $t->recommended_per_week,
                'assessment' => $t->assessment,
            ];
        }
        $groups['done'] = array_reverse($groups['done']);

        return $groups;
    }
}
