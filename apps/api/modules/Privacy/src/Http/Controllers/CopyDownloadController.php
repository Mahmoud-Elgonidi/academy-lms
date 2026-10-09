<?php

namespace Modules\Privacy\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Privacy\Models\DataRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A person's copy of their data, through its signed address (an hour), while it is kept: a ZIP, downloaded, never shown inline. */
final class CopyDownloadController
{
    public function __invoke(string $request): StreamedResponse
    {
        $copy = Str::isUuid($request) ? DataRequest::query()->find($request) : null;
        abort_if($copy === null, 404);
        abort_unless($copy->isDownloadable(), 410);
        abort_unless(Storage::disk(DataRequest::DISK)->exists((string) $copy->file_path), 410);

        return Storage::disk(DataRequest::DISK)->download((string) $copy->file_path, 'personal-data-'.$copy->created_at->format('Y-m-d').'.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
