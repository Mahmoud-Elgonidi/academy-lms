<?php

/*
 * Module boundaries (docs/architecture/04-design-patterns.md, section 2):
 * modules talk to each other through Contracts only, the dependency graph has
 * no cycles, and only platform code may use the BYPASSRLS connection.
 */

const MODULES_ABOVE_KERNEL = ['Modules\Platform', 'Modules\Identity', 'Modules\Catalog', 'Modules\People', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Scheduling', 'App'];

arch('the shared kernel depends on no module')
    ->expect('Modules\SharedKernel')
    ->not->toUse(MODULES_ABOVE_KERNEL);

arch('platform depends on no other module')
    ->expect('Modules\Platform')
    ->not->toUse(array_values(array_diff(MODULES_ABOVE_KERNEL, ['Modules\Platform'])));

arch('credits depends only on the shared kernel')
    ->expect('Modules\Credits')
    ->not->toUse(array_values(array_diff(MODULES_ABOVE_KERNEL, ['Modules\Credits'])));

arch('enrollment reaches people and catalog through contracts, and knows nothing of billing or scheduling')
    ->expect('Modules\Enrollment')
    ->not->toUse([
        'Modules\Platform', 'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Catalog\Models', 'Modules\Catalog\Actions', 'Modules\Credits', 'Modules\Billing', 'Modules\Scheduling', 'App',
    ]);

arch('billing reaches enrollment and credits only through their contracts, and knows nothing of scheduling')
    ->expect('Modules\Billing')
    ->not->toUse([
        'Modules\Credits\Models', 'Modules\Credits\Actions', 'Modules\Credits\Domain',
        'Modules\Enrollment\Models', 'Modules\Enrollment\Actions',
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Scheduling', 'App',
    ]);

arch('identity reaches platform only through its contracts, and knows nothing above it')
    ->expect('Modules\Identity')
    ->not->toUse([
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'Modules\Platform\Branding',
        'Modules\Catalog', 'Modules\People', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Scheduling', 'App',
    ]);

arch('catalog depends only on the shared kernel')
    ->expect('Modules\Catalog')
    ->not->toUse(array_values(array_diff(MODULES_ABOVE_KERNEL, ['Modules\Catalog'])));

arch('people reaches identity and catalog only through their contracts')
    ->expect('Modules\People')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Preferences',
        'Modules\Catalog\Models', 'Modules\Catalog\Actions',
        'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Scheduling', 'App',
    ]);

arch('scheduling reaches other modules only through their contracts')
    ->expect('Modules\Scheduling')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Preferences', 'Modules\Identity\Authorization',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'Modules\Platform\Branding',
        'Modules\Catalog\Models', 'Modules\Catalog\Actions',
        'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Credits\Models', 'Modules\Credits\Actions', 'Modules\Credits\Domain',
        'Modules\Enrollment\Models', 'Modules\Enrollment\Actions',
        'Modules\Billing\Models', 'Modules\Billing\Actions', 'Modules\Billing\Domain',
        'App',
    ]);

arch('the app layer uses modules only through contracts, plugins and the shared kernel')
    ->expect(['App', 'Database\Seeders'])
    ->not->toUse([
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory',
        'Modules\Identity\Models', 'Modules\Identity\Actions',
        'Modules\Catalog\Models', 'Modules\Catalog\Actions',
        'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Scheduling\Domain', 'Modules\Scheduling\Actions',
        'Modules\Credits\Models', 'Modules\Credits\Actions',
        'Modules\Enrollment\Models', 'Modules\Enrollment\Actions',
        'Modules\Billing\Models', 'Modules\Billing\Actions',
    ]);

arch('actions and models are final')
    ->expect([
        'Modules\Platform\Actions', 'Modules\Identity\Actions', 'Modules\Catalog\Actions', 'Modules\People\Actions', 'Modules\Scheduling\Actions',
        'Modules\Credits\Actions', 'Modules\Enrollment\Actions', 'Modules\Billing\Actions',
        'Modules\Platform\Models', 'Modules\Identity\Models', 'Modules\Catalog\Models', 'Modules\People\Models', 'Modules\Scheduling\Domain\Models',
        'Modules\Credits\Models', 'Modules\Enrollment\Models', 'Modules\Billing\Models',
    ])
    ->toBeFinal();

arch('shift, slot and matching rules are pure: dates, time zones and domain types only')
    ->expect(['Modules\\Scheduling\\Domain\\Shifts', 'Modules\\Scheduling\\Domain\\Availability', 'Modules\\Scheduling\\Domain\\Slots'])
    ->toOnlyUse(['Carbon\\CarbonImmutable', 'Modules\\Scheduling\\Domain', 'Modules\\SharedKernel\\Time\\LocalTimeResolver', 'InvalidArgumentException']);

arch('the cancellation policy is pure: rules, numbers and decisions only')
    ->expect('Modules\Scheduling\Domain\Policy')
    ->toOnlyUse(['Modules\Scheduling\Domain', 'Closure', 'LogicException', 'InvalidArgumentException']);

arch('scheduling reads other modules\' tables only through read models that refuse writes')
    ->expect('Modules\Scheduling\Queries\Models')
    ->toHaveMethod('save');

arch('credit allocation and tax rules are pure: no database, no framework')
    ->expect(['Modules\Credits\Domain', 'Modules\Billing\Domain'])
    ->toOnlyUse(['Carbon\CarbonImmutable', 'Modules\Credits\Domain', 'Modules\Billing\Domain', 'InvalidArgumentException']);

arch('the live board rules are pure: dates and domain types only, no database or HTTP')
    ->expect('Modules\Scheduling\Domain\LiveBoard')
    ->toOnlyUse(['Carbon\CarbonImmutable', 'Modules\Scheduling\Domain', 'app']);

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
