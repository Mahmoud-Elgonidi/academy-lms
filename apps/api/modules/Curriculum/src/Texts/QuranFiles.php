<?php

namespace Modules\Curriculum\Texts;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\SharedKernel\Text\QuranFetchFailed;
use Modules\SharedKernel\Text\QuranSource;
use SimpleXMLElement;
use Throwable;

/**
 * Fetches the Quran's text to the server (curriculum:quran --fetch, the
 * deploy, the console) and keeps it only once all of it checks out: the
 * metadata names 114 surahs and 6,236 verses; the text has one well-formed
 * line for every verse of every surah, in order, each in Arabic letters and
 * without the rub-el-hizb or sajdah signs; and Tanzil's notice is there.
 * Both files go in at once (each written aside, then renamed in place), next
 * to a manifest of their SHA-256 and when they came. The server's storage
 * keeps them across deploys and in its backups; QuranText reads them first.
 */
final class QuranFiles implements QuranSource
{
    public const SURAHS = 114;

    public const VERSES = 6236;

    /** The Madinah mushaf's pages and parts, each placed by where it begins. */
    public const PAGES = 604;

    public const JUZS = 30;

    public const MANIFEST = 'manifest.json';

    /** No file of these is near this size; a bigger answer is not theirs. */
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(private readonly string $dir) {}

    public function status(): array
    {
        $text = app(QuranText::class);
        $where = match ($text->dir()) {
            null => null,
            $this->dir => 'server',
            default => 'code',
        };
        $manifest = $where === 'server' ? (json_decode((string) @file_get_contents($this->dir.'/'.self::MANIFEST), true) ?: []) : [];

        return [
            'available' => $where !== null,
            'where' => $where,
            'notice' => $where === null ? null : ($text->notice() ?: null),
            'verses' => $where === null ? null : array_sum(array_column($text->surahs(), 'verses')),
            'text_sha256' => $manifest['text_sha256'] ?? null,
            'data_sha256' => $manifest['data_sha256'] ?? null,
            'fetched_at' => $manifest['fetched_at'] ?? null,
        ];
    }

    public function fetch(): array
    {
        $text = $this->download((string) config('curriculum.quran.text_url'));
        $data = $this->download((string) config('curriculum.quran.data_url'));
        $notice = self::check($text, $data);

        if (! is_dir($this->dir) && ! @mkdir($this->dir, 0775, true) && ! is_dir($this->dir)) {
            throw new QuranFetchFailed(__('curriculum::curriculum.quran.errors.write'));
        }
        $manifest = [
            'source' => 'Tanzil',
            'notice' => $notice,
            'verses' => self::VERSES,
            'text_sha256' => hash('sha256', $text),
            'data_sha256' => hash('sha256', $data),
            'fetched_at' => now()->toIso8601String(),
        ];
        $this->put([
            QuranText::TEXT_FILE => $text,
            QuranText::DATA_FILE => $data,
            self::MANIFEST => (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        // This process reads the new copy from now on.
        app()->forgetInstance(QuranText::class);

        return $this->status();
    }

    /**
     * Whether Tanzil's two files are whole; returns the text's notice line.
     *
     * @throws QuranFetchFailed saying what is wrong
     */
    public static function check(string $text, string $data): string
    {
        $fail = fn (string $why, array $with = []) => throw new QuranFetchFailed(__("curriculum::curriculum.quran.errors.{$why}", $with));

        $counts = [];
        $layout = ['pages' => [], 'juzs' => []];
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = new SimpleXMLElement($data, LIBXML_NONET);
            foreach ($xml->suras->sura ?? [] as $sura) {
                $counts[(int) $sura['index']] = (int) $sura['ayas'];
            }
            foreach (['pages' => $xml->pages->page ?? [], 'juzs' => $xml->juzs->juz ?? []] as $kind => $items) {
                foreach ($items as $item) {
                    $layout[$kind][(int) $item['index']] = [(int) $item['sura'], (int) $item['aya']];
                }
            }
        } catch (Throwable) {
            $fail('data');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (array_keys($counts) !== range(1, self::SURAHS) || array_sum($counts) !== self::VERSES) {
            $fail('data');
        }
        // Every page and part begins at a verse there is, each after the one before, the first at the very start.
        foreach (['pages' => self::PAGES, 'juzs' => self::JUZS] as $kind => $count) {
            ksort($layout[$kind]);
            $last = 0;
            foreach (array_values($layout[$kind]) as $i => [$surah, $verse]) {
                $at = array_sum(array_slice($counts, 0, $surah - 1)) + $verse;
                if ($verse < 1 || $verse > ($counts[$surah] ?? 0) || $at <= $last || ($i === 0 && $at !== 1)) {
                    $fail('layout');
                }
                $last = $at;
            }
            if (array_keys($layout[$kind]) !== range(1, $count)) {
                $fail('layout');
            }
        }

        $notice = null;
        $expected = [1, 1];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            if (str_starts_with($line, '#')) {
                $notice ??= str_contains($line, 'Tanzil Quran Text') ? trim(ltrim($line, '# ')) : null;

                continue;
            }
            if (preg_match('/^(\d{1,3})\|(\d{1,3})\|(.+)$/u', trim($line), $m) !== 1 || preg_match('/\p{Arabic}/u', $m[3]) !== 1) {
                $fail('line', ['line' => $n + 1]);
            }
            [$surah, $verse] = [(int) $m[1], (int) $m[2]];
            if ([$surah, $verse] !== $expected) {
                $fail('order', ['surah' => $surah, 'verse' => $verse]);
            }
            if (preg_match('/[\x{06DE}\x{06E9}]/u', $m[3]) === 1) {
                $fail('signs');
            }
            $expected = $verse < ($counts[$surah] ?? 0) ? [$surah, $verse + 1] : [$surah + 1, 1];
        }
        if ($expected !== [self::SURAHS + 1, 1]) {
            $fail('short');
        }
        if ($notice === null || ! str_contains($notice, 'Tanzil Quran Text (Simple')) {
            $fail('notice');
        }

        return $notice;
    }

    private function download(string $url): string
    {
        if (! str_starts_with($url, 'https://')) {
            throw new QuranFetchFailed(__('curriculum::curriculum.quran.errors.address'));
        }
        try {
            $answer = Http::timeout((int) config('curriculum.quran.timeout', 120))->get($url);
        } catch (ConnectionException) {
            throw new QuranFetchFailed(__('curriculum::curriculum.quran.errors.unreachable', ['host' => (string) parse_url($url, PHP_URL_HOST)]));
        }
        if (! $answer->successful()) {
            throw new QuranFetchFailed(__('curriculum::curriculum.quran.errors.status', ['host' => (string) parse_url($url, PHP_URL_HOST), 'status' => $answer->status()]));
        }
        $body = $answer->body();
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            throw new QuranFetchFailed(__('curriculum::curriculum.quran.errors.size'));
        }

        return $body;
    }

    /** @param  array<string, string>  $files  name => contents, each written aside then renamed into place */
    private function put(array $files): void
    {
        $written = [];
        foreach ($files as $name => $contents) {
            $aside = $this->dir.'/.'.$name.'.'.bin2hex(random_bytes(4));
            if (@file_put_contents($aside, $contents) !== strlen($contents)) {
                array_map('unlink', array_filter([...$written, $aside], 'is_file'));
                throw new QuranFetchFailed(__('curriculum::curriculum.quran.errors.write'));
            }
            $written[$name] = $aside;
        }
        foreach ($written as $name => $aside) {
            rename($aside, $this->dir.'/'.$name);
        }
    }
}
