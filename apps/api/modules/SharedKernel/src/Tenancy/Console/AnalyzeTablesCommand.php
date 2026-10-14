<?php

namespace Modules\SharedKernel\Tenancy\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Refreshes Postgres' statistics of every table, as their owner. Autovacuum analyses a
 * table only after enough of it changed, so small ones (subjects, settings, plans) can
 * keep no statistics at all and queries joining them get poor plans. Deploys run it
 * right after the migrations (infra/deploy/entrypoint.sh, the migrate part).
 */
final class AnalyzeTablesCommand extends Command
{
    protected $signature = 'db:analyze';

    protected $description = "Refresh Postgres' statistics of every table (deploys run it after the migrations)";

    public function handle(): int
    {
        $started = microtime(true);
        DB::connection('pgsql_owner')->statement('ANALYZE');
        $this->info(sprintf('Statistics refreshed in %.1f s.', microtime(true) - $started));

        return self::SUCCESS;
    }
}
