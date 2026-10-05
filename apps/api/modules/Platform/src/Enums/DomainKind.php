<?php

namespace Modules\Platform\Enums;

enum DomainKind: string
{
    case Subdomain = 'subdomain';
    case Custom = 'custom';
}
