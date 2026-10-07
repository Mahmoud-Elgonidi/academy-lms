<?php

namespace Modules\SharedKernel\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Writes a sensitive action to the academy's audit log (docs/architecture/01:
 * "سجل مراجعة لكل عملية حساسة"), by whoever AuditActor says. Keep the data
 * to what a reader needs to understand the change: never a secret, a key,
 * a password or a token (those keys are dropped anyway).
 */
final class AuditTrail
{
    private const SECRET = '/secret|password|token|api_?key|private|signature/i';

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  ?AuditActor  $as  who did it, when not whoever the request says (AuditActor::current())
     */
    public function record(string $action, ?string $subjectType = null, ?string $subjectId = null, array $data = [], ?AuditActor $as = null): void
    {
        DB::table('audit_logs')->insert(self::row($this->tenant->require()->id, $as ?? AuditActor::current(), $action, $subjectType, $subjectId, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function row(?string $academyId, AuditActor $actor, string $action, ?string $subjectType, ?string $subjectId, array $data): array
    {
        $request = app()->runningInConsole() ? null : request();

        return [
            'id' => (string) Str::uuid7(),
            'academy_id' => $academyId,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name === null ? null : mb_substr($actor->name, 0, 120),
            'on_behalf_of' => $actor->onBehalfOf,
            'impersonation_id' => $actor->impersonationId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'data' => json_encode(self::clean($data), JSON_UNESCAPED_UNICODE),
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'created_at' => CarbonImmutable::now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function clean(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET, $key)) {
                continue;
            }
            $out[$key] = is_array($value) ? self::clean($value) : $value;
        }

        return $out;
    }
}
