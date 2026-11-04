<?php

namespace Modules\Billing\Payments\Cards;

use Modules\Billing\Domain\Cards\ChargeMoment;

/**
 * The words a payer ticks at checkout to save their card and renew
 * automatically (docs/architecture/02, section 27, J5b): billing::cards.mandate.text,
 * in their language. A saved card keeps the version and a hash of the very
 * words they read. Raise VERSION when the words change in meaning: a checkout
 * sent with an older version is refused until they read it again.
 */
final class Mandate
{
    public const VERSION = '2027-07-20';

    /** How long before a renewal's charge the payer is told (and may turn it off). */
    public const NOTICE_DAYS = ChargeMoment::NOTICE_DAYS;

    public static function text(?string $locale = null): string
    {
        return __('billing::cards.mandate.text', ['days' => self::NOTICE_DAYS], $locale);
    }

    public static function hash(string $locale): string
    {
        return hash('sha256', self::VERSION."\n".self::text($locale));
    }
}
