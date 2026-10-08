<?php

namespace Modules\Classroom\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Classroom\Actions\Recordings\HandleEgressEvent;
use Modules\Classroom\Actions\Recordings\RecordWhenBothIn;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Models\WebhookInboxItem;
use Modules\Scheduling\Contracts\AttendanceApi;
use Throwable;

/**
 * A checked LiveKit event, for the academy its room belongs to: kept once in
 * the inbox, then turned into attendance (who came and went, when), or into
 * a recording's progress (egress events). A supervisor's comings and goings
 * are not attendance. When the academy records every lesson, the teacher and
 * the student both being in starts it.
 */
final class HandleLiveKitWebhook
{
    public const EVENTS = ['participant_joined', 'participant_left', 'room_started', 'room_finished', 'egress_started', 'egress_updated', 'egress_ended'];

    public function __construct(
        private readonly AttendanceApi $attendance,
        private readonly HandleEgressEvent $egress,
        private readonly RecordWhenBothIn $autoRecord,
    ) {}

    /** @param  array<string, mixed>  $event */
    public function handle(array $event, Room $room): void
    {
        $name = (string) ($event['event'] ?? '');
        if (! in_array($name, self::EVENTS, true)) {
            return;
        }
        $at = isset($event['createdAt']) ? CarbonImmutable::createFromTimestamp((int) $event['createdAt']) : CarbonImmutable::now();

        try {
            $item = WebhookInboxItem::query()->create([
                'provider' => 'livekit',
                'event_id' => (string) ($event['id'] ?? Str::uuid7()),
                'event' => $name,
                'payload' => $event,
                'received_at' => CarbonImmutable::now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return;     // delivered before
        }

        try {
            if (str_starts_with($name, 'egress_')) {
                $this->egress->handle((array) ($event['egressInfo'] ?? $event['egress_info'] ?? []), $at);
                $item->update(['processed_at' => CarbonImmutable::now()]);

                return;
            }
            DB::transaction(function () use ($name, $event, $room, $at) {
                $identity = (string) ($event['participant']['identity'] ?? '');
                $person = $identity !== '' && ! str_starts_with($identity, 'observer:') ? $identity : null;

                if ($name === 'participant_joined' && $person !== null) {
                    $this->attendance->participantJoined($room->session_id, $person, $at);
                } elseif ($name === 'participant_left' && $person !== null) {
                    $this->attendance->participantLeft($room->session_id, $person, $at);
                } elseif ($name === 'room_started') {
                    $room->update(['started_at' => $room->started_at ?? $at, 'finished_at' => null]);   // open again after everyone had left
                } elseif ($name === 'room_finished') {
                    $this->finish($room, $at);
                }
            });
            $item->update(['processed_at' => CarbonImmutable::now()]);
            if ($name === 'participant_joined') {
                $this->autoRecord->handle($room->refresh());
            }
        } catch (Throwable $e) {
            $item->update(['error' => Str::limit($e->getMessage(), 490)]);
            report($e);
        }
    }

    private function finish(Room $room, CarbonImmutable $at): void
    {
        foreach ($this->attendance->inClassroom($room->session_id) as $userId) {
            $this->attendance->participantLeft($room->session_id, $userId, $at);
        }
        $room->update(['finished_at' => $at]);
    }
}
