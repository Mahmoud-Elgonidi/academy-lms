<?php

namespace Modules\People\Actions\Checks;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\People\Checks\Clearances;
use Modules\People\Checks\Events\TeacherCheckExpiring;
use Modules\People\Models\TeacherCheck;

/**
 * A verified document that runs out within a month is mentioned once to the
 * teacher and the academy, and once more on the day after it ran out, unless
 * a newer one of the same kind already covers the time after it.
 *
 * @return int how many messages went out
 */
final class RemindTeacherChecks
{
    public function __construct(private readonly Clearances $clearances) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $today = $this->clearances->today($now)->toDateString();
        $soon = $this->clearances->today($now)->addDays(TeacherCheck::WARN_DAYS)->toDateString();
        $sent = 0;
        $due = TeacherCheck::query()->where('status', 'verified')->whereNotNull('expires_on')
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereNull('expiry_warned_at')->where('expires_on', '<=', $soon))
                ->orWhere(fn ($w) => $w->whereNull('expired_noted_at')->where('expires_on', '<', $today)))
            ->orderBy('expires_on')->get();
        foreach ($due as $check) {
            $expired = $check->expires_on->toDateString() < $today;
            // A newer verified check of the same kind that lasts longer takes over: nothing to say.
            $covered = TeacherCheck::query()->where('teacher_id', $check->teacher_id)->where('kind', $check->kind->value)->where('status', 'verified')
                ->whereKeyNot($check->id)->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>', $check->expires_on->toDateString()))->exists();
            DB::transaction(function () use ($check, $expired, $covered, &$sent) {
                $marks = $expired ? ['expired_noted_at' => now(), 'expiry_warned_at' => $check->expiry_warned_at ?? now()] : ['expiry_warned_at' => now()];
                $updated = TeacherCheck::query()->whereKey($check->id)->whereNull($expired ? 'expired_noted_at' : 'expiry_warned_at')->update($marks);
                if ($updated === 1 && ! $covered) {
                    $sent++;
                    DB::afterCommit(fn () => event(new TeacherCheckExpiring($check->academy_id, $check->id, $expired)));
                }
            });
        }

        return $sent;
    }
}
