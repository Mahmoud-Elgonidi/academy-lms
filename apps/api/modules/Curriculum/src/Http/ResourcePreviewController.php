<?php

namespace Modules\Curriculum\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Curriculum\Models\NodeResource;
use Modules\SharedKernel\Authorization\Ability;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** A lesson's file for the staff who build the curriculum, opened in the browser (a recording or a video seeks). */
final class ResourcePreviewController
{
    public function __invoke(Request $request, string $resource): BinaryFileResponse
    {
        abort_unless(Gate::allows(Ability::ManageCurriculum->value), 403);
        $r = NodeResource::query()->where('kind', 'file')->findOrFail($resource);
        abort_unless(Storage::disk(NodeResource::DISK)->exists((string) $r->file_path), 404);

        return response()->file(Storage::disk(NodeResource::DISK)->path((string) $r->file_path), [
            'Content-Type' => $r->mime,
            'Content-Disposition' => 'inline; filename*=UTF-8\'\''.rawurlencode((string) $r->file_name),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
