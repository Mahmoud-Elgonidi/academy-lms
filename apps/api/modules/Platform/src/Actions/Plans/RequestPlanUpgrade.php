<?php

namespace Modules\Platform\Actions\Plans;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Plans\PlanUpgradeRequest;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\SaasSubscription;
use Modules\Platform\Plans\Requests\PlanRequestMail;
use Modules\Platform\Plans\Requests\PlanRequests;

/**
 * The academy asks for a bigger plan (Settings › Academy plan, or the
 * warning's button): the plan it would like among those on offer, if it
 * chose one, and a note. Its owner or an admin, never while the platform
 * team is signed in as someone, one open at a time. The team changes the
 * plan in the console (ChangeAcademyPlan) and marks it handled. Audited; the
 * team hears of it by email.
 */
final class RequestPlanUpgrade
{
    public const NOTE_MAX = 500;

    public function __construct(private readonly PlanRequests $requests, private readonly PlatformAudit $audit) {}

    /** @throws ValidationException */
    public function handle(?string $planId, ?string $note, string $userId): PlanUpgradeRequest
    {
        $academy = $this->requests->authorizeAcademy();
        $note = filled($note) ? trim((string) $note) : null;
        $planId = filled($planId) ? (string) $planId : null;
        Validator::make(['note' => $note], ['note' => ['nullable', 'string', 'max:'.self::NOTE_MAX]], [], ['note' => __('platform::topups.fields.note')])->validate();
        $current = SaasSubscription::currentFor($academy->id);
        $plan = $planId === null ? null : SaasPlan::query()->offered()->find($planId);
        if ($planId !== null && ($plan === null || $plan->id === $current?->plan_id)) {
            throw ValidationException::withMessages(['plan' => __('platform::topups.errors.no_plan_choice')]);
        }
        if ($plan === null && $note === null) {
            throw ValidationException::withMessages(['plan' => __('platform::topups.errors.say_what')]);
        }

        try {
            $request = DB::connection('pgsql_platform')->transaction(function () use ($academy, $plan, $current, $note, $userId) {
                if (PlanUpgradeRequest::query()->where('academy_id', $academy->id)->where('status', 'requested')->exists()) {
                    throw ValidationException::withMessages(['plan' => __('platform::topups.errors.upgrade_waiting')]);
                }
                $request = PlanUpgradeRequest::query()->create([
                    'academy_id' => $academy->id,
                    'plan_id' => $plan?->id,
                    'from_plan_id' => $current?->plan_id,
                    'status' => 'requested',
                    'requested_by' => $userId,
                    'note' => $note,
                ]);
                $this->audit->record($academy->id, 'academy.upgrade_requested', 'plan_upgrade_request', $request->id, $plan === null ? [] : ['plan' => $plan->name_ar.' / '.$plan->name_en]);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['plan' => __('platform::topups.errors.upgrade_waiting')]);
        }

        PlanRequests::tellTeam(new PlanRequestMail('upgrade', [
            'academy' => $academy->nameEn.' / '.$academy->nameAr,
            'what' => ($current?->plan?->name_en ?? '—').' → '.($plan?->name_en ?? '?'),
            'note' => $note,
        ]));

        return $request;
    }
}
