<?php

namespace Modules\Billing\Actions\Payments;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Payments\GatewayManager;

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
        $data['currencies'] = array_values(array_unique(array_map('strtoupper', array_filter((array) ($data['currencies'] ?? [])))));
        $takes = GatewayManager::currencies($gateway);

        Validator::make(['gateway' => $gateway] + $data, [
            'gateway' => ['required', Rule::in($this->gateways->gateways())],
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
        $key = $credentials['secret_key'] ?? '';
        $mode = $gateway === 'demo' ? 'test' : $data['mode'];
        if ($key !== '' && preg_match('/^(sk|rk)_(test|live)_/', $key, $m) && $m[2] !== $mode) {
            $errors['secret_key'] = __('billing::payments.errors.key_mode.'.$mode);
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

        return $account;
    }
}
