<?php

namespace Modules\Classroom\Actions\Recordings;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Classroom\Contracts\Events\RecordingReady;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\ProviderManager;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * What the recorder says about a recording (LiveKit's egress_started,
 * egress_updated and egress_ended, with its EgressInfo): it is running, it is
 * ending, or it is over, with the file it made. A file with something in it
 * is ready (even one cut short); none is a failure. Its days start at its
 * end. Anything after it is over changes nothing.
 */
final class HandleEgressEvent
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly TenantContext $tenant,
    ) {}

    /** @param  array<string, mixed>  $info  the EgressInfo */
    public function handle(array $info, CarbonImmutable $at): void
    {
        $job = (string) ($info['egressId'] ?? $info['egress_id'] ?? '');
        $status = (string) ($info['status'] ?? '');
        $done = null;

        DB::transaction(function () use ($job, $status, $info, $at, &$done) {
            $recording = Recording::query()->where('egress_id', $job)->lockForUpdate()->first();
            if ($recording === null || ! $recording->isRunning()) {
                return;
            }
            match ($status) {
                // Running; a word that it started arriving after someone stopped it keeps it ending.
                'EGRESS_ACTIVE' => $recording->update([
                    'status' => $recording->status === 'starting' ? 'recording' : $recording->status,
                    'started_at' => $recording->started_at ?? self::time($info['startedAt'] ?? $info['started_at'] ?? null) ?? $at,
                ]),
                'EGRESS_ENDING' => $recording->update(['status' => 'ending']),
                'EGRESS_COMPLETE', 'EGRESS_FAILED', 'EGRESS_ABORTED', 'EGRESS_LIMIT_REACHED' => $done = $this->finish($recording, $info, $at),
                default => null,
            };
        });

        if ($done !== null) {
            $room = Room::query()->find($done->room_id);
            $recorder = $this->providers->recorder($done->provider);
            if ($room !== null && $recorder !== null && ! Recording::query()->running()->where('session_id', $done->session_id)->exists()) {
                $recorder->announceRecording($room, false);
            }
        }
    }

    /** @param  array<string, mixed>  $info */
    private function finish(Recording $recording, array $info, CarbonImmutable $at): Recording
    {
        $file = $info['fileResults'][0] ?? $info['file_results'][0] ?? $info['file'] ?? [];
        $size = (int) ($file['size'] ?? 0);
        $ended = self::time($info['endedAt'] ?? $info['ended_at'] ?? null) ?? $at;
        $error = trim((string) ($info['error'] ?? '')) ?: null;

        if ($size <= 0) {
            $recording->update(['status' => 'failed', 'ended_at' => $ended, 'error' => Str::limit($error ?? (string) ($info['status'] ?? 'no file'), 490)]);

            return $recording;
        }
        $started = $recording->started_at ?? self::time($info['startedAt'] ?? $info['started_at'] ?? null) ?? $ended;
        $recording->update([
            'status' => 'ready',
            'started_at' => $started,
            'ended_at' => $ended,
            'size' => $size,
            'duration_sec' => isset($file['duration']) ? (int) round(((int) $file['duration']) / 1e9) : max(0, (int) $started->diffInSeconds($ended)),
            'expires_at' => $ended->addDays(ClassroomSettings::current()->recording_days),
            'error' => $error !== null ? Str::limit($error, 490) : null,
        ]);
        $academy = $this->tenant->require()->id;
        DB::afterCommit(fn () => event(new RecordingReady($academy, $recording->id, $recording->session_id, $recording->media, (int) $recording->duration_sec)));

        return $recording;
    }

    /** LiveKit's times are nanoseconds since the epoch (a number, or a string of one). */
    private static function time(mixed $nanos): ?CarbonImmutable
    {
        return is_numeric($nanos) && (int) $nanos > 0 ? CarbonImmutable::createFromTimestampMs(intdiv((int) $nanos, 1_000_000)) : null;
    }
}
