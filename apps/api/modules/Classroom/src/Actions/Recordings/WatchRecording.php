<?php

namespace Modules\Classroom\Actions\Recordings;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Authorization\RecordingAccess;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\RecordingAccessLog;
use Modules\Classroom\Recordings\RecordingLink;
use Modules\Classroom\Recordings\RecordingStore;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Someone opens a recording to watch it: whoever may (RecordingAccess), a
 * ready one whose file is there. The opening is logged (who, as what, from
 * where), and they get an address on the academy's host, signed for as long
 * as the recording plus half an hour, that plays it in the page (never as a
 * download).
 */
final class WatchRecording
{
    public function __construct(
        private readonly RecordingAccess $access,
        private readonly RecordingStore $store,
        private readonly TenantDirectory $directory,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Recording $recording, string $userId, ?string $ip = null, ?string $userAgent = null): RecordingLink
    {
        $as = $this->access->watcherAs($userId, $recording) ?? throw new AuthorizationException;
        if (! $recording->isReady() || ! $this->store->exists($recording)) {
            throw ValidationException::withMessages(['recording' => __('classroom::recordings.errors.not_ready')]);
        }
        RecordingAccessLog::query()->create([
            'recording_id' => $recording->id,
            'user_id' => $userId,
            'as' => $as,
            'ip' => $ip !== null ? Str::limit($ip, 45, '') : null,
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 300, '') : null,
            'opened_at' => now(),
        ]);
        $expires = CarbonImmutable::now()->addSeconds((int) $recording->duration_sec)->addMinutes((int) config('classroom.recordings.link_minutes', 30));
        $path = URL::temporarySignedRoute('classroom.recording.media', $expires, ['recording' => $recording->id], absolute: false);

        return new RecordingLink($this->directory->baseUrl($this->tenant->require()).$path, $expires);
    }
}
