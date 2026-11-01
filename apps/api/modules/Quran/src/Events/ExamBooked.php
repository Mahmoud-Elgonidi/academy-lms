<?php

namespace Modules\Quran\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** An exam was booked as an assessment lesson with its examiner (the family and the examiner hear of it). Raised after commit. */
final class ExamBooked implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly string $examId) {}
}
