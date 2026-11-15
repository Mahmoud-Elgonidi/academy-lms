<?php

namespace Modules\Identity\Onboarding;

use RuntimeException;

/**
 * Why the sign-in email did not leave: mail is not set up on this server
 * (nothing was tried), or the mailer refused it (named by its class, never
 * its words). The message is in the current language.
 */
final class AccessMailFailed extends RuntimeException
{
    public const NOT_SET_UP = 'not_set_up';

    public const REFUSED = 'refused';

    private function __construct(public readonly string $why, string $message, public readonly ?string $mailerError = null)
    {
        parent::__construct($message);
    }

    public static function notSetUp(): self
    {
        return new self(self::NOT_SET_UP, __('identity::onboarding.access_mail.not_set_up'));
    }

    public static function refused(string $error): self
    {
        return new self(self::REFUSED, __('identity::onboarding.access_mail.refused', ['error' => $error]), $error);
    }
}
