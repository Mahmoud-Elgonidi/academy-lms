<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Games\StarterCatalog;
use Modules\SharedKernel\Games\GameCatalog;

/**
 * The platform's starter world (five islands, their creatures, four live
 * games), as the migration installs it. Every test starts from truncated
 * tables, so a test that plays puts it back first.
 */
trait StarterGames
{
    protected function installStarterGames(): void
    {
        StarterCatalog::install(DB::connection('pgsql_platform'));
        app()->forgetInstance(GameCatalog::class);
    }
}
