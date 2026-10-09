<?php

namespace Modules\Import\Listeners;

use Modules\Import\Contracts\Events\PeopleImported;
use Modules\People\Contracts\PeopleApi;

/** Each person an import made an account for gets People's welcome, with their own link to choose a password. */
final class SendWelcomes
{
    public function __construct(private readonly PeopleApi $people) {}

    public function handle(PeopleImported $event): void
    {
        if ($event->invite) {
            $this->people->sendWelcome($event->userIds);
        }
    }
}
