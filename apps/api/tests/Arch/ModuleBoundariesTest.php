<?php

/*
 * Module boundaries (docs/architecture/04-design-patterns.md, section 2):
 * modules talk to each other through Contracts only, the dependency graph has
 * no cycles, and only platform code may use the BYPASSRLS connection.
 */

arch('the shared kernel depends on no module')
    ->expect('Modules\SharedKernel')
    ->not->toUse(['Modules\Platform', 'Modules\Identity', 'App']);

arch('platform depends on no other module')
    ->expect('Modules\Platform')
    ->not->toUse(['Modules\Identity', 'App']);

arch('identity reaches platform only through its contracts')
    ->expect('Modules\Identity')
    ->not->toUse(['Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'App']);

arch('the app layer uses modules only through contracts and the shared kernel')
    ->expect(['App', 'Database\Seeders'])
    ->not->toUse([
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory',
        'Modules\Identity\Models', 'Modules\Identity\Actions',
    ]);

arch('actions and models are final')
    ->expect(['Modules\Platform\Actions', 'Modules\Identity\Actions', 'Modules\Platform\Models', 'Modules\Identity\Models'])
    ->toBeFinal();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

test('only platform code names the BYPASSRLS connection', function () {
    $allowed = ['modules/Platform/', 'config/database.php'];
    $root = dirname(__DIR__, 2);

    $offenders = collect(['app', 'modules', 'config', 'routes', 'database'])
        ->flatMap(fn (string $dir) => iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS))))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->map(fn (SplFileInfo $file) => substr($file->getPathname(), strlen($root) + 1))
        ->reject(fn (string $path) => collect($allowed)->contains(fn (string $prefix) => str_starts_with($path, $prefix)))
        ->filter(fn (string $path) => str_contains(file_get_contents("{$root}/{$path}"), 'pgsql_platform'))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
