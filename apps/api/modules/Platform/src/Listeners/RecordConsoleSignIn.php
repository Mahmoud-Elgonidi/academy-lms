<?php

namespace Modules\Platform\Listeners;

use Illuminate\Auth\Events\Login;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;

/** A platform admin signing in to the console is in the platform's audit log, with when they last did. */
final class RecordConsoleSignIn
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(Login $event): void
    {
        if ($event->guard !== 'platform' || ! $event->user instanceof PlatformAdmin) {
            return;
        }
        $event->user->forceFill(['last_login_at' => now()])->save();
        AuditActor::use(new AuditActor('platform', $event->user->id, $event->user->name));
        $this->audit->record(null, 'platform.signed_in', 'platform_admin', $event->user->id);
    }
}
