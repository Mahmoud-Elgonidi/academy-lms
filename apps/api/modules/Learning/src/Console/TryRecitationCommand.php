<?php

namespace Modules\Learning\Console;

use Illuminate\Console\Command;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\Learning\Assist\RecitationScore;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\ExpectedText;
use Modules\SharedKernel\Ai\RecitationEngine;

/**
 * Measures the recitation check before an academy turns it on: recordings
 * in a folder, with the mistakes a teacher heard in each (cases.json), go
 * through the engine bound now, and it says how many of those mistakes it
 * caught and how many it marked that were not there. Nothing is kept, no
 * academy is involved, and the recordings and what was heard stay on this
 * screen (never a log).
 *
 * cases.json: [{"file": "01.webm", "surah": 78, "from": 1, "to": 5,
 *   "mistakes": [{"verse": 3, "word": 2, "kind": "missing"}]}, ...]
 * (a word is its place in the verse from 1, as the platform's text counts them; kind missing or wrong).
 */
final class TryRecitationCommand extends Command
{
    protected $signature = 'learning:try-recitation {folder : recordings with their cases.json} {--heard : print what was heard}';

    protected $description = 'Measure the recitation check on recordings whose mistakes are known';

    public function handle(RecitationEngine $engine, CurriculumApi $curriculum): int
    {
        $folder = rtrim((string) $this->argument('folder'), '/');
        $cases = json_decode((string) @file_get_contents($folder.'/cases.json'), true);
        if (! is_array($cases) || $cases === []) {
            $this->error("No cases in {$folder}/cases.json.");

            return self::INVALID;
        }

        $rows = [];
        $total = ['marked' => 0, 'caught' => 0, 'same_kind' => 0, 'false_alarms' => 0, 'seconds' => 0.0];
        foreach ($cases as $case) {
            $file = $folder.'/'.basename((string) ($case['file'] ?? ''));
            $passage = is_file($file) ? $curriculum->passage(TextSource::quran((int) ($case['surah'] ?? 0), (int) ($case['from'] ?? 0), (int) ($case['to'] ?? 0))) : null;
            if ($passage === null) {
                $rows[] = [basename($file), '—', '—', '—', '—', is_file($file) ? 'verses not found' : 'no such file'];

                continue;
            }
            $verses = array_map(fn (array $v) => [
                'surah' => $v['surah'], 'verse' => $v['verse'], 'words' => array_slice($passage->words, $v['first'] - 1, $v['last'] - $v['first'] + 1),
            ], $passage->verses);

            try {
                $result = $engine->check(AiFeature::RecitationCheck, new AudioClip($file, (string) (mime_content_type($file) ?: 'application/octet-stream'), basename($file)), new ExpectedText($verses));
            } catch (AiFailed $e) {
                $rows[] = [basename($file), '—', '—', '—', '—', $e->reason];

                continue;
            }
            $known = array_map(fn (array $k) => ['verse' => (int) $k['verse'], 'word' => (int) $k['word'], 'kind' => (string) ($k['kind'] ?? 'wrong')], $case['mistakes'] ?? []);
            $score = RecitationScore::of($result->verses, $known);
            $total['marked'] += count($known);
            $total['caught'] += $score['caught'];
            $total['same_kind'] += $score['same_kind'];
            $total['false_alarms'] += count($score['false_alarms']);
            $total['seconds'] += $result->seconds;
            $rows[] = [basename($file), count($known), $score['caught'], implode(' ', $score['missed']) ?: '—', implode(' ', $score['false_alarms']) ?: '—', ''];
            if ($this->option('heard')) {
                $this->line(basename($file).': '.$result->heard);
            }
        }

        $this->table(['recording', 'marked', 'caught', 'missed (verse:word)', 'false alarms (verse:word)', 'not checked'], $rows);
        $flagged = $total['caught'] + $total['false_alarms'];
        $percent = fn (int $part, int $whole) => $whole === 0 ? '—' : round(100 * $part / $whole).'%';
        $this->info(sprintf(
            'Caught %d of %d mistakes (%s), %s with the same kind; %d false alarm(s): %s of what it marked was a mistake. %.0f seconds of recordings.',
            $total['caught'], $total['marked'], $percent($total['caught'], $total['marked']),
            $percent($total['same_kind'], $total['caught']), $total['false_alarms'], $percent($total['caught'], $flagged), $total['seconds'],
        ));

        return self::SUCCESS;
    }
}
