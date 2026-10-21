<?php

namespace Modules\Platform\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Settings\PlatformSettings;
use Modules\Platform\Settings\SettingCatalog;
use Modules\Platform\Settings\SettingValues;

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
    public const JSON_MAX = SettingValues::JSON_MAX;

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

        $next = SettingValues::next($values, $clear);
        SettingValues::validate($next);

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
}
