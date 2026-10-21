<?php

namespace Modules\Platform\Settings\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Where the platform's own Ollama server answers: no name and password in the
 * address, and https unless the server is on a private network (this
 * machine, a container's name, 10/8, 172.16/12, 192.168/16, a private IPv6,
 * *.internal or *.local): over the internet a prompt never travels in clear.
 */
final class OllamaAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = parse_url((string) $value);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return;     // "url" says what is wrong with it
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail(__('platform::settings.errors.ollama_credentials'));

            return;
        }
        if (strtolower($parts['scheme']) === 'http' && ! self::private(strtolower(trim($parts['host'], '[]')))) {
            $fail(__('platform::settings.errors.ollama_https'));
        }
    }

    public static function private(string $host): bool
    {
        if (in_array($host, ['localhost', 'host.docker.internal'], true) || ! str_contains($host, '.') && ! str_contains($host, ':')) {
            return true;
        }
        if (str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // A private or loopback address: what PHP calls "not public".
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            && ! str_starts_with($host, '169.254.');
    }
}
