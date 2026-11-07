<?php

namespace Modules\Platform\Actions\TopUps;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\Platform\Models\Plans\UsageTopUp;
use Modules\Platform\Plans\PlanFormat;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\Platform\Plans\Requests\PlanRequestMail;
use Modules\Platform\Plans\Requests\PlanRequests;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;

/**
 * The academy asks for an extra pack of video minutes or AI uses for this
 * month (Settings › Academy plan): its owner or an admin, never while the
 * platform team is signed in as someone, one waiting per kind. Nothing is
 * added until the platform team, paid its own way, turns it on in the
 * console (ActivateTopUp). Audited; the team hears of it by email.
 */
final class RequestTopUp
{
    public const NOTE_MAX = 500;

    public function __construct(
        private readonly PlanRequests $requests,
        private readonly PlatformAudit $audit,
        private readonly PlatformAcademyPlan $plan,
    ) {}

    /** @throws ValidationException */
    public function handle(string $packId, ?string $note, string $userId): UsageTopUp
    {
        $academy = $this->requests->authorizeAcademy();
        $note = filled($note) ? trim((string) $note) : null;
        Validator::make(['note' => $note], ['note' => ['nullable', 'string', 'max:'.self::NOTE_MAX]], [], ['note' => __('platform::topups.fields.note')])->validate();

        $pack = TopUpPack::query()->offered()->find($packId);
        if ($pack === null) {
            throw ValidationException::withMessages(['pack' => __('platform::topups.errors.no_pack')]);
        }
        $limit = $pack->limitKind();
        if ($limit === Limit::AiUses && ! $this->plan->has(Feature::Ai)) {
            throw ValidationException::withMessages(['pack' => __('platform::topups.errors.no_ai')]);
        }
        if ($this->plan->limit($limit) === null) {
            throw ValidationException::withMessages(['pack' => __('platform::topups.errors.no_limit')]);
        }

        try {
            $topUp = DB::connection('pgsql_platform')->transaction(function () use ($academy, $pack, $limit, $note, $userId) {
                if (UsageTopUp::query()->where('academy_id', $academy->id)->where('limit', $limit->value)->where('status', 'requested')->exists()) {
                    throw ValidationException::withMessages(['pack' => __('platform::topups.errors.one_waiting')]);
                }
                $topUp = UsageTopUp::query()->create([
                    'academy_id' => $academy->id,
                    'limit' => $limit->value,
                    'amount' => $pack->amount,
                    'period' => PlanRequests::monthKey($academy->defaultTimezone),
                    'status' => 'requested',
                    'pack_id' => $pack->id,
                    'requested_by' => $userId,
                    'note' => $note,
                ]);
                $this->audit->record($academy->id, 'academy.topup_requested', 'usage_topup', $topUp->id, ['limit' => $limit->value, 'amount' => $pack->amount]);

                return $topUp;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['pack' => __('platform::topups.errors.one_waiting')]);
        }

        PlanRequests::tellTeam(new PlanRequestMail('topup', [
            'academy' => $academy->nameEn.' / '.$academy->nameAr,
            'what' => __('shared::plans.limits.'.$limit->value, [], 'en').': +'.PlanFormat::amount($limit, $pack->amount).' ('.$pack->name_en.')',
            'note' => $note,
        ]));
        PlanRequests::forget($academy->id);

        return $topUp;
    }
}
