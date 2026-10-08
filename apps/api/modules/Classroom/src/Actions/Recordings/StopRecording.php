<?php

namespace Modules\Classroom\Actions\Recordings;

use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\ProviderManager;

/**
 * Stops a running recording: whoever runs the lesson asked, a consent was
 * taken back, the academy stopped recording, or the lesson is over. The
 * provider finishes the file and its webhook marks it ready. One that never
 * got going is marked failed.
 */
final class StopRecording
{
    public function __construct(private readonly ProviderManager $providers) {}

    /** @param  ?string  $byUserId  null: the system (the lesson is over) */
    public function handle(Recording $recording, ?string $byUserId): Recording
    {
        if (! $recording->isRunning()) {
            return $recording;
        }
        $recorder = $this->providers->recorder($recording->provider);
        if ($recording->egress_id !== null && $recorder !== null) {
            $recorder->stopRecording($recording->egress_id);
        }
        // Only while it still runs: the provider's webhook may have finished it meanwhile.
        Recording::query()->whereKey($recording->id)->whereIn('status', Recording::RUNNING)->update($recording->egress_id !== null
            ? ['status' => 'ending', 'stopped_by' => $byUserId, 'updated_at' => now()]
            : ['status' => 'failed', 'stopped_by' => $byUserId, 'error' => 'stopped before it started', 'updated_at' => now()]);
        $room = Room::query()->find($recording->room_id);
        if ($room !== null && $recorder !== null) {
            $recorder->announceRecording($room, false);
        }

        return $recording->refresh();
    }
}
