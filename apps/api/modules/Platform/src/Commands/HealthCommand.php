<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\Health\AlertRecipients;
use Modules\Platform\Health\HealthAlert;
use Modules\Platform\Health\SystemHealth;
use Modules\SharedKernel\Health\MailHealth;
use Modules\SharedKernel\Health\RecentErrors;
use Modules\SharedKernel\Health\TestAddresses;
use Throwable;

/**
 * The stack's health in the terminal; with --alert (the scheduler, every
 * five minutes) the platform team hears when a check has failed twice in a
 * row (so a restart or a fresh stack wakes no one), again every six hours
 * while it fails, and when it is well again.
 */
final class HealthCommand extends Command
{
    protected $signature = 'platform:health {--alert : tell the platform team what changed} {--errors : list the errors the app reported lately, as the health page does (redacted)}';

    protected $description = "The stack's health: database, cache, scheduler, worker, relay, backups and their off-site copy, disk, mail, errors";

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

        // From the server, without the console: what broke, where, how often (messages redacted like the logs).
        if ($this->option('errors')) {
            $errors = RecentErrors::all();
            $errors === []
                ? $this->line('no errors reported this week')
                : $this->table(['last (UTC)', 'times', 'request', 'error', 'thrown at'], array_map(fn (array $e) => [
                    gmdate('Y-m-d H:i', $e['last']), $e['count'], $e['where'], $e['class'].': '.$e['message'], $e['place'],
                ], array_values($errors)));
        }

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
        // The demo's console accounts (.test) never get mail through the server (TestAddresses).
        $to = array_values(array_filter(AlertRecipients::all(), fn (string $address) => ! TestAddresses::skips($address)));
        if ($to !== []) {
            try {
                Mail::to($to)->send(new HealthAlert($failing, $recovered, $remind));
            } catch (Throwable $e) {
                // Mail is down: the health page and GET /health say so; the next check tries again.
                MailHealth::failed($e);
                Log::error('health: the alert could not be sent: '.MailHealth::last()['error']);
                $this->error('alert not sent: mail fails');

                return;
            }
        }
        Cache::forever(self::ALERTED, ['names' => array_keys($failing), 'at' => now()->getTimestamp()]);
        $this->info('alert sent to '.count($to));
    }
}
