<?php

namespace Modules\Classroom\Video;

/**
 * Where and how a recording is written: video (the room laid out, low
 * quality) or sound only, into a file on the academy's storage. For a disk on
 * S3 (or R2) the provider uploads it there with the disk's own keys; for a
 * disk on this server it writes to the same folder seen from the recorder.
 */
final readonly class RecordingOutput
{
    /** @param  array{key: string, secret: string, region: string, bucket: string, endpoint: ?string, path_style: bool}|null  $s3 */
    public function __construct(
        public string $media,          // video | audio
        public string $filepath,       // where the recorder writes it (a key on S3, else a path it sees)
        public ?array $s3 = null,
    ) {}

    public function audioOnly(): bool
    {
        return $this->media === 'audio';
    }
}
