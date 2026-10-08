<?php

namespace Modules\Identity\Queries;

use Carbon\CarbonImmutable;
use Modules\Identity\Models\SocialIdentity;
use Modules\Identity\Models\User;
use Modules\Identity\Social\Provider;

/** The Google and Apple sign-ins on offer, and which of them an account is linked to (My settings, the API). */
final class SocialAccounts
{
    /**
     * @param  bool  $web  the providers on for the web (the panels, the portal); else on for the web or the apps
     * @return list<array{provider: Provider, linked: bool, email: ?string, linked_at: ?CarbonImmutable, last_used_at: ?CarbonImmutable}>
     */
    public function of(User $user, bool $web = true): array
    {
        $linked = SocialIdentity::query()->where('user_id', $user->id)->get()->keyBy(fn (SocialIdentity $i) => $i->provider->value);
        $providers = array_filter(Provider::cases(), fn (Provider $p) => $p->onForWeb() || (! $web && $p->onForApps()));

        return array_values(array_map(fn (Provider $p) => [
            'provider' => $p,
            'linked' => isset($linked[$p->value]),
            'email' => $linked[$p->value]?->email ?? null,
            'linked_at' => $linked[$p->value]?->linked_at ?? null,
            'last_used_at' => $linked[$p->value]?->last_used_at ?? null,
        ], $providers));
    }
}
