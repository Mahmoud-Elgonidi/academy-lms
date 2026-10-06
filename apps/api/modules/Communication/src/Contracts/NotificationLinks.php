<?php

namespace Modules\Communication\Contracts;

use Closure;

/**
 * Where a notification opens in the web panels. Modules register their
 * screens ("shift_proposal", "write_report"…) with a resolver that gets the
 * message's action and the panel id ("academy" or "teach") and returns a URL,
 * or null when that panel has nothing to show. The apps use the action as is.
 */
interface NotificationLinks
{
    /** @param  Closure(array<string, string>, string): ?string  $resolver */
    public function register(string $screen, Closure $resolver): void;

    /** @param  array<string, string>  $action */
    public function resolve(array $action, string $panel): ?string;
}
