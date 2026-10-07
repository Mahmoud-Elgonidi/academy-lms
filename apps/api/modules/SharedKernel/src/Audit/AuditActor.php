<?php

namespace Modules\SharedKernel\Audit;

use Illuminate\Support\Facades\Context;

/**
 * Who an audit entry is by: someone of the academy, the platform team (on
 * its own, or signed in as a user, with that user and the sign-in), or the
 * system. The request says so through Laravel's context; otherwise it is
 * whoever is signed in on the web, else the system.
 */
final readonly class AuditActor
{
    public const CONTEXT_KEY = 'audit_actor';

    public function __construct(
        public string $type,
        public ?string $id,
        public ?string $name,
        public ?string $onBehalfOf = null,
        public ?string $impersonationId = null,
    ) {}

    public static function current(): self
    {
        $given = Context::getHidden(self::CONTEXT_KEY);
        if (is_array($given)) {
            return new self($given['type'], $given['id'] ?? null, $given['name'] ?? null, $given['on_behalf_of'] ?? null, $given['impersonation_id'] ?? null);
        }
        $user = auth()->guard('web')->user();

        return $user ? new self('user', (string) $user->getAuthIdentifier(), $user->name ?? null) : new self('system', null, null);
    }

    /** From here on, entries in this request or job are by $actor. */
    public static function use(self $actor): void
    {
        Context::addHidden(self::CONTEXT_KEY, [
            'type' => $actor->type, 'id' => $actor->id, 'name' => $actor->name,
            'on_behalf_of' => $actor->onBehalfOf, 'impersonation_id' => $actor->impersonationId,
        ]);
    }

    public static function forget(): void
    {
        Context::forgetHidden(self::CONTEXT_KEY);
    }
}
