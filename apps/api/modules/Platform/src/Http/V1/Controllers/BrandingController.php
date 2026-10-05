<?php

namespace Modules\Platform\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Platform\Contracts\BrandingApi;

/** The academy's name, logo and look, for the web and mobile apps (public: needed before sign-in). */
final class BrandingController
{
    public function show(BrandingApi $branding): JsonResponse
    {
        return response()->json(['data' => $branding->current()->toArray()])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
