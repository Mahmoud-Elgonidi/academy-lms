<?php

namespace Modules\SharedKernel\Quran;

use Illuminate\Contracts\Support\Htmlable;

/** No Quran beside any lesson: the module is not there, or the academy's plan leaves it out. */
final class NoLessonQuran implements LessonQuran
{
    public function classPanel(string $sessionId, string $viewerId, array $live): ?Htmlable
    {
        return null;
    }
}
