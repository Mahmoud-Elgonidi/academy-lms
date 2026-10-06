<?php

namespace Modules\Credits\Contracts;

/** What happens to a session's reserved minutes when it does not take place. */
enum ReleaseMode: string
{
    /** The minutes go back to the lots they came from. */
    case Refund = 'refund';

    /** The minutes become a makeup lot that expires sooner and is never refunded. */
    case Makeup = 'makeup';
}
