<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Modules\SharedKernel\Health\RecentErrors;
use Modules\SharedKernel\Tenancy\EachTenant;
use Modules\SharedKernel\Tenancy\TenantContext;

/** Every command the scheduler runs on a server, by name. */
const SCHEDULED = [
    'billing:sweep', 'classroom:reconcile', 'classroom:recordings', 'communication:sweep', 'credits:expire',
    'learning:digests', 'learning:sweep', 'model:prune', 'people:checks', 'platform:health', 'platform:services', 'platform:site-sweep', 'platform:usage', 'privacy:sweep',
    'safeguarding:follow-up', 'scheduling:courses', 'scheduling:dst', 'scheduling:materialize', 'scheduling:sweep', 'support:follow-up',
];

beforeEach(function () {
    $this->createAcademy(['slug' => 'one']);
    $this->createAcademy(['slug' => 'two']);
});

it('runs every scheduled command over two academies without an error', function () {
    // The scheduler's own command lines, with their options (model:prune's models, the health check's --alert).
    $scheduled = collect(app(Schedule::class)->events())
        ->map(fn ($event) => preg_match("/artisan'?\\s+(.+)$/", (string) $event->command, $m) === 1 ? trim($m[1]) : null)
        ->filter()->unique()->values();
    // A new scheduled command joins the list, so it runs here too.
    expect($scheduled->map(fn (string $line) => strtok($line, ' '))->sort()->values()->all())->toBe(collect(SCHEDULED)->sort()->values()->all());

    // The health check answers 1 when something needs looking at (here nothing runs the server's parts): that is its report, not an error.
    $failed = [];
    foreach ($scheduled as $line) {
        $code = Artisan::call($line);
        $output = trim(Artisan::output());
        if ($code !== 0 && ! (str_starts_with($line, 'platform:health') && str_contains($output, 'overall: fail'))) {
            $failed[] = "{$line} ({$code}): {$output}";
        }
    }
    expect($failed)->toBe([]);
    expect(app(TenantContext::class)->current())->toBeNull();
});

it('keeps sweeping the other academies when one of them throws, and reports it', function () {
    $seen = [];
    $total = app(EachTenant::class)->sum(function ($academy) use (&$seen) {
        if ($academy->slug === 'one') {
            throw new RuntimeException('the sweep broke in one academy');
        }
        $seen[] = $academy->slug;

        return 2;
    });

    expect($seen)->toBe(['two'])
        ->and($total)->toBe(2)
        ->and(collect(RecentErrors::all())->pluck('message')->all())->toContain('the sweep broke in one academy')
        ->and(app(TenantContext::class)->current())->toBeNull();
});
