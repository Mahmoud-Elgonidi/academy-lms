<?php

namespace Modules\Credits\Contracts;

/** Where a lot of minutes came from; makeup and bonus minutes are never refunded. */
enum LotSource: string
{
    case Purchase = 'purchase';
    case Makeup = 'makeup';
    case Trial = 'trial';
    case Bonus = 'bonus';
    case Adjustment = 'adjustment';

    public function isRefundable(): bool
    {
        return $this === self::Purchase;
    }
}
