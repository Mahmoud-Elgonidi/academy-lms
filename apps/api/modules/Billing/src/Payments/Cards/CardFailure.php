<?php

namespace Modules\Billing\Payments\Cards;

/**
 * Why a charge on a saved card did not go through, in our own words (never
 * the gateway's): a soft decline is tried once more a day later; a hard one
 * (the card expired, was lost or stolen, or is no longer valid; the bank asks
 * for the payer, 3-D Secure; we could not confirm it) never is: the payer
 * gets the pay link.
 */
enum CardFailure: string
{
    case Declined = 'declined';
    case InsufficientFunds = 'insufficient_funds';
    case ExpiredCard = 'expired_card';
    case LostCard = 'lost_card';
    case InvalidCard = 'invalid_card';
    case AuthenticationRequired = 'authentication_required';
    case Processing = 'processing_error';
    case Unconfirmed = 'unconfirmed';

    /** Tried again a day later (once). */
    public function retries(): bool
    {
        return in_array($this, [self::Declined, self::InsufficientFunds, self::Processing], true);
    }

    /** The card itself can no longer be charged: it is let go (and auto-renew with it, when no other card is left). */
    public function endsCard(): bool
    {
        return in_array($this, [self::ExpiredCard, self::LostCard, self::InvalidCard], true);
    }

    /** The reason, in the payer's words. */
    public function sentence(?string $locale = null): string
    {
        return __('billing::cards.failures.'.$this->value, [], $locale);
    }

    /**
     * Reads the common decline codes (ISO 8583 response codes, as Moyasar and Tap
     * pass them, and Stripe's codes and decline codes) and words into a kind.
     */
    public static function fromCode(?string $code, ?string $message = null): self
    {
        $code = strtolower(trim((string) $code));
        $text = strtolower((string) $message);
        $iso = ltrim($code, '0');

        return match (true) {
            in_array($code, ['insufficient_funds', 'card_velocity_exceeded', 'withdrawal_count_limit_exceeded'], true), $iso === '51', $iso === '61',
            str_contains($text, 'insufficient') => self::InsufficientFunds,
            in_array($code, ['expired_card'], true), $iso === '54', $iso === '33', str_contains($text, 'expired') => self::ExpiredCard,
            in_array($code, ['lost_card', 'stolen_card', 'pickup_card', 'restricted_card'], true), in_array($iso, ['41', '43', '4', '7', '62'], true),
            str_contains($text, 'lost') || str_contains($text, 'stolen') => self::LostCard,
            in_array($code, ['authentication_required', 'card_not_enrolled'], true), in_array($iso, ['1a', '65'], true),
            str_contains($text, '3ds') || str_contains($text, '3-d secure') || str_contains($text, 'authentication') => self::AuthenticationRequired,
            in_array($code, ['invalid_number', 'incorrect_number', 'invalid_account', 'new_account_information_available', 'card_not_supported', 'resource_missing', 'token_inactive'], true),
            in_array($iso, ['14', '15', '57', '78'], true), str_contains($text, 'token') || str_contains($text, 'invalid card') => self::InvalidCard,
            in_array($code, ['processing_error', 'try_again_later', 'issuer_not_available', 'reenter_transaction'], true), in_array($iso, ['91', '96', '19'], true) => self::Processing,
            default => self::Declined,
        };
    }
}
