<?php

namespace Modules\SharedKernel\Health;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Mail to an address on .test (the demo's …@demo.test and …@platform.test,
 * a TLD that can never be delivered, RFC 2606) never goes out to a real mail
 * server: a server's SMTP account would only bounce it, and a run of bounces
 * marks mail as down on the health page (MailHealth) and can get the
 * account's sending paused. Every mail passes here (Laravel's MessageSending):
 * .test recipients are taken off, and a message left with nobody is not sent
 * at all, so it neither counts as sent nor as failed. Communication records
 * such a notification as skipped. Mail that goes nowhere anyway (the log and
 * array mailers) and local development (Mailpit catches everything) are left
 * as they are.
 */
final class TestAddresses
{
    public static function isTest(?string $address): bool
    {
        $host = strtolower(rtrim(substr(strrchr(trim((string) $address), '@') ?: '', 1), '.>'));

        return $host === 'test' || str_ends_with($host, '.test');
    }

    /** Whether mail to a .test address would be held back by this mailer (the default when null). */
    public static function heldBack(?string $mailer = null): bool
    {
        if (app()->environment('local')) {
            return false;
        }
        $mailer ??= (string) config('mail.default');
        $transport = (string) config("mail.mailers.{$mailer}.transport", $mailer);

        return ! in_array($transport, ['array', 'log'], true);
    }

    /** Whether a mail to this address is held back rather than sent. */
    public static function skips(?string $address, ?string $mailer = null): bool
    {
        return self::isTest($address) && self::heldBack($mailer);
    }

    /** MessageSending: off with the .test recipients; false (not sent) when none are left, else null so other listeners still run. */
    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;
        if (! $message instanceof Email || ! self::heldBack(isset($event->data['mailer']) ? (string) $event->data['mailer'] : null)) {
            return null;
        }

        $left = 0;
        foreach (['To', 'Cc', 'Bcc'] as $field) {
            $all = $message->{'get'.$field}();
            $kept = array_values(array_filter($all, fn (Address $a) => ! self::isTest($a->getAddress())));
            if (count($kept) !== count($all)) {
                $message->getHeaders()->remove($field);
                if ($kept !== []) {
                    $message->{strtolower($field)}(...$kept);
                }
            }
            $left += count($kept);
        }

        return $left > 0 ? null : false;
    }
}
