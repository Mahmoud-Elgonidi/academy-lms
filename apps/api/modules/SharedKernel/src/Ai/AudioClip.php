<?php

namespace Modules\SharedKernel\Ai;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** A recording to hear: a file on this server, its type, and a name for the provider. */
final readonly class AudioClip
{
    public function __construct(
        public string $path,
        public string $mime,
        public string $name = 'audio',
    ) {}

    public static function fromUpload(UploadedFile $file): self
    {
        return new self((string) $file->getRealPath(), (string) $file->getMimeType(), 'audio.'.($file->guessExtension() ?: 'bin'));
    }

    public static function fromDisk(string $disk, string $path, string $mime): self
    {
        return new self(Storage::disk($disk)->path($path), $mime, basename($path));
    }

    public function bytes(): int
    {
        return is_file($this->path) ? (int) filesize($this->path) : 0;
    }
}
