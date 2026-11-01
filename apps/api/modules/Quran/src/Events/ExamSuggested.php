<?php

namespace Modules\Quran\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** An exam was suggested at a stop of a learner's plan (who may examine hears of it). Raised after commit. */
final class ExamSuggested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly string $examId) {}
}
