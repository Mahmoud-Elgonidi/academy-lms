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

    /** Prove a mobile number is the visitor's before a trial is booked for it from the academy's page (no account needed). */
    case Trial = 'trial';

    /** A code for this purpose proves the number alone: it is right with or without an account behind it. */
    public function needsNoAccount(): bool
    {
        return $this === self::Discover || $this === self::Trial;
    }
}
