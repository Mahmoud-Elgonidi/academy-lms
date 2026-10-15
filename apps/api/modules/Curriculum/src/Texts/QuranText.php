<?php

namespace Modules\Curriculum\Texts;

use Modules\Curriculum\Contracts\DTOs\Passage;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\SharedKernel\Text\ArabicText;
use SimpleXMLElement;

/**
 * The Quran as the platform keeps it: Tanzil's simple text, verbatim, with
 * its notice (quran-simple.txt, "surah|verse|text" lines), and its metadata
 * (quran-data.xml: each surah's names and verses), fetched to the server's
 * storage and checked there (QuranFiles), else shipped in resources/quran.
 * Never typed, never generated, never changed: every word a screen or a
 * helper shows of the Quran comes from here. Until the files are in place,
 * the Quran is not offered (available() is false).
 *
 * Tanzil writes the basmala at the head of each surah's first verse (not
 * al-Fatiha's, where it is the verse, nor at-Tawba's); here it is apart.
 * A pause mark Tanzil writes on its own (ۛ ۖ ۗ) is no word: it goes with
 * the word before it, so a quote keeps it and the count of words does not.
 */
final class QuranText
{
    public const TEXT_FILE = 'quran-simple.txt';

    public const DATA_FILE = 'quran-data.xml';

    /** @var ?array<int, array<int, string>> surah => verse => text */
    private ?array $verses = null;

    /** @var ?list<array{number: int, name_ar: string, name_en: string, verses: int}> */
    private ?array $surahs = null;

    private ?string $notice = null;

    /** @var ?array<string, true> each run of three words, as compared */
    private ?array $runs = null;

    /** @param  ?string  $fallback  read when $dir has no copy (the code's own, under resources) */
    public function __construct(private readonly string $dir, private readonly ?string $fallback = null) {}

    public function available(): bool
    {
        return $this->dir() !== null;
    }

    /** The folder the copy in use is read from: the server's, else the code's; null when neither has one. */
    public function dir(): ?string
    {
        foreach (array_filter([$this->dir, $this->fallback]) as $dir) {
            if (is_file($dir.'/'.self::TEXT_FILE) && is_file($dir.'/'.self::DATA_FILE)) {
                return $dir;
            }
        }

        return null;
    }

    /** @return list<array{number: int, name_ar: string, name_en: string, verses: int}> */
    public function surahs(): array
    {
        // Read once there is a copy (one fetched while this process runs is read from then on).
        if ($this->surahs === null && ($dir = $this->dir()) !== null) {
            $this->surahs = [];
            $xml = new SimpleXMLElement((string) file_get_contents($dir.'/'.self::DATA_FILE), LIBXML_NONET);
            foreach ($xml->suras->sura ?? [] as $sura) {
                $this->surahs[] = [
                    'number' => (int) $sura['index'],
                    'name_ar' => (string) $sura['name'],
                    'name_en' => (string) $sura['tname'],
                    'verses' => (int) $sura['ayas'],
                ];
            }
        }

        return $this->surahs ?? [];
    }

    /** @return ?array{number: int, name_ar: string, name_en: string, verses: int} */
    public function surah(int $number): ?array
    {
        return collect($this->surahs())->firstWhere('number', $number);
    }

    /**
     * The verses $from to $to of a surah, as kept; empty when they are not all there.
     *
     * @return array<int, string> verse => text
     */
    public function verses(int $surah, int $from, int $to): array
    {
        $all = $this->load()[$surah] ?? [];
        if ($from < 1 || $to < $from || ! isset($all[$from], $all[$to])) {
            return [];
        }

        return array_slice($all, $from - 1, $to - $from + 1, true);
    }

    public function passage(TextSource $source): ?Passage
    {
        $surah = $this->surah((int) $source->surah);
        $verses = $surah ? $this->verses($surah['number'], (int) $source->from, (int) $source->to) : [];
        if ($verses === []) {
            return null;
        }

        $words = [];
        $list = [];
        foreach ($verses as $number => $text) {
            $own = self::words($text);
            $list[] = ['surah' => $surah['number'], 'verse' => $number, 'text' => $text, 'first' => count($words) + 1, 'last' => count($words) + count($own)];
            array_push($words, ...$own);
        }
        $range = $source->from === $source->to
            ? Passage::arabicNumber((int) $source->from)
            : Passage::arabicNumber((int) $source->from).'–'.Passage::arabicNumber((int) $source->to);

        return new Passage($source, $surah['name_ar'].' '.$range, $words, $list, $this->notice());
    }

    /**
     * A verse's words as written, a pause mark on its own going with the word before it.
     *
     * @return list<string>
     */
    public static function words(string $verse): array
    {
        $words = [];
        foreach (preg_split('/\s+/u', trim($verse), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (preg_match('/\p{L}/u', $token) !== 1 && $words !== []) {
                $words[count($words) - 1] .= ' '.$token;

                continue;
            }
            $words[] = $token;
        }

        return $words;
    }

    /** Tanzil's notice, kept with every passage shown (its terms ask for it). */
    public function notice(): string
    {
        $this->load();

        return $this->notice ?? '';
    }

    /**
     * Whether a text holds three words in a row of the Quran (as compared:
     * marks and alef forms aside). What a helper writes for a passage must
     * quote it by reference instead, so its words come from here.
     */
    public function quotes(string $text): bool
    {
        $words = ArabicText::words($text);
        if (count($words) < 3) {
            return false;
        }
        $runs = $this->runs();
        for ($i = 0; $i + 2 < count($words); $i++) {
            if (isset($runs[$words[$i].' '.$words[$i + 1].' '.$words[$i + 2]])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, true> */
    private function runs(): array
    {
        if ($this->runs === null) {
            if ($this->load() === []) {
                return [];
            }
            $this->runs = [];
            foreach ($this->load() as $verses) {
                $words = ArabicText::words(implode(' ', $verses));
                for ($i = 0; $i + 2 < count($words); $i++) {
                    $this->runs[$words[$i].' '.$words[$i + 1].' '.$words[$i + 2]] = true;
                }
            }
        }

        return $this->runs;
    }

    /** @return array<int, array<int, string>> */
    private function load(): array
    {
        if ($this->verses !== null) {
            return $this->verses;
        }
        if (($dir = $this->dir()) === null) {
            return [];
        }
        $this->verses = [];

        $basmala = ArabicText::words('بسم الله الرحمن الرحيم');
        $notice = [];
        foreach (file($dir.'/'.self::TEXT_FILE, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_starts_with($line, '#')) {
                $notice[] = trim(ltrim($line, '# '));

                continue;
            }
            if (preg_match('/^(\d+)\|(\d+)\|(.+)$/u', trim($line), $m) !== 1) {
                continue;
            }
            [$surah, $verse, $text] = [(int) $m[1], (int) $m[2], trim($m[3])];
            if ($verse === 1 && $surah !== 1 && $surah !== 9) {
                $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (count($words) > 4 && ArabicText::words(implode(' ', array_slice($words, 0, 4))) === $basmala) {
                    $text = implode(' ', array_slice($words, 4));
                }
            }
            $this->verses[$surah][$verse] = $text;
        }
        $this->notice = collect($notice)->first(fn (string $l) => str_contains($l, 'Tanzil Quran Text'))
            ?? collect($notice)->first(fn (string $l) => str_contains($l, 'Tanzil'));

        return $this->verses;
    }
}
