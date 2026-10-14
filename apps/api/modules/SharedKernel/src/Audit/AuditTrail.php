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
     * An erased account's name out of the academy's log (the account's erasure calls it, inside
     * its transaction): where the person acted, their name becomes $as and their address and
     * browser go; any value that was their name becomes $as, in entries about them (as actor,
     * subject or the one signed in as) and, unless another account goes by the same name
     * ($shared), in any other entry. What was done, when and by which account stays.
     *
     * @return int the entries changed
     */
    public function forget(string $userId, string $name, string $as, bool $shared = false): int
    {
        $name = trim($name);
        $rows = DB::table('audit_logs')
            ->where('academy_id', $this->tenant->require()->id)
            ->where(function ($q) use ($userId, $name, $shared) {
                $q->where(fn ($q) => $q->where('actor_type', 'user')->where('actor_id', $userId))
                    ->orWhere('on_behalf_of', $userId)
                    ->orWhere('subject_id', $userId);
                if (! $shared && $name !== '') {
                    $q->orWhereRaw("jsonb_path_exists(data, '$.** ? (@ == \$n)', jsonb_build_object('n', ?::text))", [$name]);
                }
            })
            ->get(['id', 'actor_type', 'actor_id', 'actor_name', 'ip', 'user_agent', 'data']);
        if ($rows->isEmpty()) {
            return 0;
        }

        // The log lets this through only while the erasure says so, for its own transaction.
        return DB::transaction(function () use ($rows, $userId, $name, $as): int {
            DB::statement("select set_config('lms.audit_forget', 'on', true)");
            $changed = 0;
            foreach ($rows as $row) {
                $data = json_decode((string) $row->data, true) ?: [];
                $update = [];
                if ($name !== '') {
                    $clean = self::replace($data, $name, $as);
                    if ($clean !== $data) {
                        $update['data'] = json_encode($clean, JSON_UNESCAPED_UNICODE);
                    }
                }
                $actor = ['actor_name' => mb_substr($as, 0, 120), 'ip' => null, 'user_agent' => null];
                if ($row->actor_type === 'user' && $row->actor_id === $userId && [$row->actor_name, $row->ip, $row->user_agent] !== array_values($actor)) {
                    $update += $actor;
                }
                if ($update !== []) {
                    $changed += DB::table('audit_logs')->where('id', $row->id)->update($update);
                }
            }
            DB::statement("select set_config('lms.audit_forget', 'off', true)");

            return $changed;
        });
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function replace(array $data, string $name, string $as): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::replace($value, $name, $as);
            } elseif ($value === $name) {
                $data[$key] = $as;
            }
        }

        return $data;
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
