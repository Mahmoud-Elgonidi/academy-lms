<?php

namespace Modules\Learning\Http\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Authorization\LearningAccess;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\HomeworkSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A handed-in file for the signed-in teacher or staff in the panels, inline so recordings play in the page. */
final class SubmissionFileController
{
    public function __invoke(Request $request, string $submission, LearningAccess $access): StreamedResponse
    {
        $user = $request->user() ?? abort(403);
        $submission = HomeworkSubmission::query()->findOrFail($submission);
        $homework = Homework::query()->findOrFail($submission->homework_id);
        abort_unless($submission->file_path !== null && $access->canSeeHomework($user, $homework), 404);

        return Storage::disk(SubmitHomework::DISK)->response($submission->file_path, $submission->file_name, ['Content-Type' => $submission->mime]);
    }
}
