<?php

namespace Modules\SharedKernel\Time;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who saves a person's own zone, by the kind of account: each module that
 * owns accounts adds its own in its provider (Identity for users, Platform
 * for the console team). An account no module took cannot change it here.
 */
final class OwnTimezones
{
    /** @var array<class-string, class-string<OwnTimezone>> */
    private array $savers = [];

    /**
     * @param  class-string  $account
     * @param  class-string<OwnTimezone>  $saver
     */
    public function add(string $account, string $saver): void
    {
        $this->savers[$account] = $saver;
    }

    public function for(?Authenticatable $user): ?OwnTimezone
    {
        $saver = $user === null ? null : ($this->savers[$user::class] ?? null);

        return $saver === null ? null : app($saver);
    }
}
