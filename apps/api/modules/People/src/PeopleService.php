<?php

namespace Modules\People;

use Modules\People\Actions\LinkGuardian;
use Modules\People\Contracts\PeopleApi;

final class PeopleService implements PeopleApi
{
    public function __construct(private readonly LinkGuardian $linkGuardian) {}

    public function linkGuardian(string $guardianId, string $studentId, string $relationship = 'parent', bool $isPrimary = true): void
    {
        $this->linkGuardian->handle($guardianId, $studentId, $relationship, $isPrimary);
    }
}
