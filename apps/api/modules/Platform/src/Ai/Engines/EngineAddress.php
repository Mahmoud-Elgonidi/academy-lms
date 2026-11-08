<?php

namespace Modules\Platform\Ai\Engines;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Platform\Settings\Rules\OllamaAddress;

/**
 * Where an engine answers: no name and password in the address (a key goes
 * in its own field), and https unless the server is on a private network
 * (as OllamaAddress): over the internet a prompt never travels in clear.
 */
final class EngineAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = parse_url((string) $value);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return;     // "url" says what is wrong with it
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail(__('platform::engines.errors.credentials'));

            return;
        }
        if (strtolower($parts['scheme']) === 'http' && ! OllamaAddress::private(strtolower(trim($parts['host'], '[]')))) {
            $fail(__('platform::engines.errors.https'));
        }
    }
}
