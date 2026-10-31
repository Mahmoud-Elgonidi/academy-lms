<?php

namespace Modules\Quran\Bridges;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Modules\Quran\Mushaf\ClassMushaf;
use Modules\SharedKernel\Quran\LessonQuran;

/** The shared mushaf beside a Quran lesson's room on the class page (SharedKernel's LessonQuran). */
final class QuranLessons implements LessonQuran
{
    public function __construct(private readonly ClassMushaf $mushaf) {}

    public function classPanel(string $sessionId, string $viewerId, array $live): ?Htmlable
    {
        $first = $this->mushaf->page($sessionId, $viewerId);
        if ($first === null) {
            return null;
        }

        return new HtmlString(view('quran::classroom.mushaf', ['mushaf' => [
            'first' => $first,
            'urls' => [
                'show' => route('quran.class.mushaf', ['session' => $sessionId]),
                'change' => route('quran.class.mushaf.change', ['session' => $sessionId]),
                'auth' => $live['auth'],
            ],
            'echo' => $live['echo'],
            'channel' => $live['channel'],
        ]])->render());
    }
}
