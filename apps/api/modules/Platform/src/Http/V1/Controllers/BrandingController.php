<?php

namespace Modules\Platform\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Time\ViewerClock;

/** The academy's name, logo and look, for the web and mobile apps (public: needed before sign-in). */
final class BrandingController
{
    /**
     * The academy's look.
     *
     * With `app`: the oldest app version still served (an older app asks to be updated first) and where to update it.
     * `time_format` is how the academy writes a clock for everyone who has not chosen their own (pages before sign-in).
     */
    public function show(BrandingApi $branding, ViewerClock $clock): JsonResponse
    {
        return response()->json([
            'data' => $branding->current()->toArray(),
            /** h12 (5:00 PM) or h24 (17:00): the academy's choice. */
            'time_format' => $clock->academy()->value,
            'app' => [
                /** The oldest app version still served, like "1.0.0". */
                'min_version' => (string) config('platform.app.min_version'),
                /** The store page on Android, or null. */
                'android_url' => config('platform.app.android_url'),
                /** The App Store page, or null. */
                'ios_url' => config('platform.app.ios_url'),
            ],
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
