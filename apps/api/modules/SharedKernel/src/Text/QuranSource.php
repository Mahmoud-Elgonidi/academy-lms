<?php

namespace Modules\SharedKernel\Text;

/**
 * Where the platform's copy of the Quran comes from (Curriculum binds it:
 * Tanzil's files, fetched to the server and checked before they are kept),
 * for the console to show and to fetch it again. Default: none.
 */
interface QuranSource
{
    /**
     * Whether the text is here, and the copy kept: where it is (server: fetched to the server's storage; code:
     * shipped with the code), Tanzil's notice line, the files' SHA-256 and when they were fetched.
     *
     * @return array{available: bool, where: ?string, notice: ?string, verses: ?int, text_sha256: ?string, data_sha256: ?string, fetched_at: ?string}
     */
    public function status(): array;

    /**
     * Fetches Tanzil's files, checks them whole and keeps them in place of any copy before. Nothing is kept
     * when a check fails.
     *
     * @return array{available: bool, where: ?string, notice: ?string, verses: ?int, text_sha256: ?string, data_sha256: ?string, fetched_at: ?string}
     *
     * @throws QuranFetchFailed with the reason in the reader's words
     */
    public function fetch(): array;
}
