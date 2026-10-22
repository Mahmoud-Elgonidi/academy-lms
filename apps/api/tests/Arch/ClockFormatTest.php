<?php

/*
 * Every clock written for people goes through Clock (SharedKernel\Time): 12
 * hours (5:00 م, 5:00 PM) or 24 (17:00), the reader's choice. This reads the
 * modules' code and views and fails on a clock written another way. Data
 * (API keys, spreadsheets, comparisons, validation) writes with format() and
 * is not looked at, except in a view, which writes for people.
 */

/**
 * What the scan cannot tell from data: file (from apps/api) => [the text on the line => why it stays].
 *
 * @var array<string, array<string, string>>
 */
const CLOCK_FORMAT_ALLOWED = [
    'modules/Scheduling/resources/views/filament/calendar/page.blade.php' => [
        "hourCycle: 'h23'" => 'reads the minute of the day to place the now line; nothing is written for people',
    ],
];

/** The one place that writes a clock its own way. */
const CLOCK_FORMAT_CLOCK = 'modules/SharedKernel/src/Time/Clock.php';

const CLOCK_FORMAT_HELP = 'Write a clock for people through Modules\SharedKernel\Time\Clock: '
    ."translatedFormat(Clock::pattern('l j F · H:i')) on a Carbon with ->locale(...), Clock::wall('17:00') for a time of the week, "
    .'Clock::hour($h) for an hour on a scale, Clock::hourCycle() for a script (data-hour-cycle). '
    .'If it is data (an API key, a spreadsheet, a comparison), write it with format() outside a view, '
    .'or add it to CLOCK_FORMAT_ALLOWED in tests/Arch/ClockFormatTest.php with the reason.';

it('writes every clock for people through Clock', function () {
    $root = dirname(__DIR__, 2);
    $files = [];
    foreach (['modules', 'app'] as $top) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$top, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = substr($file->getPathname(), strlen($root) + 1);
            $view = str_ends_with($path, '.blade.php') && preg_match('#^modules/[^/]+/resources/views/#', $path) === 1;
            $code = str_ends_with($path, '.php') && ! str_ends_with($path, '.blade.php')
                && (str_starts_with($path, 'app/') || preg_match('#^modules/[^/]+/src/#', $path) === 1);
            if (($view || $code) && $path !== CLOCK_FORMAT_CLOCK) {
                $files[$path] = $view;
            }
        }
    }
    ksort($files);
    expect(count($files))->toBeGreaterThan(500);

    // The argument of the call that opens at $open (the index of its "("), quotes respected.
    $argument = function (string $text, int $open): string {
        $depth = 0;
        $quote = null;
        for ($i = $open, $n = strlen($text); $i < $n; $i++) {
            $c = $text[$i];
            if ($quote !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')' && --$depth === 0) {
                return substr($text, $open + 1, $i - $open - 1);
            }
        }

        return substr($text, $open + 1);
    };
    // A pattern literal with H:i left once every Clock::pattern(...) is taken out.
    $bareClock = function (string $args) use ($argument): bool {
        while (preg_match('/(?:\\\\?Modules\\\\SharedKernel\\\\Time\\\\)?Clock::pattern\(/', $args, $m, PREG_OFFSET_CAPTURE) === 1) {
            $open = $m[0][1] + strlen($m[0][0]) - 1;
            $inner = $argument($args, $open);
            $args = substr($args, 0, $m[0][1]).substr($args, $open + strlen($inner) + 2);
        }

        return preg_match('/([\'"])[^\'"]*H:i[^\'"]*\1/', $args) === 1;
    };

    $problems = [];
    foreach ($files as $path => $view) {
        $text = (string) file_get_contents($root.'/'.$path);
        $allowed = CLOCK_FORMAT_ALLOWED[$path] ?? [];
        $flag = function (int $offset, string $what) use (&$problems, $text, $path, $allowed): void {
            $line = substr_count($text, "\n", 0, $offset) + 1;
            $source = trim(explode("\n", $text)[$line - 1]);
            foreach (array_keys($allowed) as $needle) {
                if (str_contains($source, $needle)) {
                    return;
                }
            }
            $problems[] = "{$path}:{$line}: {$what}: {$source}";
        };

        preg_match_all('/translatedFormat\(/', $text, $calls, PREG_OFFSET_CAPTURE);
        foreach ($calls[0] as [$call, $at]) {
            if ($bareClock($argument($text, $at + strlen($call) - 1))) {
                $flag($at, 'a pattern with H:i not through Clock::pattern()');
            }
        }

        // The same pattern given to a helper that hands it on (FamilyLearning::date) passes; a view writes for people.
        if ($view) {
            preg_match_all('/->format\(\s*([\'"])[^\'"]*H:i[^\'"]*\1\s*\)/', $text, $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] as [, $at]) {
                $flag($at, 'a view writing a clock with format()');
            }
            preg_match_all('/sprintf\(\s*([\'"])%02d:(?:00|%02d)\1/', $text, $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] as [, $at]) {
                $flag($at, 'a view writing an hour with sprintf()');
            }
            preg_match_all('/hourCycle\s*:\s*([\'"])h23\1|hour12\s*:\s*false/', $text, $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] as [, $at]) {
                $flag($at, 'a script writing a fixed 24-hour clock');
            }
        } else {
            preg_match_all('/sprintf\(\s*([\'"])%02d:00\1/', $text, $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] as [, $at]) {
                $flag($at, 'an hour written with sprintf()');
            }
        }
    }

    expect($problems)->toBe([], CLOCK_FORMAT_HELP."\n".implode("\n", $problems));
});

it('keeps the allowlist to lines that are still there', function () {
    $root = dirname(__DIR__, 2);
    foreach (CLOCK_FORMAT_ALLOWED as $path => $lines) {
        $text = (string) @file_get_contents($root.'/'.$path);
        foreach (array_keys($lines) as $needle) {
            expect(str_contains($text, $needle))->toBeTrue("{$path} no longer has {$needle}: take it out of CLOCK_FORMAT_ALLOWED");
        }
    }
});
