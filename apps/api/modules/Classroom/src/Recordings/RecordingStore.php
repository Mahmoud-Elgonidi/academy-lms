<?php

namespace Modules\Classroom\Recordings;

use Illuminate\Support\Facades\Storage;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Video\RecordingOutput;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where recordings live: the disk the academy's recordings go to (this
 * server's private disk, or S3/R2). Tells the recorder where to write, and
 * serves, checks and removes the file.
 */
final class RecordingStore
{
    public function disk(): string
    {
        return (string) config('classroom.recordings.disk', 'local');
    }

    public function output(Recording $recording): RecordingOutput
    {
        $disk = (array) config("filesystems.disks.{$recording->disk}");
        if (($disk['driver'] ?? null) === 's3') {
            $root = trim((string) ($disk['root'] ?? ''), '/');

            return new RecordingOutput($recording->media, ($root !== '' ? $root.'/' : '').$recording->path, [
                'key' => (string) ($disk['key'] ?? ''),
                'secret' => (string) ($disk['secret'] ?? ''),
                'region' => (string) ($disk['region'] ?? ''),
                'bucket' => (string) ($disk['bucket'] ?? ''),
                'endpoint' => ($disk['endpoint'] ?? null) ?: null,
                'path_style' => (bool) ($disk['use_path_style_endpoint'] ?? false),
            ]);
        }
        // A disk on this server: the recorder writes into the same folder, as it sees it.
        $root = rtrim((string) (config('classroom.recordings.recorder_root') ?: Storage::disk($recording->disk)->path('')), '/');

        return new RecordingOutput($recording->media, $root.'/'.$recording->path);
    }

    public function exists(Recording $recording): bool
    {
        return Storage::disk($recording->disk)->exists($recording->path);
    }

    public function size(Recording $recording): int
    {
        return (int) Storage::disk($recording->disk)->size($recording->path);
    }

    public function delete(Recording $recording): void
    {
        Storage::disk($recording->disk)->delete($recording->path);
    }

    /** The file to play in the page (never as a download), or a short-lived address on S3 that does the same. */
    public function serve(Recording $recording): Response
    {
        $disk = Storage::disk($recording->disk);
        $name = 'lesson-'.$recording->created_at->format('Y-m-d').'.'.pathinfo($recording->path, PATHINFO_EXTENSION);
        if ((config("filesystems.disks.{$recording->disk}.driver")) === 's3') {
            return new RedirectResponse($disk->temporaryUrl($recording->path, now()->addMinutes(10), [
                'ResponseContentDisposition' => 'inline; filename="'.$name.'"',
                'ResponseContentType' => $recording->mime,
            ]));
        }
        $response = new BinaryFileResponse($disk->path($recording->path), 200, [
            'Content-Type' => $recording->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], false);
        $response->setContentDisposition('inline', $name);

        return $response;
    }
}
