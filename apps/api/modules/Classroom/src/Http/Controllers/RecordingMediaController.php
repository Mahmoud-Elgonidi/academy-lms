<?php

namespace Modules\Classroom\Http\Controllers;

use Modules\Classroom\Models\Recording;
use Modules\Classroom\Recordings\RecordingStore;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plays a recording through the address WatchRecording signed (whoever opened
 * it was checked and logged there); in the page, never as a download. A
 * removed one is gone.
 */
final class RecordingMediaController
{
    public function __invoke(string $recording, RecordingStore $store): Response
    {
        $model = Recording::query()->whereKey($recording)->where('status', 'ready')->first();
        abort_if($model === null || ! $store->exists($model), 404);

        return $store->serve($model);
    }
}
