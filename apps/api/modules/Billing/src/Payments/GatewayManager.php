<?php

namespace Modules\Billing\Payments;

use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Payments\Gateways\DemoGateway;
use Modules\Billing\Payments\Gateways\MoyasarGateway;
use Modules\Billing\Payments\Gateways\PaymobGateway;
use Modules\Billing\Payments\Gateways\StripeGateway;
use Modules\Billing\Payments\Gateways\TapGateway;

/**
 * The gateways an academy can connect, what each needs and takes, and the
 * adapter for one of the academy's accounts (its keys decrypted only here).
 */
final class GatewayManager
{
    /**
     * Per gateway: the keys it needs (required or not; the ones in "public"
     * are not secrets and may be shown again), the currencies it takes (null:
     * most currencies), and the ways to pay a payer sees on its page.
     */
    public const GATEWAYS = [
        'stripe' => ['fields' => ['secret_key' => true, 'webhook_secret' => true], 'currencies' => null, 'methods' => ['card', 'apple_pay', 'google_pay']],
        // The academy's own Stripe account connected to the platform: no keys, made by connecting (StripeConnect).
        'stripe_connect' => ['fields' => [], 'currencies' => null, 'methods' => ['card', 'apple_pay', 'google_pay'], 'connect' => true],
        'paymob' => [
            'fields' => ['secret_key' => true, 'public_key' => true, 'api_key' => true, 'hmac_secret' => true, 'integration_ids' => true],
            'public' => ['public_key', 'integration_ids'],
            'currencies' => ['EGP'],
            'methods' => ['card', 'wallet'],
        ],
        'moyasar' => ['fields' => ['secret_key' => true, 'webhook_secret' => true], 'currencies' => ['SAR'], 'methods' => ['mada', 'card', 'apple_pay', 'stc_pay']],
        'tap' => ['fields' => ['secret_key' => true], 'currencies' => ['KWD', 'BHD', 'SAR', 'AED', 'OMR', 'QAR', 'EGP', 'JOD', 'USD', 'EUR', 'GBP'], 'methods' => ['knet', 'benefit', 'mada', 'card', 'apple_pay']],
        'demo' => ['fields' => [], 'currencies' => null, 'methods' => ['card']],
    ];

    /** @return list<string> the gateways an academy may add with keys here (a connected Stripe account comes by connecting) */
    public function gateways(): array
    {
        return array_values(array_filter(array_keys(self::GATEWAYS), fn (string $g) => ($g !== 'demo' || $this->demoAllowed()) && empty(self::GATEWAYS[$g]['connect'])));
    }

    /** Whether this server can connect academies' Stripe accounts (the platform's Connect keys are set). */
    public function connectAvailable(): bool
    {
        return filled(config('billing.payments.stripe.platform_secret')) && filled(config('billing.payments.stripe.connect_client_id'));
    }

    public function demoAllowed(): bool
    {
        return (bool) config('billing.payments.demo_gateway');
    }

    public function for(GatewayAccount $account): PaymentGateway
    {
        $credentials = (array) $account->credentials;

        return match ($account->gateway) {
            'stripe' => new StripeGateway($credentials),
            'stripe_connect' => $account->external_id && $this->connectAvailable() ? new StripeGateway([
                'account_id' => $account->external_id,
                'webhook_secret' => (string) config('billing.payments.stripe.connect_webhook_secret'),
                'application_fee_bp' => (int) config('billing.payments.stripe.application_fee_bp'),
                'academy_id' => $account->academy_id,
            ]) : throw new GatewayException('Stripe Connect is not set up on this server.'),
            'moyasar' => new MoyasarGateway($credentials),
            'tap' => new TapGateway($credentials),
            'paymob' => new PaymobGateway($credentials),
            'demo' => $this->demoAllowed() ? new DemoGateway : throw new GatewayException('The demo gateway is off on this server.'),
            default => throw new GatewayException("Unknown gateway {$account->gateway}."),
        };
    }

    /** @return array<string, bool> key => required */
    public static function fields(string $gateway): array
    {
        return self::GATEWAYS[$gateway]['fields'] ?? [];
    }

    /** @return list<string> the keys of a gateway that are not secrets */
    public static function publicFields(string $gateway): array
    {
        return self::GATEWAYS[$gateway]['public'] ?? [];
    }

    /** @return list<string> every key any gateway asks for, in a steady order, for the settings form */
    public static function allFields(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (array $g) => array_keys($g['fields']), array_values(self::GATEWAYS)))));
    }

    /** @return ?list<string> */
    public static function currencies(string $gateway): ?array
    {
        return self::GATEWAYS[$gateway]['currencies'] ?? null;
    }

    /** @return list<string> */
    public static function methods(string $gateway): array
    {
        return self::GATEWAYS[$gateway]['methods'] ?? [];
    }
}
