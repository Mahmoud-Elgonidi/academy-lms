<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Models\AttendanceSegment;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/** Someone left the classroom (the provider's webhook, its room API, or the end of an outside link). Idempotent. */
final class RecordParticipantLeave
{
    public function handle(Session $session, string $userId, ?CarbonImmutable $at = null, string $source = 'provider'): void
    {
        $at ??= CarbonImmutable::now();

        DB::transaction(function () use ($session, $userId, $at, $source) {
            $open = AttendanceSegment::query()->where('session_id', $session->id)->where('user_id', $userId)->whereNull('left_at')->lockForUpdate()->first();
            if ($open === null) {
                return;
            }
            $open->update(['left_at' => $at->max($open->joined_at)]);

            $participant = $session->participants()->where('user_id', $userId)->first();
            if ($participant !== null) {
                $participant->last_left_at = $at;
                $participant->attended_min = (int) round(AttendanceSegment::query()->where('session_id', $session->id)->where('user_id', $userId)->whereNotNull('left_at')
                    ->get()->sum(fn (AttendanceSegment $s) => $s->joined_at->diffInSeconds($s->left_at)) / 60);
                $participant->save();
            }

            SessionEvent::record($session, SessionEventKind::Left, ['user_id' => $userId, 'source' => $source], $userId, $at);
        });
    }
}
