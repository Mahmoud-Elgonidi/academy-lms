<?php

namespace Modules\Billing\Actions\Payments;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Payments\GatewayManager;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Connects one of the academy's gateway accounts, or changes it. The keys
 * are kept encrypted and never shown again: a key left blank keeps the one
 * saved. A test key on a live account (or the reverse) is refused.
 */
final class SaveGatewayAccount
{
    public function __construct(private readonly GatewayManager $gateways) {}

    /** @param  array<string, mixed>  $data  gateway, label, mode, currencies, is_active, and the gateway's keys */
    public function handle(?GatewayAccount $account, array $data, ?string $actorId): GatewayAccount
    {
        $gateway = $account->gateway ?? (string) ($data['gateway'] ?? '');
        if ($gateway === 'stripe_connect' && $account !== null) {
            $data['mode'] = $account->mode;   // a connected account's mode is Stripe's to say
        }
        $data['currencies'] = array_values(array_unique(array_map('strtoupper', array_filter((array) ($data['currencies'] ?? [])))));
        $takes = GatewayManager::currencies($gateway);

        Validator::make(['gateway' => $gateway] + $data, [
            'gateway' => ['required', Rule::in($account !== null ? [$account->gateway] : $this->gateways->gateways())],
            'label' => ['required', 'string', 'max:80'],
            'mode' => ['required', Rule::in(['test', 'live'])],
            'currencies' => ['array'],
            'currencies.*' => ['string', 'size:3', ...($takes === null ? [] : [Rule::in($takes)])],
        ], [], [
            'label' => __('billing::payments.fields.label'),
            'mode' => __('billing::payments.fields.mode'),
            'currencies.*' => __('billing::payments.fields.currencies'),
        ])->validate();

        $saved = (array) ($account?->credentials ?? []);
        $credentials = [];
        $errors = [];
        foreach (GatewayManager::fields($gateway) as $field => $required) {
            $value = trim((string) ($data[$field] ?? ''));
            $credentials[$field] = $value !== '' ? $value : ($saved[$field] ?? '');
            if ($required && $credentials[$field] === '') {
                $errors[$field] = __('billing::payments.errors.required', ['field' => __('billing::payments.fields.'.$field)]);
            }
        }
        $mode = $gateway === 'demo' ? 'test' : $data['mode'];
        foreach (['secret_key', 'public_key'] as $field) {
            $key = $credentials[$field] ?? '';
            // Stripe's sk_/rk_, Paymob's egy_sk_/egy_pk_: a test key on a live account, or the reverse, is refused.
            if ($key !== '' && preg_match('/^(?:[a-z]{3}_)?(?:sk|rk|pk)_(test|live)_/', $key, $m) && $m[1] !== $mode) {
                $errors[$field] = __('billing::payments.errors.key_mode.'.$mode);
            }
        }
        if (($credentials['integration_ids'] ?? '') !== '' && ! preg_match('/^\s*\d+(\s*,\s*\d+)*\s*$/', $credentials['integration_ids'])) {
            $errors['integration_ids'] = __('billing::payments.errors.integration_ids');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $account ??= new GatewayAccount(['gateway' => $gateway, 'created_by' => $actorId]);
        $account->fill([
            'label' => $data['label'],
            'mode' => $mode,
            'currencies' => $data['currencies'],
            'credentials' => $credentials,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort' => (int) ($data['sort'] ?? $account->sort ?? 0),
        ])->save();
        app(AuditTrail::class)->record('billing.gateway_saved', 'gateway_account', $account->id, [
            'gateway' => $gateway, 'label' => $account->label, 'mode' => $mode, 'currencies' => $data['currencies'], 'is_active' => $account->is_active,
            'fields_changed' => array_values(array_filter(array_keys(GatewayManager::fields($gateway)), fn (string $f) => trim((string) ($data[$f] ?? '')) !== '' && trim((string) ($data[$f] ?? '')) !== ($saved[$f] ?? null))),
        ]);

        return $account;
    }
}
