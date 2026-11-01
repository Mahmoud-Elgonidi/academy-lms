<?php

namespace Modules\Quran\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** An examiner recorded an exam's result (the family hears of it). Raised after commit. */
final class ExamDecided implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly string $examId) {}
}
