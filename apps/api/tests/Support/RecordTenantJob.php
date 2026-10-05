<?php

namespace Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Tenancy\TenantContext;

/** Records what a queued job sees, to prove the tenant travels with it. */
final class RecordTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @var array<string, mixed>|null */
    public static ?array $seen = null;

    public function handle(TenantContext $tenants): void
    {
        self::$seen = [
            'tenant' => $tenants->id(),
            'pg_setting' => DB::selectOne("select current_setting('app.tenant_id', true) as v")->v,
            'visible_users' => User::query()->count(),
            'raw_visible_users' => DB::table('users')->count(),
        ];
    }
}
