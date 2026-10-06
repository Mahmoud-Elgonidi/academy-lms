<?php

namespace Modules\Evaluation\Listeners;

use Modules\Evaluation\Actions\RequestLessonRating;
use Modules\Scheduling\Contracts\Events\SessionFinalized;

/** A lesson that took place may ask its family for a rating (docs/architecture/04: Evaluation listens to SessionFinalized). */
final class AskForRatings
{
    public function __construct(private readonly RequestLessonRating $request) {}

    public function handle(SessionFinalized $event): void
    {
        $this->request->handle($event->sessionId, $event->outcome);
    }
}
