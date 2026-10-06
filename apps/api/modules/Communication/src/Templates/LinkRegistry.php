<?php

namespace Modules\Communication\Templates;

use Closure;
use Modules\Communication\Contracts\NotificationLinks;

final class LinkRegistry implements NotificationLinks
{
    /** @var array<string, Closure> */
    private array $resolvers = [];

    public function register(string $screen, Closure $resolver): void
    {
        $this->resolvers[$screen] = $resolver;
    }

    public function resolve(array $action, string $panel): ?string
    {
        $resolver = $this->resolvers[$action['screen'] ?? ''] ?? null;

        return $resolver === null ? null : $resolver($action, $panel);
    }
}
