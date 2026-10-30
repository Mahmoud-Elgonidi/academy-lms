<?php

namespace Modules\Scheduling\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
use Modules\Scheduling\Actions\Shifts\CommentOnShiftProposal;
use Modules\Scheduling\Actions\Shifts\SaveShiftProposal;
use Modules\Scheduling\Actions\Shifts\ShiftProposals;
use Modules\Scheduling\Actions\Shifts\WithdrawShiftProposal;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Models\ShiftProposalComment;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Http\V1\Requests\SaveShiftProposalRequest;
use Modules\Scheduling\Http\V1\Resources\ShiftWeekResource;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** The signed-in teacher's shifts. Thin: every change goes through the same Actions as the back office. */
final class TeacherShiftsController
{
    public function __construct(
        private readonly ShiftProposals $proposals,
        private readonly LocalTimePresenter $time,
    ) {}

    /** The teacher's schedule in force today, one approved to start later, the open proposal and the last decisions. */
    public function show(Request $request): JsonResponse
    {
        $teacherId = $request->user()->getAuthIdentifier();
        $timezone = $this->proposals->teacherTimezone($teacherId);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $current = $this->proposals->scheduleOn($teacherId, $today, $timezone);
        $upcoming = $this->proposals->upcoming($teacherId, $today);
        $proposal = $this->proposals->openOrDraft($teacherId);

        return response()->json(['data' => [
            'timezone' => $timezone,
            'current' => ShiftWeekResource::make($current),
            // An approved schedule that starts on a later day: `current` stays until then.
            'upcoming' => $upcoming ? [
                // The first day it is in force (`local` holds that date; `label` names the day).
                'starts_on' => $this->time->academyDay($upcoming->effectiveFrom()),
                'version' => $upcoming->version,
                'timezone' => $upcoming->timezone,
                'weekly_minutes' => $upcoming->shifts()->weeklyMinutes(),
                // Same shape as `current.blocks`.
                'blocks' => $upcoming->shifts()->toArray(),
            ] : null,
            'proposal' => $proposal ? $this->proposal($proposal->load('rules', 'comments')) : null,
            // The last three decided proposals, newest first.
            'history' => ShiftProposal::query()
                ->where('teacher_id', $teacherId)
                ->whereIn('status', [ShiftProposalStatus::Approved->value, ShiftProposalStatus::Superseded->value, ShiftProposalStatus::Rejected->value])
                ->orderByDesc('decided_at')->orderByDesc('updated_at')
                ->limit(3)
                ->get()
                ->map(fn (ShiftProposal $p) => [
                    'id' => $p->id,
                    'status' => $p->status->value,
                    'status_label' => __('scheduling::shifts.statuses.'.$p->status->value),
                    // The start date the proposal asked for.
                    'effective_from' => $p->effective_from->toDateString(),
                    'decided_at' => $this->time->present($p->decided_at),
                    // The admins' reason, for a rejected proposal only.
                    'reason' => $p->status === ShiftProposalStatus::Rejected ? $p->decision_note : null,
                ])->values()->all(),
            'earliest_start' => [
                'adding_time' => $today->addDay()->toDateString(),
                'removing_time' => $today->addDays($this->proposals->settings()->reduction_notice_days)->toDateString(),
            ],
        ]]);
    }

    public function save(SaveShiftProposalRequest $request, SaveShiftProposal $save): JsonResponse
    {
        $teacherId = $request->user()->getAuthIdentifier();
        $hours = fn (?string $key) => $request->filled($key) ? (int) round((float) $request->input($key) * 60) : null;

        try {
            $shifts = WeeklyShifts::fromArray($request->input('blocks', []), $this->proposals->teacherTimezone($teacherId));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['blocks' => __('scheduling::shifts.problems.empty')]);
        }

        $proposal = $save->handle(
            $teacherId,
            $shifts,
            CarbonImmutable::parse($request->input('effective_from')),
            $request->input('note'),
            $hours('min_weekly_hours'),
            $hours('max_weekly_hours'),
            $request->boolean('submit', true),
        );

        return response()->json(['data' => $this->proposal($proposal->load('rules', 'comments'))]);
    }

    public function withdraw(Request $request, WithdrawShiftProposal $withdraw): JsonResponse
    {
        $proposal = $withdraw->handle($this->open($request), $request->user()->getAuthIdentifier());

        return response()->json(['data' => $this->proposal($proposal->load('rules', 'comments'))]);
    }

    public function accept(Request $request, ApproveShiftProposal $approve): JsonResponse
    {
        $teacherId = $request->user()->getAuthIdentifier();
        $proposal = $approve->handle($this->open($request), $teacherId, 'teacher');

        return response()->json(['data' => $this->proposal($proposal->load('rules', 'comments'))]);
    }

    public function comment(Request $request, CommentOnShiftProposal $comment): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $proposal = $this->open($request);
        $comment->handle($proposal, $request->user()->getAuthIdentifier(), 'teacher', $request->input('body'));

        return response()->json(['data' => $this->proposal($proposal->load('rules', 'comments'))], 201);
    }

    private function open(Request $request): ShiftProposal
    {
        return $this->proposals->openOrDraft($request->user()->getAuthIdentifier())
            ?? throw ValidationException::withMessages(['proposal' => __('scheduling::shifts.errors.not_now')]);
    }

    /** @return array<string, mixed> */
    private function proposal(ShiftProposal $p): array
    {
        return [
            'id' => $p->id,
            'status' => $p->status->value,
            'status_label' => __('scheduling::shifts.statuses.'.$p->status->value),
            'waiting_on' => $p->status->waitingOn(),
            'revision' => $p->revision,
            'author_role' => $p->rules->where('revision', $p->revision)->first()?->author_role ?? $p->author_role,
            'effective_from' => $p->effective_from->toDateString(),
            'note' => $p->note,
            'min_weekly_minutes' => $p->min_weekly_min,
            'max_weekly_minutes' => $p->max_weekly_min,
            'week' => ShiftWeekResource::make($p->shifts()),
            'previous_week' => $p->revision > 1 ? ShiftWeekResource::make($p->shifts($p->revision - 1)) : null,
            'submitted_at' => $this->time->present($p->submitted_at),
            'comments' => $p->comments->map(fn (ShiftProposalComment $c) => [
                'author_role' => $c->author_role,
                'mine' => $c->author_id === $p->teacher_id,
                'body' => $c->body,
                'at' => $this->time->present($c->created_at),
            ])->values()->all(),
        ];
    }
}
