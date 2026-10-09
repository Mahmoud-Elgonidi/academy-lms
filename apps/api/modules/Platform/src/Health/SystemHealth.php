<?php

namespace Modules\Platform\Health;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\SharedKernel\Files\ScannerHealth;
use Modules\SharedKernel\Files\UploadScanner;
use Modules\SharedKernel\Health\Heartbeats;
use Modules\SharedKernel\Health\MailHealth;
use Modules\SharedKernel\Health\RecentErrors;
use Throwable;

/**
 * Is the stack well (docs/architecture/03, section 12)? Each check reads
 * the thing itself: the database and the cache answer, the scheduler, the
 * queue worker and the live relay spoke lately, Reverb takes connections,
 * the last backup is recent, the WAL archive keeps up and both reach the
 * off-site copy, a restore was drilled lately, the disk has room, mail goes
 * out, and errors are few. A check is ok,
 * warn (look soon) or fail (look now); "off" when this server has no such
 * part. The console page, GET /health and the alerts all read this.
 */
class SystemHealth
{
    public const ORDER = ['ok' => 0, 'off' => 0, 'warn' => 1, 'fail' => 2];

    /** @return array<string, array{status: string, value: string, hint: ?string}> by check, in display order */
    public function checks(): array
    {
        $checks = [];
        foreach (['database', 'cache', 'scheduler', 'worker', 'queue', 'relay', 'reverb', 'backups', 'archive', 'offsite', 'drill', 'disk', 'mail', 'scanning', 'errors'] as $name) {
            try {
                $checks[$name] = $this->{$name}();
            } catch (Throwable $e) {
                $checks[$name] = $this->check('fail', '', $e->getMessage());
            }
        }

        return $checks;
    }

    /** @param  array<string, array{status: string}>  $checks */
    public static function overall(array $checks): string
    {
        $worst = 'ok';
        foreach ($checks as $check) {
            if (self::ORDER[$check['status']] > self::ORDER[$worst]) {
                $worst = $check['status'];
            }
        }

        return $worst;
    }

    /** @return list<object{kind: string, status: string, started_at: string, finished_at: string, size_bytes: ?int, name: ?string, detail: ?string}> newest first (the off-site copy's hourly "the WAL is going" lines left out) */
    public function backupRuns(int $limit = 12): array
    {
        return DB::connection('pgsql_platform')->table('platform_backups')
            ->whereNot(fn ($q) => $q->where('kind', 'offsite')->where('name', 'wal')->where('status', 'ok'))
            ->orderByDesc('finished_at')->limit($limit)
            ->get(['kind', 'status', 'started_at', 'finished_at', 'size_bytes', 'name', 'detail'])->all();
    }

    private function database(): array
    {
        $db = DB::connection('pgsql_platform');
        $db->getPdo();
        // One round trip on an open connection: how long the database takes to answer, not to connect or count its files.
        $started = microtime(true);
        $db->selectOne('select 1');
        $ms = (int) round((microtime(true) - $started) * 1000);
        $size = (int) $db->selectOne('select pg_database_size(current_database()) as size')->size;

        return $this->check($ms > 1000 ? 'warn' : 'ok', __('platform::health.values.database', ['size' => self::bytes($size), 'ms' => $ms]));
    }

    private function cache(): array
    {
        $key = 'health:probe:'.bin2hex(random_bytes(4));
        Cache::put($key, 'ok', 30);
        $ok = Cache::pull($key) === 'ok';

        return $this->check($ok ? 'ok' : 'fail', __('platform::health.values.'.($ok ? 'answers' : 'silent')));
    }

    private function scheduler(): array
    {
        return $this->beat('scheduler', 3, 10);
    }

    private function worker(): array
    {
        $check = $this->beat('worker', 3, 10);
        $lag = (int) (Heartbeats::last('worker')['lag'] ?? 0);
        if ($check['status'] === 'ok' && $lag > 120) {
            return $this->check('warn', __('platform::health.values.lag', ['seconds' => $lag]), __('platform::health.hints.worker'));
        }

        return $check;
    }

    private function queue(): array
    {
        $waiting = 0;
        foreach (['notifications', 'default'] as $queue) {
            $waiting += (int) Queue::size($queue);
        }
        $failed = (int) DB::connection('pgsql_platform')->table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        $status = match (true) {
            $waiting > 1000 => 'fail',
            $waiting > 200 || $failed > 0 => 'warn',
            default => 'ok',
        };

        return $this->check($status, __('platform::health.values.queue', ['waiting' => $waiting, 'failed' => $failed]), $status === 'ok' ? null : __('platform::health.hints.queue'));
    }

    private function relay(): array
    {
        return config('broadcasting.default') === 'reverb' ? $this->beat('relay', 2, 5) : $this->off();
    }

    private function reverb(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return $this->off();
        }
        $options = (array) config('broadcasting.connections.reverb.options');
        $host = (string) ($options['host'] ?? '127.0.0.1');
        $port = (int) ($options['port'] ?? 8080);
        $socket = @fsockopen($host, $port, $code, $message, 2);
        if ($socket === false) {
            return $this->check('fail', __('platform::health.values.silent'), __('platform::health.hints.reverb'));
        }
        fclose($socket);

        return $this->check('ok', __('platform::health.values.answers'));
    }

    private function backups(): array
    {
        if (! config('platform.health.backups')) {
            return $this->off();
        }
        $last = $this->lastOk('base');
        $failed = DB::connection('pgsql_platform')->table('platform_backups')->whereNotIn('kind', ['drill', 'offsite'])->where('status', 'failed')
            ->where('finished_at', '>', $last ?? '1970-01-01')->exists();
        if ($last === null) {
            return $this->check('fail', __('platform::health.values.never'), __('platform::health.hints.backups'));
        }
        $hours = $last->diffInHours(now());
        $status = match (true) {
            $hours > 50 => 'fail',
            $hours > 26 || $failed => 'warn',
            default => 'ok',
        };

        return $this->check($status, __('platform::health.values.ago', ['when' => $last->diffForHumans()]), $status === 'ok' ? null : __('platform::health.hints.backups'));
    }

    private function archive(): array
    {
        if (! config('platform.health.backups')) {
            return $this->off();
        }
        $a = DB::connection('pgsql_platform')->selectOne('select archived_count, last_archived_time, failed_count, last_failed_time from pg_stat_archiver');
        $archived = $a->last_archived_time ? CarbonImmutable::parse($a->last_archived_time) : null;
        $failing = $a->last_failed_time && (! $archived || CarbonImmutable::parse($a->last_failed_time)->greaterThan($archived));
        $status = match (true) {
            $failing => 'fail',
            $archived === null => 'warn',
            $archived->lessThan(now()->subMinutes(30)) => 'warn',
            default => 'ok',
        };
        $value = $archived ? __('platform::health.values.ago', ['when' => $archived->diffForHumans()]) : __('platform::health.values.never');

        return $this->check($status, $value, $status === 'ok' ? null : __('platform::health.hints.archive'));
    }

    /**
     * The off-site copy (infra/deploy/offsite.sh): the WAL reaches the bucket
     * (a line each hour while it does, at once when it stops or starts again)
     * and so does each day's base backup. Where there are backups but no
     * bucket, a reminder that they would go with the server.
     */
    private function offsite(): array
    {
        if (! config('platform.health.backups')) {
            return $this->off();
        }
        if (! config('platform.health.offsite')) {
            return $this->check('warn', __('platform::health.values.offsite_off'), __('platform::health.hints.offsite_off'));
        }
        $rows = DB::connection('pgsql_platform')->table('platform_backups')->where('kind', 'offsite');
        $wal = (clone $rows)->where('name', 'wal')->orderByDesc('finished_at')->first(['status', 'finished_at', 'detail']);
        $walOk = $this->lastOk('offsite', 'wal');
        $baseOk = $this->lastOk('offsite', 'base/%');
        $hint = __('platform::health.hints.offsite');

        if ($wal?->status === 'failed') {
            $since = CarbonImmutable::parse((clone $rows)->where('name', 'wal')->where('status', 'failed')
                ->where('finished_at', '>', $walOk ?? '1970-01-01')->min('finished_at'));

            return $this->check($since->lessThan(now()->subHour()) ? 'fail' : 'warn',
                __('platform::health.values.offsite_failing', ['since' => $since->diffForHumans(), 'error' => mb_substr((string) $wal->detail, 0, 160)]), $hint);
        }
        if ($walOk === null) {
            return $this->check('warn', __('platform::health.values.never'), $hint);
        }
        $failed = (clone $rows)->where('status', 'failed')->where('finished_at', '>', $baseOk ?? '1970-01-01')->exists();
        $status = match (true) {
            $walOk->lessThan(now()->subHours(12)) || $baseOk?->lessThan(now()->subHours(50)) => 'fail',
            $walOk->lessThan(now()->subHours(3)) || $baseOk === null || $baseOk->lessThan(now()->subHours(26)) || $failed => 'warn',
            default => 'ok',
        };

        return $this->check($status, __('platform::health.values.offsite', [
            'wal' => $walOk->diffForHumans(),
            'base' => $baseOk?->diffForHumans() ?? __('platform::health.values.never'),
        ]), $status === 'ok' ? null : $hint);
    }

    private function drill(): array
    {
        if (! config('platform.health.backups')) {
            return $this->off();
        }
        $last = $this->lastOk('drill');
        if ($last === null) {
            return $this->check('warn', __('platform::health.values.never'), __('platform::health.hints.drill'));
        }

        return $this->check($last->lessThan(now()->subDays(95)) ? 'warn' : 'ok', __('platform::health.values.ago', ['when' => $last->diffForHumans()]),
            $last->lessThan(now()->subDays(95)) ? __('platform::health.hints.drill') : null);
    }

    private function disk(): array
    {
        $path = storage_path('app');
        $free = (float) disk_free_space($path);
        $total = max(1.0, (float) disk_total_space($path));
        $share = $free / $total;
        $status = match (true) {
            $free < 1024 ** 3 || $share < 0.05 => 'fail',
            $free < 5 * 1024 ** 3 || $share < 0.15 => 'warn',
            default => 'ok',
        };

        return $this->check($status, __('platform::health.values.disk', ['free' => self::bytes((int) $free), 'pct' => (int) round($share * 100)]),
            $status === 'ok' ? null : __('platform::health.hints.disk'));
    }

    /**
     * Mail goes out: alerts, welcome links and codes by email need a real
     * mailer on a server (not the log), someone to send the alerts to, and
     * the last message to have left (MailHealth, from every sender).
     */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            return app()->isProduction()
                ? $this->check('warn', __('platform::health.values.mail_log'), __('platform::health.hints.mail_log'))
                : $this->off();
        }
        $last = MailHealth::last();
        if (MailHealth::failing()) {
            return $this->check('fail', __('platform::health.values.mail_failed', [
                'when' => CarbonImmutable::createFromTimestamp((int) $last['failed'])->diffForHumans(), 'error' => $last['error'] ?? '',
            ]), __('platform::health.hints.mail_failed'));
        }
        if (AlertRecipients::all() === []) {
            return $this->check('warn', __('platform::health.values.mail_nobody'), __('platform::health.hints.mail_nobody'));
        }

        return isset($last['sent'])
            ? $this->check('ok', __('platform::health.values.mail_sent', ['when' => CarbonImmutable::createFromTimestamp((int) $last['sent'])->diffForHumans()]))
            : $this->check('ok', __('platform::health.values.mail_ready', ['mailer' => $mailer]), __('platform::health.hints.mail_test'));
    }

    /**
     * Uploads are checked for malware when the server runs a scanner
     * (UPLOAD_SCANNER=clamav): it answers, and how it went this week (files
     * refused, the last time it could not answer).
     */
    private function scanning(): array
    {
        if (config('uploads.scanner') !== 'clamav') {
            return $this->off();
        }
        $scanner = app(UploadScanner::class);
        if (($error = $scanner->ping()) !== null) {
            return $this->check('fail', __('platform::health.values.scanner_down', ['error' => mb_substr($error, 0, 160)]), __('platform::health.hints.scanner_down'));
        }
        $last = ScannerHealth::last();
        $value = __('platform::health.values.scanner_ok', ['version' => (string) $scanner->version(), 'infected' => (int) ($last['infected'] ?? 0)]);
        if (($last['failed'] ?? 0) > ($last['scanned'] ?? 0)) {
            return $this->check('warn', $value, __('platform::health.hints.scanner_failed', ['error' => $last['error'] ?? '']));
        }

        return $this->check('ok', $value);
    }

    private function errors(): array
    {
        $hour = RecentErrors::countLast(1);
        $day = RecentErrors::countLast(24);
        $status = match (true) {
            $hour >= 50 => 'fail',
            $hour >= 5 => 'warn',
            default => 'ok',
        };

        return $this->check($status, __('platform::health.values.errors', ['hour' => $hour, 'day' => $day]), $status === 'ok' ? null : __('platform::health.hints.errors'));
    }

    /** A part that beats: ok within $warn minutes, warn until $fail, then fail. */
    private function beat(string $part, int $warn, int $fail): array
    {
        $beat = Heartbeats::last($part);
        if ($beat === null) {
            return $this->check('fail', __('platform::health.values.never'), __('platform::health.hints.'.$part));
        }
        $at = CarbonImmutable::createFromTimestamp($beat['at']);
        $minutes = $at->diffInMinutes(now());
        $status = match (true) {
            $minutes >= $fail => 'fail',
            $minutes >= $warn => 'warn',
            default => 'ok',
        };

        return $this->check($status, __('platform::health.values.ago', ['when' => $at->diffForHumans()]), $status === 'ok' ? null : __('platform::health.hints.'.$part));
    }

    private function lastOk(string $kind, ?string $name = null): ?CarbonImmutable
    {
        $at = DB::connection('pgsql_platform')->table('platform_backups')->where('kind', $kind)->where('status', 'ok')
            ->when($name !== null, fn ($q) => $q->where('name', 'like', $name))->max('finished_at');

        return $at === null ? null : CarbonImmutable::parse($at);
    }

    private function off(): array
    {
        return $this->check('off', __('platform::health.values.off'));
    }

    /** @return array{status: string, value: string, hint: ?string} */
    private function check(string $status, string $value, ?string $hint = null): array
    {
        return ['status' => $status, 'value' => $value, 'hint' => $hint];
    }

    public static function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) $bytes : number_format($n, $n < 10 ? 1 : 0)).' '.$units[$i];
    }
}
