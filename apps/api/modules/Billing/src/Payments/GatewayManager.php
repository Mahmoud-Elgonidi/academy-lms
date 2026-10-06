<?php

namespace Modules\Billing\Payments;

use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Payments\Gateways\DemoGateway;
use Modules\Billing\Payments\Gateways\MoyasarGateway;
use Modules\Billing\Payments\Gateways\StripeGateway;
use Modules\Billing\Payments\Gateways\TapGateway;

/**
 * The gateways an academy can connect, what each needs and takes, and the
 * adapter for one of the academy's accounts (its keys decrypted only here).
 */
final class GatewayManager
{
    /**
     * Per gateway: the keys it needs (required or not), the currencies it takes
     * (null: most currencies), and the ways to pay a payer sees on its page.
     */
    public const GATEWAYS = [
        'stripe' => ['fields' => ['secret_key' => true, 'webhook_secret' => true], 'currencies' => null, 'methods' => ['card', 'apple_pay', 'google_pay']],
        'moyasar' => ['fields' => ['secret_key' => true, 'webhook_secret' => true], 'currencies' => ['SAR'], 'methods' => ['mada', 'card', 'apple_pay', 'stc_pay']],
        'tap' => ['fields' => ['secret_key' => true], 'currencies' => ['KWD', 'BHD', 'SAR', 'AED', 'OMR', 'QAR', 'EGP', 'JOD', 'USD', 'EUR', 'GBP'], 'methods' => ['knet', 'benefit', 'mada', 'card', 'apple_pay']],
        'demo' => ['fields' => [], 'currencies' => null, 'methods' => ['card']],
    ];

    /** @return list<string> the gateways an academy may connect here */
    public function gateways(): array
    {
        return array_values(array_filter(array_keys(self::GATEWAYS), fn (string $g) => $g !== 'demo' || $this->demoAllowed()));
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
            'moyasar' => new MoyasarGateway($credentials),
            'tap' => new TapGateway($credentials),
            'demo' => $this->demoAllowed() ? new DemoGateway : throw new GatewayException('The demo gateway is off on this server.'),
            default => throw new GatewayException("Unknown gateway {$account->gateway}."),
        };
    }

    /** @return array<string, bool> key => required */
    public static function fields(string $gateway): array
    {
        return self::GATEWAYS[$gateway]['fields'] ?? [];
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
