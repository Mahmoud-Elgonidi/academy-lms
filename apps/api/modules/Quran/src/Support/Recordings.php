<?php

namespace Modules\Quran\Support;

/**
 * Where recordings are kept and what they may be: the private disk homework
 * recordings are on (Learning's), and the sound types a recitation takes.
 */
final class Recordings
{
    public const DISK = 'local';

    /** Up to this many KB. */
    public const MAX_KB = 25 * 1024;

    public const AUDIO = ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/webm', 'audio/wav', 'audio/x-wav', 'audio/x-m4a', 'video/webm', 'video/mp4'];
}
