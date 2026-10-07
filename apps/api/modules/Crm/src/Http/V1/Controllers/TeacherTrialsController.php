<?php

namespace Modules\Crm\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Billing\Contracts\BillingApi;
use Modules\Crm\Actions\AssessTrial;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Queries\TeacherTrials;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** The teacher app: my trial lessons, and the assessment after one (no lesson report for a trial). */
final class TeacherTrialsController
{
    /**
     * My trial lessons.
     *
     * to_assess, upcoming, and done (assessed in the last 30 days); plans are what an assessment may recommend.
     */
    public function index(Request $request, TeacherTrials $trials, BillingApi $billing, LocalTimePresenter $time): JsonResponse
    {
        $present = fn (array $group) => array_map(fn (array $t) => ['starts_at' => $time->present($t['starts_at'])] + $t, $group);

        return response()->json(array_map($present, $trials->of((string) $request->user()?->getAuthIdentifier())) + [
            'plans' => array_map(fn ($p) => ['id' => $p->id, 'name' => $p->name], $billing->planOptions(null)),
        ]);
    }

    /**
     * Assess a trial.
     *
     * After the trial took place: the level, what to recommend, and notes for the sales team. Again to correct it.
     */
    public function assess(Request $request, string $trial, AssessTrial $assess, TeacherTrials $trials, LocalTimePresenter $time): JsonResponse
    {
        $request->validate([
            'level' => ['required', 'string', 'max:120'],
            'plan_id' => ['nullable', 'string', 'max:64'],
            'per_week' => ['nullable', 'integer', 'between:1,7'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        $me = (string) $request->user()?->getAuthIdentifier();
        $own = LeadTrial::query()->where('teacher_id', $me)->findOrFail($trial);
        $assess->handle($own, $request->only(['level', 'plan_id', 'per_week', 'notes']), $me);
        $row = collect($trials->of($me)['done'])->firstWhere('id', $own->id);

        return response()->json(['data' => ['starts_at' => $time->present($row['starts_at'])] + $row]);
    }
}
