<?php

namespace Modules\People\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\People\Models\TeacherCheck;
use Modules\SharedKernel\Authorization\Ability;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A teacher's vetting document, inline, for the teacher who sent it and the academy's safeguarding leads; nobody else. */
final class CheckFileController
{
    public function __invoke(Request $request, string $check): StreamedResponse
    {
        $user = $request->user() ?? abort(403);
        $check = Str::isUuid($check) ? TeacherCheck::query()->find($check) : null;
        abort_unless($check !== null && $check->file_path !== null
            && ($check->teacher_id === $user->getAuthIdentifier() || Gate::forUser($user)->allows(Ability::HandleSafeguarding->value)), 404);

        return Storage::disk(TeacherCheck::DISK)->response($check->file_path, $check->file_name, [
            'Content-Type' => $check->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
