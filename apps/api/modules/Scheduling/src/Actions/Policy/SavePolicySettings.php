<?php

namespace Modules\Scheduling\Actions\Policy;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Policy\PolicySettings;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;

/** Saves the policy numbers as a new version; earlier decisions keep theirs. */
final class SavePolicySettings
{
    /** @param  array<string, mixed>  $values */
    public function handle(array $values, ?string $actorId): PolicyVersion
    {
        try {
            $settings = PolicySettings::fromArray($values);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['settings' => $e->getMessage()]);
        }

        return DB::transaction(function () use ($settings, $actorId) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['policy:'.app(TenantContext::class)->require()->id]);
            $current = PolicyVersion::query()->orderByDesc('version')->first();
            if ($current !== null && $current->settings == $settings->toArray()) {
                return $current;
            }

            $version = PolicyVersion::query()->create([
                'version' => ($current?->version ?? 0) + 1,
                'settings' => $settings->toArray(),
                'created_by' => $actorId,
            ]);
            $before = (array) ($current?->settings ?? []);
            $changed = array_keys(array_filter($settings->toArray(), fn ($value, $key) => ! array_key_exists($key, $before) || $before[$key] != $value, ARRAY_FILTER_USE_BOTH));
            app(AuditTrail::class)->record('policy.saved', 'policy_version', $version->id, ['version' => $version->version, 'changed' => $changed]);

            return $version;
        });
    }
}
