<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\Curriculum\Providers\CurriculumServiceProvider;
use Modules\Curriculum\Texts\QuranFiles;
use Modules\Curriculum\Texts\QuranText;
use Modules\Platform\Filament\Console\Pages\PlatformSettingsPage;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Text\QuranFetchFailed;
use Modules\SharedKernel\Text\QuranSource;

/*
 * The Quran's text on the server (curriculum:quran --fetch, the deploy, the
 * console): Tanzil's two files are fetched and kept only once all of them
 * checks out (114 surahs, 6,236 verses, every verse in order, Tanzil's
 * notice), next to a manifest of their checksums; QuranText reads that copy
 * first. These files are made up here in the shape of Tanzil's: their words
 * are not the Quran's.
 */

const TEXT_URL = 'https://tanzil.example/text';
const DATA_URL = 'https://tanzil.example/data.xml';

beforeEach(function () {
    $this->storage = sys_get_temp_dir().'/lms-quran-'.bin2hex(random_bytes(4));
    $this->app->useStoragePath($this->storage);
    app()->forgetInstance(QuranText::class);
    app()->forgetInstance(QuranSource::class);
    config(['curriculum.quran.text_url' => TEXT_URL, 'curriculum.quran.data_url' => DATA_URL]);
    // What "Tanzil" answers right now (fakeTanzil), so a test can change it between two fetches.
    $GLOBALS['tanzil'] = ['', '', 404];
    Http::fake([
        TEXT_URL => fn () => Http::response($GLOBALS['tanzil'][0], $GLOBALS['tanzil'][2]),
        DATA_URL => fn () => Http::response($GLOBALS['tanzil'][1], $GLOBALS['tanzil'][2]),
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->storage);
});

/** @return array{0: string, 1: string} a text and its metadata in Tanzil's shape: 114 surahs, 6,236 verses */
function tanzilShaped(?callable $line = null): array
{
    $counts = array_fill(1, 113, 55) + [114 => 6236 - 113 * 55];
    $xml = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n<quran>\n<suras alias=\"chapters\">\n";
    $text = '';
    foreach ($counts as $surah => $verses) {
        $xml .= "<sura index=\"{$surah}\" ayas=\"{$verses}\" name=\"سورة {$surah}\" tname=\"Sura {$surah}\" />\n";
        foreach (range(1, $verses) as $verse) {
            $words = $verse === 2 ? 'كَلِمَةٌ ۛ أُخْرَى هُنَا' : 'كَلِمَةٌ تَجْرِيبِيَّةٌ رَقْم '.$verse;
            $text .= $line ? $line($surah, $verse, $words) : "{$surah}|{$verse}|{$words}\n";
        }
    }
    $text .= "\n#  A file in the shape of Tanzil's text, with made-up words: not the Quran.\n#  Tanzil Quran Text (Simple, Version Test)\n";
    // Where each page and part begins, spread evenly through the verses (the real ones are the Madinah mushaf's).
    $place = function (string $tag, string $items, int $count) use ($counts): string {
        $out = "<{$tag}>\n";
        foreach (range(1, $count) as $i) {
            $at = intdiv(($i - 1) * 6236, $count) + 1;
            foreach ($counts as $surah => $verses) {
                if ($at <= $verses) {
                    break;
                }
                $at -= $verses;
            }
            $out .= "<{$items} index=\"{$i}\" sura=\"{$surah}\" aya=\"{$at}\" />\n";
        }

        return $out."</{$tag}>\n";
    };

    return [$text, $xml."</suras>\n".$place('juzs', 'juz', 30).$place('pages', 'page', 604)."</quran>\n"];
}

function fakeTanzil(string $text, string $data, int $status = 200): void
{
    $GLOBALS['tanzil'] = [$text, $data, $status];
}

/** @return list<string> files left aside (a write that did not finish) */
function leftAside(): array
{
    $dir = CurriculumServiceProvider::quranDir();

    return is_dir($dir) ? array_values(array_filter(scandir($dir), fn (string $f) => str_starts_with($f, '.') && ! in_array($f, ['.', '..'], true))) : [];
}

it('fetches the text, keeps it with its checksums, and reads it from the server from then on', function () {
    [$text, $data] = tanzilShaped();
    fakeTanzil($text, $data);
    expect(app(QuranSource::class)->status()['where'])->not->toBe('server');

    $status = app(QuranSource::class)->fetch();

    $dir = CurriculumServiceProvider::quranDir();
    expect($dir)->toStartWith($this->storage)
        ->and(file_get_contents($dir.'/quran-simple.txt'))->toBe($text)
        ->and($status)->toMatchArray([
            'available' => true, 'where' => 'server', 'verses' => 6236, 'notice' => 'Tanzil Quran Text (Simple, Version Test)',
            'text_sha256' => hash('sha256', $text), 'data_sha256' => hash('sha256', $data),
        ])
        ->and(json_decode(file_get_contents($dir.'/manifest.json'), true)['source'])->toBe('Tanzil')
        ->and(leftAside())->toBe([]);

    $quran = app(QuranText::class);
    expect($quran->dir())->toBe($dir)->and($quran->surahs())->toHaveCount(114);
    // A pause mark on its own goes with the word before it: a quote keeps it, the count of words does not.
    $passage = $quran->passage(TextSource::quran(7, 2, 2));
    expect($passage->words)->toBe(['كَلِمَةٌ ۛ', 'أُخْرَى', 'هُنَا'])
        ->and($passage->quote(1, 2))->toBe('كَلِمَةٌ ۛ أُخْرَى');

    // The mushaf's pages: where each begins, the part it is in, and a page that ends one surah and begins the next.
    $page = $quran->page(6);
    expect($quran->pageCount())->toBe(604)
        ->and($quran->pages()[0])->toBe(['page' => 1, 'juz' => 1])->and($quran->pages()[603]['juz'])->toBe(30)
        ->and($quran->pagesOf(1, 1, 11))->toBe([1, 2])->and($quran->pagesOf(1, 1, 99))->toBe([])
        ->and(array_map(fn ($v) => $v['surah'].':'.$v['verse'], $page['verses']))->toBe(['1:52', '1:53', '1:54', '1:55', '2:1', '2:2', '2:3', '2:4', '2:5', '2:6'])
        ->and(array_column($page['surahs'], 'number'))->toBe([1, 2])
        ->and($page['verses'][1]['first'])->toBe($page['verses'][0]['last'] + 1)
        ->and($quran->page(605))->toBeNull();
});

it('keeps nothing when a check fails, and the copy in use stays', function (callable $make, string $why) {
    [$text, $data] = tanzilShaped();
    fakeTanzil($text, $data);
    app(QuranSource::class)->fetch();
    $kept = hash_file('sha256', CurriculumServiceProvider::quranDir().'/quran-simple.txt');

    [$badText, $badData] = $make($text, $data);
    fakeTanzil($badText, $badData);
    expect(fn () => app(QuranSource::class)->fetch())->toThrow(QuranFetchFailed::class, $why);

    expect(hash_file('sha256', CurriculumServiceProvider::quranDir().'/quran-simple.txt'))->toBe($kept)
        ->and(leftAside())->toBe([]);
})->with([
    'a verse missing' => [fn ($t, $d) => [str_replace("7|3|كَلِمَةٌ تَجْرِيبِيَّةٌ رَقْم 3\n", '', $t), $d], 'skips or repeats a verse at surah 7, verse 4'],
    'a verse twice' => [fn ($t, $d) => [str_replace('7|3|', "7|2|x كلمة\n7|3|", $t), $d], 'skips or repeats'],
    'the end cut off' => [fn ($t, $d) => [explode('114|10|', $t)[0], $d], 'stops before the last verse'],
    'metadata of 113 surahs' => [fn ($t, $d) => [$t, preg_replace('/<sura index="114"[^>]*\/>\n/', '', $d)], 'must name 114 surahs'],
    'metadata that is not XML' => [fn ($t, $d) => [$t, '<html>a page</html'], 'must name 114 surahs'],
    'a page missing' => [fn ($t, $d) => [$t, preg_replace('/<page index="300"[^>]*\/>\n/', '', $d)], '604 pages and 30 parts'],
    'a page where the one before begins' => [fn ($t, $d) => [$t, preg_replace('/<page index="2" sura="\d+" aya="\d+"/', '<page index="2" sura="1" aya="1"', $d)], '604 pages and 30 parts'],
    'no notice' => [fn ($t, $d) => [str_replace('Tanzil Quran Text (Simple, Version Test)', 'Some text', $t), $d], "Tanzil's notice"],
    'a line not in Arabic' => [fn ($t, $d) => [str_replace('9|9|كَلِمَةٌ تَجْرِيبِيَّةٌ رَقْم 9', '9|9|some words', $t), $d], 'Line '],
    'rub-el-hizb signs' => [fn ($t, $d) => [str_replace('9|9|كَلِمَةٌ', '9|9|۞ كَلِمَةٌ', $t), $d], 'rub-el-hizb or sajdah'],
]);

it('says why when the files cannot be fetched', function () {
    fakeTanzil('down', 'down', 503);
    expect(fn () => app(QuranSource::class)->fetch())->toThrow(QuranFetchFailed::class, 'tanzil.example answered with an error (503)');

    config(['curriculum.quran.text_url' => 'http://tanzil.example/text']);
    expect(fn () => app(QuranSource::class)->fetch())->toThrow(QuranFetchFailed::class, 'must start with https://');
    expect(is_file(CurriculumServiceProvider::quranDir().'/quran-simple.txt'))->toBeFalse();
});

it('fetches from the command, only once with --if-missing, and says what is there', function () {
    [$text, $data] = tanzilShaped();
    fakeTanzil($text, $data);

    $this->artisan('curriculum:quran')->expectsOutputToContain('No copy of the Quran')->assertSuccessful();
    $this->artisan('curriculum:quran', ['--fetch' => true, '--if-missing' => true])->expectsOutputToContain('6,236 verses')->assertSuccessful();
    $this->artisan('curriculum:quran', ['--fetch' => true, '--if-missing' => true])->expectsOutputToContain('SHA-256 text '.hash('sha256', $text))->assertSuccessful();
    Http::assertSentCount(2);

    fakeTanzil('nothing', $data);
    $this->artisan('curriculum:quran', ['--fetch' => true])->assertFailed();
});

it('shows the copy in the console and fetches it there, audited', function () {
    [$text, $data] = tanzilShaped();
    fakeTanzil($text, $data);
    $owner = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@company.example', 'password' => 'a-long-password']);
    $owner->forceFill(['is_owner' => true])->save();
    app()->setLocale('en');
    Filament::setCurrentPanel('console');
    $this->actingAs($owner, 'platform');

    Livewire::test(PlatformSettingsPage::class)
        ->assertSee('Not on the server yet')
        ->callAction('fetchQuran')
        ->assertNotified("The Quran's text is on the server, checked: 114 surahs, 6,236 verses.")
        ->assertSee('Tanzil Quran Text (Simple, Version Test)')->assertSee(hash('sha256', $text));

    expect(json_decode(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.quran_fetched')->value('data'), true))->toBe(['verses' => 6236]);
});

it('checks any copy shipped with the code as whole Tanzil text', function () {
    $dir = base_path('modules/Curriculum/resources/quran');
    expect(is_file($dir.'/README.md'))->toBeTrue();
    if (is_file($dir.'/'.QuranText::TEXT_FILE)) {
        expect(QuranFiles::check(file_get_contents($dir.'/'.QuranText::TEXT_FILE), file_get_contents($dir.'/'.QuranText::DATA_FILE)))
            ->toContain('Tanzil Quran Text (Simple');
    } else {
        expect(is_file($dir.'/'.QuranText::DATA_FILE))->toBeFalse();   // both or neither
    }
});
