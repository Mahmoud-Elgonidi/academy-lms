<?php

namespace Modules\Platform\Settings;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The platform settings' values as they are kept and checked, for saving
 * (SavePlatformSettings) and for trying what is typed before it is saved
 * (PlatformSettings::trying): a blank one is no longer set here, except a
 * key's or a file's, which goes only when cleared.
 */
final class SettingValues
{
    /** The largest Firebase service account file taken, in characters. */
    public const JSON_MAX = 20000;

    /**
     * @param  array<string, mixed>  $values  setting key => value as given
     * @param  list<string>  $clear  keys and files to remove
     * @return array<string, ?string> key => the value to keep (null: no longer set here)
     */
    public static function next(array $values, array $clear = []): array
    {
        $next = [];
        foreach (SettingCatalog::all() as $key => $setting) {
            if ($setting->secret() && in_array($key, $clear, true)) {
                $next[$key] = null;

                continue;
            }
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $value = self::normalize($setting, $values[$key]);
            if ($value !== '') {
                $next[$key] = $value;
            } elseif (! $setting->secret()) {
                $next[$key] = null;
            }
        }

        return $next;
    }

    /** A value as it is kept: trimmed text, a list joined by commas, a toggle as 1 or 0. */
    public static function normalize(SettingDefinition $setting, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($setting->type) {
            'toggle' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null ? (string) $value : (filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0'),
            'list' => implode(',', SettingDefinition::list(is_array($value) ? implode(',', $value) : (string) $value)),
            default => trim((string) $value),
        };
    }

    /**
     * Checks each value given by its setting's rules (null: no longer set here).
     *
     * @param  array<string, ?string>  $next
     */
    public static function validate(array $next): void
    {
        $data = [];
        $rules = [];
        $names = [];
        foreach ($next as $key => $value) {
            if ($value === null) {
                continue;
            }
            $setting = SettingCatalog::get($key);
            $names[$key] = __("platform::settings.keys.{$key}.label");
            if ($setting->type === 'list') {
                $data[$key] = SettingDefinition::list($value);
                $rules[$key] = ['array', 'max:20'];
                $rules[$key.'.*'] = $setting->rules;
                $names[$key.'.*'] = $names[$key];

                continue;
            }
            $data[$key] = $value;
            $rules[$key] = match ($setting->type) {
                'select' => [Rule::in($setting->options)],
                'toggle' => ['in:0,1'],
                'int' => $setting->rules,
                'json_file' => ['string', 'max:'.self::JSON_MAX, function (string $attribute, mixed $value, \Closure $fail) {
                    if (! self::serviceAccount((string) $value)) {
                        $fail(__('platform::settings.errors.fcm_file'));
                    }
                }],
                default => ['string', ...$setting->rules],
            };
        }

        Validator::make($data, $rules, [], $names)->validate();
    }

    /** Whether a text is a Google service account's key file (what Firebase hands out). */
    public static function serviceAccount(string $json): bool
    {
        $account = json_decode($json, true);

        return is_array($account)
            && ($account['type'] ?? null) === 'service_account'
            && is_string($account['project_id'] ?? null) && preg_match('/^[a-z0-9\-]{4,40}$/', $account['project_id']) === 1
            && filter_var($account['client_email'] ?? null, FILTER_VALIDATE_EMAIL) !== false
            && is_string($account['private_key'] ?? null) && str_contains($account['private_key'], 'PRIVATE KEY');
    }
}
