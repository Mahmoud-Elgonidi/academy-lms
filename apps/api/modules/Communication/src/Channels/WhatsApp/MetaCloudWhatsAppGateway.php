<?php

namespace Modules\Communication\Channels\WhatsApp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WhatsApp Cloud API (Meta). Business-initiated messages must use a template
 * Meta approved; ours ("academy_notification") takes the title and the body
 * as its two body parameters, so one approved template carries every
 * notification in each language it was approved in. Codes go by a second,
 * authentication-category template ("otp_template", with Meta's copy-code
 * button): the code is the body's one parameter and the button's.
 */
final class MetaCloudWhatsAppGateway implements WhatsAppGateway
{
    /** @param  array{token: ?string, phone_number_id: ?string, api_version: string, template: string, otp_template?: string}  $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'meta';
    }

    public function send(string $phoneE164, string $title, string $body, string $locale): string
    {
        return $this->template($phoneE164, $this->config['template'], $locale, [[
            'type' => 'body',
            'parameters' => [['type' => 'text', 'text' => $title], ['type' => 'text', 'text' => $body]],
        ]]);
    }

    public function sendCode(string $phoneE164, string $code, string $locale): string
    {
        return $this->template($phoneE164, $this->config['otp_template'] ?? 'sign_in_code', $locale, [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
        ]);
    }

    /** @param  list<array<string, mixed>>  $components */
    private function template(string $phoneE164, string $name, string $locale, array $components): string
    {
        if (blank($this->config['token']) || blank($this->config['phone_number_id'])) {
            throw new RuntimeException('WhatsApp (Meta) is not configured.');
        }

        $response = Http::withToken($this->config['token'])->acceptJson()->timeout(15)
            ->post("https://graph.facebook.com/{$this->config['api_version']}/{$this->config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($phoneE164, '+'),
                'type' => 'template',
                'template' => ['name' => $name, 'language' => ['code' => $locale], 'components' => $components],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp (Meta) refused the message: '.($response->json('error.message') ?? $response->status()));
        }

        return (string) $response->json('messages.0.id');
    }
}
