<?php

namespace Modules\Platform\Console;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The console's own audit entries (an academy suspended, an admin signing
 * in), written across academies on the platform connection; an entry about
 * an academy shows in that academy's log too.
 */
final class PlatformAudit
{
    /** @param  array<string, mixed>  $data */
    public function record(?string $academyId, string $action, ?string $subjectType = null, ?string $subjectId = null, array $data = []): void
    {
        DB::connection('pgsql_platform')->table('audit_logs')->insert(AuditTrail::row($academyId, AuditActor::current(), $action, $subjectType, $subjectId, $data));
    }
}
