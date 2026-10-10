<?php

namespace Modules\Identity\Enums;

enum OtpPurpose: string
{
    /** Sign in to the academy the request is for. */
    case SignIn = 'sign_in';

    /** Find the person's accounts across academies (the shared app's first screen). */
    case Discover = 'discover';

    /** Prove a new email or mobile number is the signed-in person's before it becomes a way in. */
    case ChangeContact = 'contact';
}
