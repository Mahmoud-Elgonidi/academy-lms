<?php

namespace Modules\Scheduling\Domain\Enums;

enum FundingStatus: string
{
    case Funded = 'funded';
    case Unfunded = 'unfunded';
    case Free = 'free';
    case Makeup = 'makeup';
}
