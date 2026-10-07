<?php

namespace Modules\Identity\Enums;

enum OtpPurpose: string
{
    /** Sign in to the academy the request is for. */
    case SignIn = 'sign_in';

    /** Find the person's accounts across academies (the shared app's first screen). */
    case Discover = 'discover';
}
