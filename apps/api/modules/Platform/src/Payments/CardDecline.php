<?php

namespace Modules\Platform\Payments;

/**
 * Why a charge on the academy's saved card did not go through, in our own
 * words (never Stripe's): a soft decline is tried once more a day later; a
 * hard one turns automatic recharge off at once, and one that says the card
 * itself is done (expired, lost, no longer valid) lets the card go too.
 */
enum CardDecline: string
{
    case Declined = 'declined';
    case InsufficientFunds = 'insufficient_funds';
    case ExpiredCard = 'expired_card';
    case LostCard = 'lost_card';
    case InvalidCard = 'invalid_card';
    case AuthenticationRequired = 'authentication_required';
    case Processing = 'processing_error';

    /** Tried once more a day later. */
    public function soft(): bool
    {
        return in_array($this, [self::Declined, self::InsufficientFunds, self::Processing], true);
    }

    /** The card itself can no longer be charged. */
    public function endsCard(): bool
    {
        return in_array($this, [self::ExpiredCard, self::LostCard, self::InvalidCard], true);
    }

    /** Stripe's code (and decline code, for card_declined) as a kind. */
    public static function fromStripe(string $code, string $declineCode = ''): self
    {
        $code = strtolower(trim($code === 'card_declined' && $declineCode !== '' ? $declineCode : $code));

        return match (true) {
            in_array($code, ['insufficient_funds', 'card_velocity_exceeded', 'withdrawal_count_limit_exceeded'], true) => self::InsufficientFunds,
            $code === 'expired_card' => self::ExpiredCard,
            in_array($code, ['lost_card', 'stolen_card', 'pickup_card', 'restricted_card'], true) => self::LostCard,
            in_array($code, ['authentication_required', 'card_not_enrolled'], true) => self::AuthenticationRequired,
            in_array($code, ['invalid_number', 'incorrect_number', 'invalid_account', 'new_account_information_available', 'card_not_supported', 'resource_missing'], true) => self::InvalidCard,
            in_array($code, ['processing_error', 'try_again_later', 'issuer_not_available', 'reenter_transaction'], true) => self::Processing,
            default => self::Declined,
        };
    }

    public function sentence(?string $locale = null): string
    {
        return __('platform::recharge.declines.'.$this->value, [], $locale);
    }
}
