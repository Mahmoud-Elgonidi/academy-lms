<?php

namespace Modules\Scheduling\Domain\LiveBoard;

enum BoardGroup: string
{
    case Attention = 'attention';
    case Fine = 'fine';
    case Upcoming = 'upcoming';
    case Done = 'done';
}
