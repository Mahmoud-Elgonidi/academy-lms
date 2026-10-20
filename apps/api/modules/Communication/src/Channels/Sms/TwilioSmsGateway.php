<?php

namespace Modules\Communication\Channels\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\SharedKernel\Health\Faults\TwilioFaults;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceHealth;
use RuntimeException;

/**
 * Twilio Programmable Messaging (HTTP, no SDK): POST
 * /2010-04-01/Accounts/{sid}/Messages.json with basic auth, from a sender
 * number or a messaging service (which picks a registered sender per country).
 * How Twilio answers goes to ServiceHealth ('twilio'); a bad or unsubscribed
 * number leaves it alone.
 */
final class TwilioSmsGateway implements SmsGateway
{
    /** @param  array{sid: ?string, token: ?string, from: ?string, messaging_service_sid: ?string}  $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'twilio';
    }

    public function send(string $phoneE164, string $text): string
    {
        $sid = $this->config['sid'] ?? null;
        if (blank($sid) || blank($this->config['token'] ?? null) || (blank($this->config['from'] ?? null) && blank($this->config['messaging_service_sid'] ?? null))) {
            throw new RuntimeException('SMS (Twilio) is not configured.');
        }

        $sender = filled($this->config['messaging_service_sid'] ?? null)
            ? ['MessagingServiceSid' => $this->config['messaging_service_sid']]
            : ['From' => $this->config['from']];

        try {
            $response = Http::withBasicAuth($sid, (string) $this->config['token'])->asForm()->acceptJson()->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", ['To' => $phoneE164, 'Body' => $text] + $sender);
        } catch (ConnectionException $e) {
            ServiceHealth::fault('twilio', ServiceFault::unreachable());
            throw $e;
        }

        if ($response->failed()) {
            if (($fault = TwilioFaults::of($response->status(), $response->json())) !== null) {
                ServiceHealth::fault('twilio', $fault);
            }
            throw new RuntimeException('SMS (Twilio) refused the message: '.($response->json('message') ?? $response->status()));
        }
        ServiceHealth::ok('twilio');

        return (string) $response->json('sid');
    }
}
