<?php

namespace Modules\SharedKernel\Health;

/**
 * Why an outside service would not do what we asked, in our words: the
 * gateways and the console's "Try it" tell the provider's code apart into
 * one of these (Health\Faults\*), so the platform team reads "the credit ran
 * out" rather than "HTTP 429".
 */
enum ServiceProblem: string
{
    /** The key or token was refused: wrong, revoked or expired. */
    case Key = 'key';
    /** The key works but may not do this (scopes, region, an API not enabled). */
    case Permission = 'permission';
    /** The credit, quota or plan ran out, or the payment did not go through. */
    case Quota = 'quota';
    /** The account is suspended, closed or blocked. */
    case Suspended = 'suspended';
    /** The model (or template) asked for is not there for this key. */
    case Model = 'model';
    /** Something set up there is missing or wrong (a sender, a project). */
    case Config = 'config';
    /** Too many requests for now. */
    case Rate = 'rate';
    /** The service had trouble on its side. */
    case Down = 'down';
    /** It could not be reached at all. */
    case Unreachable = 'unreachable';
    case Unknown = 'unknown';

    /** A person has to act (top up, a new key, a setting): every call fails until then. */
    public function severe(): bool
    {
        return in_array($this, [self::Key, self::Permission, self::Quota, self::Suspended, self::Model, self::Config], true);
    }

    /** It may pass on its own. */
    public function transient(): bool
    {
        return in_array($this, [self::Rate, self::Down, self::Unreachable], true);
    }
}
