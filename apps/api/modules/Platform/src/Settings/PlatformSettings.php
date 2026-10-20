<?php

namespace Modules\Platform\Settings;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The platform's settings as an owner set them in the console, put into the
 * running config in place of the .env values of the same meaning.
 *
 * Applied at the start of every request, job and command (never while the
 * app boots: `config:cache` would then write the keys out in plain text),
 * and again whenever they change. The cache holds them as the table does,
 * encrypted; they are read in plain text only inside the process that uses
 * them. With no table or cache to read, nothing changes: the server runs on
 * its .env, as before.
 */
final class PlatformSettings
{
    public const CACHE_KEY = 'platform:settings';

    private ?string $version = null;

    /** @var array<string, mixed> config path => what it held before this process set it */
    private array $original = [];

    public function apply(): void
    {
        try {
            $entry = $this->entry();
        } catch (Throwable) {
            return;
        }
        if ($entry['version'] === $this->version) {
            return;
        }

        $wanted = [];
        foreach ($this->decrypt($entry['values']) as $key => $value) {
            foreach (SettingCatalog::get($key)?->paths ?? [] as $path) {
                $wanted[$path] = SettingCatalog::get($key)->toConfig($value);
            }
        }
        $changed = false;
        // What this process set before and the screen no longer has goes back to the server's own.
        foreach (array_diff_key($this->original, $wanted) as $path => $value) {
            config([$path => $value]);
            unset($this->original[$path]);
            $changed = true;
        }
        foreach ($wanted as $path => $value) {
            if (! array_key_exists($path, $this->original)) {
                $this->original[$path] = config($path);
            }
            $changed = $changed || config($path) !== $value;
            config([$path => $value]);
        }
        $this->version = $entry['version'];

        // A mailer made before keeps its old server and sender.
        if ($changed && app()->resolved('mail.manager') && method_exists(app('mail.manager'), 'forgetMailers')) {
            app('mail.manager')->forgetMailers();
        }
    }

    /**
     * Runs $try with values typed on the screen and not saved (checked as a
     * save would check them), in this process only, and puts the config back
     * after: "Try it" before saving. A value left empty, or a key ticked to
     * remove, is tried as the server's own.
     *
     * @template T
     *
     * @param  array<string, mixed>  $values  setting key => value as typed
     * @param  list<string>  $clear
     * @param  \Closure(): T  $try
     * @return T
     */
    public function trying(array $values, array $clear, \Closure $try): mixed
    {
        $next = SettingValues::next($values, $clear);
        SettingValues::validate($next);

        $before = [];
        foreach ($next as $key => $value) {
            $setting = SettingCatalog::get($key);
            foreach ($setting->paths as $path) {
                $before[$path] ??= config($path);
                config([$path => $value === null ? $this->serverValue($setting) : $setting->toConfig($value)]);
            }
        }
        $this->forgetMailers();
        try {
            return $try();
        } finally {
            foreach ($before as $path => $value) {
                config([$path => $value]);
            }
            $this->forgetMailers();
        }
    }

    private function forgetMailers(): void
    {
        if (app()->resolved('mail.manager') && method_exists(app('mail.manager'), 'forgetMailers')) {
            app('mail.manager')->forgetMailers();
        }
    }

    /** Commands that write the config, routes or views out, or run before there is a database: never with the screen's values. */
    public static function leavesAlone(string $command): bool
    {
        return in_array($command, ['config:cache', 'optimize', 'package:discover', 'event:cache', 'route:cache', 'view:cache', 'key:generate'], true)
            || str_starts_with($command, 'filament:');
    }

    /** Called once the table changed: every process reads it again on its next request, job or command. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * What a setting holds on the server itself (its .env line, or the code's default), whatever the screen says.
     */
    public function serverValue(SettingDefinition $setting): mixed
    {
        $path = $setting->paths[0];

        return array_key_exists($path, $this->original) ? $this->original[$path] : config($path);
    }

    /**
     * The saved values, readable (only for the owners' screen and SavePlatformSettings).
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->decrypt($this->rows()->pluck('value', 'key')->all());
    }

    /**
     * Who saved each value and when.
     *
     * @return array<string, array{by: ?string, at: string}>
     */
    public function saved(): array
    {
        return $this->rows()->mapWithKeys(fn (object $row) => [$row->key => ['by' => $row->updated_by, 'at' => (string) $row->updated_at]])->all();
    }

    /** @return array{version: string, values: array<string, string>} */
    private function entry(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['version'], $cached['values'])) {
            return $cached;
        }
        $values = $this->rows()->pluck('value', 'key')->all();
        ksort($values);
        $entry = ['version' => sha1(serialize($values)), 'values' => $values];
        Cache::forever(self::CACHE_KEY, $entry);

        return $entry;
    }

    /** @return Collection<int, object> */
    private function rows()
    {
        return DB::connection('pgsql_platform')->table('platform_settings')->orderBy('key')->get(['key', 'value', 'updated_by', 'updated_at']);
    }

    /**
     * @param  array<string, string>  $values  encrypted
     * @return array<string, string>
     */
    private function decrypt(array $values): array
    {
        $plain = [];
        foreach ($values as $key => $value) {
            if (SettingCatalog::get($key) === null) {
                continue;
            }
            try {
                $plain[$key] = Crypt::decryptString($value);
            } catch (DecryptException) {
                // Saved under another APP_KEY: as if not set, so the server's own value holds.
            }
        }

        return $plain;
    }
}
