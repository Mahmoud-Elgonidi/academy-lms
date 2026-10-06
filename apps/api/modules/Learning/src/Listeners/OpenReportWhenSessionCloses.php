<?php

namespace Modules\Learning\Listeners;

use Modules\Learning\Actions\OpenSessionReport;
use Modules\Scheduling\Contracts\Events\SessionFinalized;

final class OpenReportWhenSessionCloses
{
    public function __construct(private readonly OpenSessionReport $open) {}

    public function handle(SessionFinalized $event): void
    {
        $this->open->handle($event->sessionId, $event->outcome);
    }
}
