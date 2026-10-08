<?php

/*
 * Module boundaries (docs/architecture/04-design-patterns.md, section 2):
 * modules talk to each other through Contracts only, the dependency graph has
 * no cycles, and only platform code may use the BYPASSRLS connection.
 */

const MODULES_ABOVE_KERNEL = ['Modules\Platform', 'Modules\Identity', 'Modules\Communication', 'Modules\Catalog', 'Modules\People', 'Modules\Curriculum', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Scheduling', 'Modules\Learning', 'Modules\Classroom', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import', 'Modules\Support', 'App'];

arch('the shared kernel depends on no module')
    ->expect('Modules\SharedKernel')
    ->not->toUse(MODULES_ABOVE_KERNEL);

arch('platform depends on no other module')
    ->expect('Modules\Platform')
    ->not->toUse(array_values(array_diff(MODULES_ABOVE_KERNEL, ['Modules\Platform'])));

arch('credits depends only on the shared kernel')
    ->expect('Modules\Credits')
    ->not->toUse(array_values(array_diff(MODULES_ABOVE_KERNEL, ['Modules\Credits'])));

arch('enrollment reaches people, catalog and curriculum through contracts, and knows nothing of billing, scheduling or learning')
    ->expect('Modules\Enrollment')
    ->not->toUse([
        'Modules\Platform', 'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Catalog\Models', 'Modules\Catalog\Actions', 'Modules\Curriculum\Models', 'Modules\Curriculum\Actions',
        'Modules\Credits', 'Modules\Billing', 'Modules\Scheduling', 'Modules\Learning', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import', 'App',
    ]);

arch('curriculum knows only catalog\'s contracts and the shared kernel')
    ->expect('Modules\Curriculum')
    ->not->toUse(array_values(array_diff(MODULES_ABOVE_KERNEL, ['Modules\Curriculum', 'Modules\Catalog'])))
    ->and('Modules\Curriculum')
    ->not->toUse(['Modules\Catalog\Models', 'Modules\Catalog\Actions']);

arch('learning reaches other modules only through their contracts, and nothing depends on it')
    ->expect('Modules\Learning')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Preferences', 'Modules\Identity\Authorization',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory',
        'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Curriculum\Models', 'Modules\Curriculum\Actions',
        'Modules\Enrollment\Models', 'Modules\Enrollment\Actions',
        'Modules\Scheduling\Domain', 'Modules\Scheduling\Actions', 'Modules\Scheduling\Queries',
        'Modules\Credits', 'Modules\Billing', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import', 'App',
    ]);

arch('progress, mastery and today\'s plan are pure: dates, curriculum DTOs and domain types only')
    ->expect('Modules\Learning\Domain')
    ->toOnlyUse(['Carbon\CarbonImmutable', 'Modules\Learning\Domain', 'Modules\Curriculum\Contracts\DTOs', 'InvalidArgumentException']);

arch('billing reaches enrollment and credits only through their contracts, and knows nothing of scheduling')
    ->expect('Modules\Billing')
    ->not->toUse([
        'Modules\Credits\Models', 'Modules\Credits\Actions', 'Modules\Credits\Domain',
        'Modules\Enrollment\Models', 'Modules\Enrollment\Actions',
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Scheduling', 'Modules\Curriculum', 'Modules\Learning', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import', 'App',
    ]);

arch('identity reaches platform only through its contracts, and knows nothing above it')
    ->expect('Modules\Identity')
    ->not->toUse([
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'Modules\Platform\Branding',
        'Modules\Communication', 'Modules\Catalog', 'Modules\People', 'Modules\Curriculum', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Scheduling', 'Modules\Learning', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import', 'App',
    ]);

arch('communication knows only identity\'s and platform\'s contracts: modules call it, it calls none of them')
    ->expect('Modules\Communication')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Authorization', 'Modules\Identity\Preferences',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'Modules\Platform\Branding',
        'Modules\Catalog', 'Modules\People', 'Modules\Curriculum', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing',
        'Modules\Scheduling', 'Modules\Learning', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import', 'App',
    ]);

arch('modules send messages only through communication\'s contracts')
    ->expect(['Modules\People', 'Modules\Learning', 'Modules\Billing', 'Modules\Scheduling', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import', 'Modules\Support'])
    ->not->toUse([
        'Modules\Communication\Models', 'Modules\Communication\Actions', 'Modules\Communication\Channels',
        'Modules\Communication\Templates', 'Modules\Communication\Jobs', 'Modules\Communication\Domain',
    ]);

arch('quiet hours and channel choices are pure')
    ->expect('Modules\Communication\Domain')
    ->toOnlyUse(['Carbon\CarbonImmutable', 'Modules\Communication\Domain', 'InvalidArgumentException']);

arch('catalog depends only on the shared kernel')
    ->expect('Modules\Catalog')
    ->not->toUse(array_values(array_diff(MODULES_ABOVE_KERNEL, ['Modules\Catalog'])));

arch('people reaches identity and catalog only through their contracts')
    ->expect('Modules\People')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Preferences',
        'Modules\Catalog\Models', 'Modules\Catalog\Actions',
        'Modules\Curriculum', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Scheduling', 'Modules\Learning', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import', 'App',
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
        'Modules\Curriculum', 'Modules\Learning', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import', 'App',
    ]);

arch('evaluation reaches lessons, reports and people only through their contracts, and only the CRM and the app sit above it')
    ->expect('Modules\Evaluation')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Preferences', 'Modules\Identity\Authorization',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'Modules\Platform\Branding',
        'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Scheduling\Domain', 'Modules\Scheduling\Actions', 'Modules\Scheduling\Queries', 'Modules\Scheduling\Filament',
        'Modules\Learning\Models', 'Modules\Learning\Actions', 'Modules\Learning\Domain', 'Modules\Learning\Queries',
        'Modules\Communication\Models', 'Modules\Communication\Actions', 'Modules\Communication\Channels', 'Modules\Communication\Templates',
        'Modules\Catalog', 'Modules\Curriculum', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Crm', 'Modules\Classroom', 'Modules\Payroll', 'Modules\Import', 'App',
    ]);

arch('the KPI rules are pure: numbers only')
    ->expect('Modules\Evaluation\Domain')
    ->toOnlyUse(['Modules\Evaluation\Domain', 'InvalidArgumentException']);

arch('payroll reaches lessons, people and messages only through their contracts, and nothing depends on it')
    ->expect('Modules\Payroll')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Preferences', 'Modules\Identity\Authorization',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'Modules\Platform\Branding',
        'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Scheduling\Domain', 'Modules\Scheduling\Actions', 'Modules\Scheduling\Queries', 'Modules\Scheduling\Filament',
        'Modules\Communication\Models', 'Modules\Communication\Actions', 'Modules\Communication\Channels', 'Modules\Communication\Templates',
        'Modules\Catalog\Models', 'Modules\Catalog\Actions',
        'Modules\Curriculum', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Learning', 'Modules\Evaluation', 'Modules\Crm', 'Modules\Classroom', 'App',
    ]);

arch('import sits on top: it makes people, profiles and schedules only through each module\'s contracts, and nothing depends on it')
    ->expect('Modules\Import')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Preferences', 'Modules\Identity\Authorization', 'Modules\Identity\Http',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'Modules\Platform\Branding',
        'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Catalog\Models', 'Modules\Catalog\Actions',
        'Modules\Scheduling\Domain', 'Modules\Scheduling\Actions', 'Modules\Scheduling\Queries', 'Modules\Scheduling\Filament',
        'Modules\Billing\Models', 'Modules\Billing\Actions', 'Modules\Billing\Domain',
        'Modules\Communication\Models', 'Modules\Communication\Actions', 'Modules\Communication\Channels', 'Modules\Communication\Templates',
        'Modules\Credits', 'Modules\Enrollment', 'Modules\Curriculum', 'Modules\Learning', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Crm', 'Modules\Classroom', 'App',
    ]);

arch('pay rules are pure: rate lines and numbers only')
    ->expect('Modules\Payroll\Domain')
    ->toOnlyUse(['Modules\Payroll\Domain', 'InvalidArgumentException']);

// The demo seeder books trials and enrolments the way the screens do, through Scheduling's actions.
arch('crm sits on top: it reaches every module only through its contracts, and nothing depends on it')
    ->expect('Modules\Crm')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Preferences', 'Modules\Identity\Authorization',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'Modules\Platform\Branding',
        'Modules\Catalog\Models', 'Modules\Catalog\Actions',
        'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Credits', 'Modules\Curriculum', 'Modules\Learning', 'Modules\Classroom', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import',
        'Modules\Enrollment\Models', 'Modules\Enrollment\Actions',
        'Modules\Billing\Models', 'Modules\Billing\Actions', 'Modules\Billing\Domain',
        'Modules\Scheduling\Domain', 'Modules\Scheduling\Actions', 'Modules\Scheduling\Queries', 'Modules\Scheduling\Filament',
        'Modules\Communication\Models', 'Modules\Communication\Actions', 'Modules\Communication\Channels', 'Modules\Communication\Templates',
        'App',
    ])
    ->ignoring('Modules\Crm\Database');

arch('the classroom reaches lessons, plans, people, messages and branding only through their contracts, and nothing depends on it')
    ->expect('Modules\Classroom')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Preferences', 'Modules\Identity\Authorization',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'Modules\Platform\Branding',
        'Modules\People\Models', 'Modules\People\Actions',
        'Modules\Scheduling\Domain', 'Modules\Scheduling\Actions', 'Modules\Scheduling\Queries', 'Modules\Scheduling\Filament',
        'Modules\Learning\Models', 'Modules\Learning\Actions', 'Modules\Learning\Domain', 'Modules\Learning\Queries',
        'Modules\Communication\Models', 'Modules\Communication\Actions', 'Modules\Communication\Channels', 'Modules\Communication\Templates',
        'Modules\Catalog', 'Modules\Curriculum', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Payroll', 'Modules\Import', 'App',
    ])
    ->and(['Modules\Platform', 'Modules\Identity', 'Modules\Communication', 'Modules\Catalog', 'Modules\People', 'Modules\Curriculum', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Scheduling', 'Modules\Learning', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import'])
    ->not->toUse('Modules\Classroom');

arch('support sits on top: it reaches lessons, people, messages and the KPIs only through their contracts, and nothing depends on it')
    ->expect('Modules\Support')
    ->not->toUse([
        'Modules\Identity\Models', 'Modules\Identity\Actions', 'Modules\Identity\Preferences', 'Modules\Identity\Authorization',
        'Modules\Platform\Models', 'Modules\Platform\Actions', 'Modules\Platform\Directory', 'Modules\Platform\Branding',
        'Modules\People\Models', 'Modules\People\Actions', 'Modules\People\Filament',
        'Modules\Scheduling\Domain', 'Modules\Scheduling\Actions', 'Modules\Scheduling\Queries', 'Modules\Scheduling\Filament',
        'Modules\Evaluation\Models', 'Modules\Evaluation\Actions', 'Modules\Evaluation\Domain', 'Modules\Evaluation\Queries', 'Modules\Evaluation\Filament',
        'Modules\Communication\Models', 'Modules\Communication\Actions', 'Modules\Communication\Channels', 'Modules\Communication\Templates',
        'Modules\Catalog', 'Modules\Curriculum', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Learning', 'Modules\Classroom', 'Modules\Crm', 'Modules\Payroll', 'Modules\Import', 'App',
    ])
    ->ignoring('Modules\Support\Database')
    ->and(['Modules\Platform', 'Modules\Identity', 'Modules\Communication', 'Modules\Catalog', 'Modules\People', 'Modules\Curriculum', 'Modules\Credits', 'Modules\Enrollment', 'Modules\Billing', 'Modules\Scheduling', 'Modules\Learning', 'Modules\Classroom', 'Modules\Crm', 'Modules\Evaluation', 'Modules\Payroll', 'Modules\Import'])
    ->not->toUse('Modules\Support');

arch('the video adapters only talk to the provider: no queries, no attendance, no tenancy')
    ->expect('Modules\Classroom\Video')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database', 'Modules\Scheduling\Contracts\AttendanceApi', 'Modules\SharedKernel\Tenancy']);

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
        'Modules\Communication\Models', 'Modules\Communication\Actions',
        'Modules\Curriculum\Models', 'Modules\Curriculum\Actions',
        'Modules\Learning\Models', 'Modules\Learning\Actions', 'Modules\Learning\Domain',
        'Modules\Classroom\Models', 'Modules\Classroom\Actions', 'Modules\Classroom\Video',
        'Modules\Crm\Models', 'Modules\Crm\Actions',
        'Modules\Evaluation\Models', 'Modules\Evaluation\Actions',
        'Modules\Payroll\Models', 'Modules\Payroll\Actions',
        'Modules\Import\Models', 'Modules\Import\Actions',
        'Modules\Support\Models', 'Modules\Support\Actions',
    ]);

arch('actions and models are final')
    ->expect([
        'Modules\Platform\Actions', 'Modules\Identity\Actions', 'Modules\Catalog\Actions', 'Modules\People\Actions', 'Modules\Scheduling\Actions',
        'Modules\Credits\Actions', 'Modules\Enrollment\Actions', 'Modules\Billing\Actions', 'Modules\Curriculum\Actions', 'Modules\Learning\Actions', 'Modules\Communication\Actions', 'Modules\Classroom\Actions', 'Modules\Crm\Actions', 'Modules\Evaluation\Actions', 'Modules\Payroll\Actions', 'Modules\Import\Actions', 'Modules\Support\Actions',
        'Modules\Platform\Models', 'Modules\Identity\Models', 'Modules\Catalog\Models', 'Modules\People\Models', 'Modules\Scheduling\Domain\Models',
        'Modules\Credits\Models', 'Modules\Enrollment\Models', 'Modules\Billing\Models', 'Modules\Curriculum\Models', 'Modules\Learning\Models', 'Modules\Communication\Models', 'Modules\Classroom\Models', 'Modules\Crm\Models', 'Modules\Evaluation\Models', 'Modules\Payroll\Models', 'Modules\Import\Models', 'Modules\Support\Models',
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

arch('credit allocation, tax and discount rules are pure: no database, no framework')
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
