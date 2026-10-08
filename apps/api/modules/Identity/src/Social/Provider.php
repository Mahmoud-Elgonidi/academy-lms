<?php

namespace Modules\Identity\Social;

/** Who can vouch for a person's sign-in besides the academy itself. */
enum Provider: string
{
    case Google = 'google';
    case Apple = 'apple';

    public function label(): string
    {
        return __('identity::social.providers.'.$this->value);
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return (array) config('identity.social.'.$this->value, []);
    }

    /** On for the web: the platform has a web client for it. */
    public function onForWeb(): bool
    {
        return filled($this->config()['client_id'] ?? null) && ($this !== self::Google || filled($this->config()['client_secret'] ?? null));
    }

    /** On for the apps: the platform accepts the apps' own clients. */
    public function onForApps(): bool
    {
        return $this->appAudiences() !== [];
    }

    /** @return list<string> the clients an app's token may be for */
    public function appAudiences(): array
    {
        return self::list($this->config()['app_audiences'] ?? null);
    }

    /** @return list<string> */
    public function issuers(): array
    {
        return self::list($this->config()['issuers'] ?? null);
    }

    /** @return list<self> */
    public static function forWeb(): array
    {
        return array_values(array_filter(self::cases(), fn (self $p) => $p->onForWeb()));
    }

    /** @return list<string> */
    private static function list(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', $items), fn (string $item) => $item !== ''));
    }
}
