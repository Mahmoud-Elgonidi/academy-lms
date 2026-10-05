<?php

namespace Modules\Scheduling\Domain\Enums;

/** A property of the session, not a state (docs/architecture/02-domain-rules.md, section 5). */
enum SessionType: string
{
    case Regular = 'regular';
    case Trial = 'trial';
    case Makeup = 'makeup';
    case Extra = 'extra';
    case Assessment = 'assessment';
}
