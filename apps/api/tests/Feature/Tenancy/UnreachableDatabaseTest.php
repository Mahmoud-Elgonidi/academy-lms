<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * A database that does not answer: the tenant setting written on each new
 * connection fails, Laravel reconnects and announces the connection again.
 * That must end in one error, not in reconnecting until memory runs out.
 */

it('ends in one error when the database does not answer', function () {
    $default = config('database.default');
    config(['database.connections.unreachable' => ['port' => 1] + config("database.connections.{$default}"), 'database.default' => 'unreachable']);

    try {
        expect(fn () => DB::connection('unreachable')->select('select 1'))->toThrow(QueryException::class);
    } finally {
        DB::purge('unreachable');
        config(['database.default' => $default]);
    }
});
