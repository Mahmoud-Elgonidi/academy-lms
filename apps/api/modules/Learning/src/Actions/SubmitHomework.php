<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Contracts\Events\HomeworkSubmitted;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\HomeworkSubmission;
use Throwable;

/**
 * The student (or a guardian for a child) hands homework in: text, a file,
 * or a voice recording, as the homework accepts. Late is allowed; a second
 * hand-in adds to the first until the teacher has marked it. Files go to the
 * private disk under the academy's folder and are never public.
 */
final class SubmitHomework
{
    public const DISK = 'local';

    public const MAX_KB = 25 * 1024;

    /** Recordings: browsers record voice into webm (Chrome) or mp4 (Safari) containers, which read as video/*. */
    public const AUDIO = ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/webm', 'audio/wav', 'audio/x-wav', 'audio/x-m4a', 'video/webm', 'video/mp4'];

    public const FILES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic'];

    public function handle(Homework $homework, string $actorId, ?string $body, ?UploadedFile $file): HomeworkSubmission
    {
        if ($homework->status === 'reviewed') {
            throw ValidationException::withMessages(['homework' => __('learning::homework.errors.closed')]);
        }
        $body = trim((string) $body) ?: null;
        $mimes = match ($homework->accepts) {
            'audio' => self::AUDIO,
            'file' => self::FILES,
            default => [...self::AUDIO, ...self::FILES],
        };
        Validator::make(['body' => $body, 'file' => $file], [
            'body' => [$homework->accepts === 'text' ? 'required' : 'nullable', 'string', 'max:5000'],
            'file' => [in_array($homework->accepts, ['audio', 'file'], true) ? 'required' : 'nullable', 'file', 'max:'.self::MAX_KB, 'mimetypes:'.implode(',', $mimes)],
        ], [], trans('learning::homework.attributes'))->after(function ($v) use ($body, $file) {
            if ($body === null && $file === null) {
                $v->errors()->add('body', __('learning::homework.errors.empty'));
            }
        })->validate();

        return DB::transaction(function () use ($homework, $actorId, $body, $file) {
            $stored = $file?->storeAs("academies/{$homework->academy_id}/homework/{$homework->id}", Str::uuid7().'.'.($file->guessExtension() ?: 'bin'), ['disk' => self::DISK]);
            try {
                $submission = $homework->submissions()->create([
                    'body' => $body,
                    'file_path' => $stored ?: null,
                    'file_name' => $file ? Str::limit($file->getClientOriginalName(), 190, '') : null,
                    'mime' => $file?->getMimeType(),
                    'size' => $file?->getSize(),
                    'submitted_by' => $actorId,
                    'submitted_at' => CarbonImmutable::now(),
                ]);
                $homework->update(['status' => 'submitted']);
            } catch (Throwable $e) {
                if ($stored) {
                    Storage::disk(self::DISK)->delete($stored);
                }
                throw $e;
            }
            DB::afterCommit(fn () => event(new HomeworkSubmitted($homework->academy_id, $homework->id, $homework->student_id, $homework->teacher_id)));

            return $submission;
        });
    }
}
