<?php

namespace Modules\Platform\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Settings\PlatformSettings;
use Modules\Platform\Settings\SettingCatalog;
use Modules\Platform\Settings\SettingDefinition;

/**
 * Saves the platform's settings from the console (owners only). A value
 * given is checked by its setting's rules and kept encrypted; a blank one is
 * no longer set here (the server's .env holds again), except a key or file,
 * whose blank keeps what is saved: those go only when cleared. The Firebase
 * file must be a service account's. Audited with the settings' names alone,
 * never a value; every process takes the new values on its next request,
 * job or command.
 */
final class SavePlatformSettings
{
    /** The largest Firebase service account file taken, in characters. */
    public const JSON_MAX = 20000;

    public function __construct(private readonly PlatformSettings $settings, private readonly PlatformAudit $audit) {}

    /**
     * @param  array<string, mixed>  $values  setting key => value (blank: not set here; a key's or file's blank keeps it)
     * @param  list<string>  $clear  keys and files to remove
     * @return list<string> the settings that changed
     */
    public function handle(PlatformAdmin $by, array $values, array $clear = []): array
    {
        if (! $by->is_owner || ! $by->is_active) {
            throw new AuthorizationException(__('platform::settings.owners_only'));
        }

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
        $this->validate($next);

        $saved = $this->settings->values();
        // The Firebase project comes with its file when nothing names it.
        if (filled($next['fcm_credentials'] ?? null) && blank($next['fcm_project_id'] ?? $saved['fcm_project_id'] ?? null)
            && blank($this->settings->serverValue(SettingCatalog::get('fcm_project_id')))) {
            $next['fcm_project_id'] = (string) json_decode($next['fcm_credentials'], true)['project_id'];
        }

        $changed = array_keys(array_filter($next, fn (?string $value, string $key) => $value !== ($saved[$key] ?? null), ARRAY_FILTER_USE_BOTH));
        if ($changed === []) {
            return [];
        }

        DB::connection('pgsql_platform')->transaction(function () use ($next, $changed, $by) {
            $table = DB::connection('pgsql_platform')->table('platform_settings');
            foreach ($changed as $key) {
                if ($next[$key] === null) {
                    $table->clone()->where('key', $key)->delete();
                } else {
                    $table->clone()->upsert(
                        ['key' => $key, 'value' => Crypt::encryptString($next[$key]), 'updated_by' => $by->id, 'updated_at' => now()],
                        ['key'],
                        ['value', 'updated_by', 'updated_at'],
                    );
                }
            }
            $sections = array_values(array_unique(array_map(fn (string $key) => SettingCatalog::get($key)->group, $changed)));
            $this->audit->record(null, 'platform.settings_saved', 'platform_settings', null, ['sections' => $sections, 'keys' => $changed]);
        });
        $this->settings->forget();
        $this->settings->apply();

        return $changed;
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

    /** @param  array<string, ?string>  $next */
    private function validate(array $next): void
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
