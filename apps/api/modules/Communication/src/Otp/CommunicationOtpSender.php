<?php

namespace Modules\Communication\Otp;

use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Modules\Communication\Channels\ChannelManager;
use Modules\Communication\Mail\OtpMail;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Contracts\OtpSender;
use Modules\SharedKernel\Health\MailHealth;
use Throwable;

/**
 * Identity's codes, by the platform's channels: WhatsApp's authentication
 * template, a text, or an email. Codes are not notifications: no inbox, no
 * quiet hours, and nothing of them is kept in the message log.
 */
final class CommunicationOtpSender implements OtpSender
{
    public function __construct(private readonly ChannelManager $channels) {}

    public function supports(string $channel): bool
    {
        return match ($channel) {
            'whatsapp' => in_array(config('communication.whatsapp.driver'), ['log', 'meta'], true),
            'sms' => $this->channels->sms() !== null,
            'email' => true,
            default => false,
        };
    }

    public function send(OtpMessage $message): void
    {
        match ($message->channel) {
            'whatsapp' => $this->channels->whatsapp()->sendCode($message->destination, $message->code, $message->locale),
            'sms' => $this->channels->sms()?->send($message->destination, $this->text($message))
                ?? throw new InvalidArgumentException('Texts are turned off.'),
            'email' => $this->mail($message),
            default => throw new InvalidArgumentException("Unknown channel [{$message->channel}]."),
        };
    }

    private function mail(OtpMessage $message): void
    {
        try {
            Mail::to($message->destination)->send(new OtpMail($message));
        } catch (Throwable $e) {
            MailHealth::failed($e);
            throw $e;
        }
    }

    private function text(OtpMessage $message): string
    {
        $text = trans('communication::otp.sms', ['code' => $message->code, 'name' => $message->senderName, 'minutes' => $message->minutes], $message->locale);

        // WebOTP: the browser offers the code on that host's sign-in page.
        return $message->host === null ? $text : $text."\n\n@{$message->host} #{$message->code}";
    }
}
