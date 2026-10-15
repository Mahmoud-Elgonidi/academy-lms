<?php

namespace Modules\SharedKernel\Ai;

/**
 * How a recording of a text went, word by word: each verse's words ok,
 * missing or wrong (with what was said instead), the words said that are
 * not in the text (after the word they followed, counted through the whole
 * text from 1; 0 before the first), notes an engine that hears more than
 * words may add (a letter or a rule, by word), what was heard, and how long.
 * A help for the teacher, never a mark.
 */
final readonly class RecitationResult
{
    /**
     * @param  list<array{surah: ?int, verse: ?int, words: list<array{text: string, state: 'ok'|'missing'|'wrong', heard: ?string}>}>  $verses
     * @param  list<array{after: int, text: string}>  $extra
     * @param  list<array{word: int, note: string}>  $notes
     */
    public function __construct(
        public array $verses,
        public array $extra,
        public string $heard,
        public float $seconds,
        public string $engine,
        public array $notes = [],
    ) {}

    /** @return array{words: int, ok: int, missing: int, wrong: int, extra: int} */
    public function summary(): array
    {
        $states = array_count_values(array_merge(...array_map(fn (array $v) => array_column($v['words'], 'state'), $this->verses ?: [['words' => []]])));

        return [
            'words' => array_sum($states),
            'ok' => $states['ok'] ?? 0,
            'missing' => $states['missing'] ?? 0,
            'wrong' => $states['wrong'] ?? 0,
            'extra' => count($this->extra),
        ];
    }

    /** @return array<string, mixed> as kept */
    public function toArray(): array
    {
        return ['verses' => $this->verses, 'extra' => $this->extra, 'notes' => $this->notes, 'summary' => $this->summary()];
    }
}
