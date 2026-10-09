<?php

namespace Modules\Identity\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    // Anonymised at the person's request (Privacy): no name, no way in, never active again.
    case Erased = 'erased';
}
