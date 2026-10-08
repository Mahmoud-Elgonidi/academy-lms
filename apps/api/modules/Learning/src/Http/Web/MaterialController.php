<?php

namespace Modules\Learning\Http\Web;

use Illuminate\Support\Facades\Storage;
use Modules\Curriculum\Contracts\CurriculumApi;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A lesson's file behind a signed address (MaterialLinks): opened in the
 * browser or the app's player, a recording or a video seeking by ranges.
 */
final class MaterialController
{
    public function __invoke(string $resource, CurriculumApi $curriculum): BinaryFileResponse
    {
        $file = $curriculum->resourceFile($resource);
        abort_if($file === null || ! Storage::disk($file->disk)->exists($file->path), 404);

        return response()->file(Storage::disk($file->disk)->path($file->path), [
            'Content-Type' => $file->mime,
            'Content-Disposition' => 'inline; filename*=UTF-8\'\''.rawurlencode($file->name),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
