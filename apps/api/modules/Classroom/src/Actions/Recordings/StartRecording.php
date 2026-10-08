<?php

namespace Modules\Classroom\Actions\Recordings;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Authorization\RecordingAccess;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Recordings\RecordingRules;
use Modules\Classroom\Recordings\RecordingStore;
use Modules\Classroom\Video\ProviderManager;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * Starts recording a lesson (docs/architecture/02, "تسجيل الحصص"): when the
 * academy records lessons, the lesson's room is open on our own video, and
 * everyone it records has agreed. Its teacher or a supervisor starts it, or
 * it starts by itself when the academy records every lesson. One recording
 * runs at a time; asking again while one runs returns it.
 */
final class StartRecording
{
    public function __construct(
        private readonly AttendanceApi $lessons,
        private readonly RecordingAccess $access,
        private readonly RecordingRules $rules,
        private readonly RecordingStore $store,
        private readonly ProviderManager $providers,
        private readonly TenantContext $tenant,
    ) {}

    /** @param  ?string  $byUserId  null: it starts by itself (the academy records every lesson) */
    public function handle(string $sessionId, ?string $byUserId, ?CarbonImmutable $now = null): Recording
    {
        $now ??= CarbonImmutable::now();
        $settings = ClassroomSettings::current();
        if (! $settings->records()) {
            throw ValidationException::withMessages(['recording' => __('classroom::recordings.errors.off')]);
        }
        $lesson = $this->lessons->lesson($sessionId) ?? throw ValidationException::withMessages(['recording' => __('classroom::classroom.errors.not_yours')]);
        if ($byUserId !== null && ! $this->access->controls($byUserId, $lesson)) {
            throw new AuthorizationException;
        }
        if (! $lesson->isOpen() || $now->greaterThan($lesson->endsAt->addMinutes(config('classroom.closes_after_min')))
            || $now->lessThan($lesson->startsAt->subMinutes(config('classroom.opens_before_min')))) {
            throw ValidationException::withMessages(['recording' => __('classroom::recordings.errors.not_now')]);
        }
        $room = Room::query()->where('session_id', $sessionId)->first();
        if ($room === null || $room->finished_at !== null) {
            throw ValidationException::withMessages(['recording' => __('classroom::recordings.errors.no_room')]);
        }
        $recorder = $this->providers->recorder($room->provider) ?? throw ValidationException::withMessages(['recording' => __('classroom::recordings.errors.outside_link')]);
        if ($missing = $this->rules->missing($lesson)) {
            throw ValidationException::withMessages(['recording' => __('classroom::recordings.errors.consent_missing', ['names' => implode('، ', $missing)])]);
        }
        if ($running = Recording::query()->running()->where('session_id', $sessionId)->first()) {
            return $running;
        }

        $media = $this->rules->mediaFor($lesson, $settings);
        $id = (string) Str::uuid7();
        try {
            $recording = Recording::query()->forceCreate([
                'id' => $id,
                'session_id' => $sessionId,
                'room_id' => $room->id,
                'provider' => $room->provider,
                'media' => $media,
                'status' => 'starting',
                'started_by' => $byUserId,
                'disk' => $this->store->disk(),
                'path' => Recording::pathFor($this->tenant->require()->id, $sessionId, $id, $media),
                'mime' => $media === 'audio' ? 'audio/ogg' : 'video/mp4',
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two presses at once: the first one runs.
            return Recording::query()->running()->where('session_id', $sessionId)->firstOrFail();
        }

        try {
            $job = $recorder->startRecording($room, $this->store->output($recording));
        } catch (Throwable $e) {
            report($e);
            $recording->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 490)]);
            throw ValidationException::withMessages(['recording' => __('classroom::recordings.errors.recorder')]);
        }
        $recording->update(['egress_id' => $job]);
        $recorder->announceRecording($room, true);

        return $recording;
    }
}
