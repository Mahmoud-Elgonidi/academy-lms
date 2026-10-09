<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\Health\HealthAlert;
use Modules\Platform\Health\SystemHealth;
use Modules\Platform\Models\PlatformAdmin;

/**
 * The stack's health in the terminal; with --alert (the scheduler, every
 * five minutes) the platform team hears when a check has failed twice in a
 * row (so a restart or a fresh stack wakes no one), again every six hours
 * while it fails, and when it is well again.
 */
final class HealthCommand extends Command
{
    protected $signature = 'platform:health {--alert : tell the platform team what changed}';

    protected $description = "The stack's health: database, cache, scheduler, worker, relay, backups, disk, errors";

    private const ALERTED = 'health:alerted';

    private const FAILED_LAST = 'health:failed-last';

    private const REMIND_HOURS = 6;

    public function handle(SystemHealth $health): int
    {
        $checks = $health->checks();
        $this->table(['check', 'status', 'value', 'hint'], array_map(
            fn (string $name, array $c) => [$name, $c['status'], $c['value'], (string) $c['hint']], array_keys($checks), $checks));
        $overall = SystemHealth::overall($checks);
        $this->line('overall: '.$overall);

        if ($this->option('alert')) {
            $this->notifyTeam($checks);
        }

        return $overall === 'fail' ? self::FAILURE : self::SUCCESS;
    }

    /** @param  array<string, array{status: string, value: string, hint: ?string}>  $checks */
    private function notifyTeam(array $checks): void
    {
        $failingNow = array_keys(array_filter($checks, fn (array $c) => $c['status'] === 'fail'));
        $before = (array) Cache::get(self::ALERTED, ['names' => [], 'at' => 0]);
        $confirmed = array_merge((array) Cache::get(self::FAILED_LAST, []), $before['names'] ?? []);
        Cache::forever(self::FAILED_LAST, $failingNow);

        $failing = array_intersect_key($checks, array_flip(array_intersect($failingNow, $confirmed)));
        $new = array_diff(array_keys($failing), $before['names'] ?? []);
        $recovered = array_values(array_diff($before['names'] ?? [], $failingNow));
        $remind = $failing !== [] && $new === [] && now()->getTimestamp() - (int) ($before['at'] ?? 0) >= self::REMIND_HOURS * 3600;
        if ($new === [] && $recovered === [] && ! $remind) {
            return;
        }

        foreach ($failing as $name => $check) {
            Log::warning("health: {$name} is failing: {$check['value']}");
        }
        $to = PlatformAdmin::query()->where('is_active', true)->pluck('email')->merge(config('platform.health.alert_to'))->filter()->unique()->values()->all();
        if ($to !== []) {
            Mail::to($to)->send(new HealthAlert($failing, $recovered, $remind));
        }
        Cache::forever(self::ALERTED, ['names' => array_keys($failing), 'at' => now()->getTimestamp()]);
        $this->info('alert sent to '.count($to));
    }
}
