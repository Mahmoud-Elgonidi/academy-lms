<?php

namespace Modules\Quran\Console;

use Illuminate\Console\Command;
use Modules\SharedKernel\Text\QuranFetchFailed;
use Modules\SharedKernel\Text\QuranSource;

/**
 * The Quran's text on this server: whether it is here and which copy
 * (`quran:text`; `curriculum:quran` stays its other name, for servers whose deploy still calls it), or fetch Tanzil's files and keep them once checked
 * (`--fetch`; with `--if-missing`, only when there is no copy yet, as the
 * deploy does).
 */
final class QuranCommand extends Command
{
    protected $signature = 'quran:text
        {--fetch : fetch Tanzil\'s files to the server, checked before they are kept}
        {--if-missing : with --fetch, only when the server has no copy yet}';

    /** @var list<string> */
    protected $aliases = ['curriculum:quran'];

    protected $description = 'Show the Quran\'s text on this server, or fetch it from Tanzil (--fetch)';

    public function handle(QuranSource $quran): int
    {
        $status = $quran->status();
        if ($this->option('fetch') && ! ($this->option('if-missing') && $status['available'])) {
            try {
                $status = $quran->fetch();
            } catch (QuranFetchFailed $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info('Fetched and checked: 114 surahs, 6,236 verses.');
        }

        if (! $status['available']) {
            $this->warn('No copy of the Quran\'s text on this server yet: php artisan quran:text --fetch');

            return $this->option('fetch') ? self::FAILURE : self::SUCCESS;
        }
        $this->line(sprintf('%s (%s verses), %s', $status['notice'] ?? 'Tanzil', number_format((int) $status['verses']),
            $status['where'] === 'server' ? 'fetched to the server '.($status['fetched_at'] ?? '') : 'shipped with the code'));
        if ($status['text_sha256'] !== null) {
            $this->line('SHA-256 text '.$status['text_sha256']);
            $this->line('SHA-256 data '.$status['data_sha256']);
        }

        return self::SUCCESS;
    }
}
