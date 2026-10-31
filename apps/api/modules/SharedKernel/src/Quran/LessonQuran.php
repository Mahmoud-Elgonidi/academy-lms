<?php

namespace Modules\SharedKernel\Quran;

use Illuminate\Contracts\Support\Htmlable;

/**
 * The Quran beside a lesson's room (docs/architecture/02, section 26): what
 * the class page shows next to the video for a person with a seat in the
 * lesson, drawn by the Quran module (which binds this) while the academy's
 * plan has the Quran. Default: nothing (NoLessonQuran), and the page is a
 * room like any other.
 */
interface LessonQuran
{
    /**
     * The panel for this person on the lesson's class page, ready to show; null when the lesson has none for them.
     *
     * @param  array{channel: string, auth: string, echo: ?array<string, mixed>}  $live  the lesson's own channel, where
     *                                                                                   the page signs it, and the socket's settings (null: none)
     */
    public function classPanel(string $sessionId, string $viewerId, array $live): ?Htmlable;
}
