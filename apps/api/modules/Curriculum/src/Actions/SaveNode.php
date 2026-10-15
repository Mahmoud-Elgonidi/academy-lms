<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Texts\QuranText;

/**
 * Adds a level, unit or lesson at the end of its parent, or edits one.
 * Levels sit at the top, units in levels, lessons in units (or straight in a
 * level). Moving a node to another parent is not done here. A lesson may
 * have a text (text_source, given or left as it is): words the academy
 * typed, or a passage of the Quran the platform has, by surah and verses.
 */
final class SaveNode
{
    private const PARENTS = ['level' => [null], 'unit' => ['level'], 'lesson' => ['unit', 'level']];

    public function __construct(private readonly QuranText $quran) {}

    /** @param  array{kind?: string, parent_id?: ?string, title_ar: string, title_en: string, objectives?: ?string, weight?: int|string, est_sessions?: int|string, mastery_rule?: string, text_source?: ?array<string, mixed>}  $data */
    public function handle(CurriculumVersion $version, ?Node $node, array $data): Node
    {
        if ($version->status === 'retired') {
            throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.retired')]);
        }
        $text = array_key_exists('text_source', $data) ? $this->textSource($node?->kind ?? ($data['kind'] ?? 'lesson'), $data['text_source']) : false;

        return DB::transaction(function () use ($version, $node, $data, $text) {
            if ($node === null) {
                $kind = $data['kind'] ?? 'lesson';
                $parent = isset($data['parent_id']) ? Node::query()->where('version_id', $version->id)->find($data['parent_id']) : null;
                if (! in_array($kind, Node::KINDS, true) || ! in_array($parent?->kind, self::PARENTS[$kind], true)) {
                    throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.place')]);
                }
                $node = new Node([
                    'version_id' => $version->id,
                    'parent_id' => $parent?->id,
                    'kind' => $kind,
                    'ordinal' => (int) Node::query()->where('version_id', $version->id)->where('parent_id', $parent?->id)->max('ordinal') + 1,
                ]);
            }

            $node->fill([
                'title_ar' => trim($data['title_ar']),
                'title_en' => trim($data['title_en']),
                'objectives' => ($data['objectives'] ?? null) ?: null,
                'weight' => max(1, min(100, (int) ($data['weight'] ?? $node->weight ?? 1))),
                'est_sessions' => max(1, min(50, (int) ($data['est_sessions'] ?? $node->est_sessions ?? 1))),
                'mastery_rule' => in_array($data['mastery_rule'] ?? null, Node::MASTERY_RULES, true) ? $data['mastery_rule'] : ($node->mastery_rule ?? 'mastered_once'),
            ]);
            if ($text !== false) {
                $node->text_source = $text?->toArray();
            }
            $node->save();

            return $node;
        });
    }

    /**
     * The lesson's text as kept, null for none; refused when it is too long, the passage is not
     * one the platform has, or the node is not a lesson.
     *
     * @param  mixed  $given
     */
    private function textSource(string $kind, $given): ?TextSource
    {
        $l = 'curriculum::curriculum.text.errors.';
        if ($given === null || $given === [] || (is_array($given) && ($given['kind'] ?? null) === null)) {
            return null;
        }
        $fail = fn (string $why, array $replace = []) => throw ValidationException::withMessages(['text_source' => __($l.$why, $replace)]);
        if ($kind !== 'lesson') {
            $fail('lesson_only');
        }
        if (! is_array($given) || ! in_array($given['kind'] ?? null, ['text', 'quran'], true)) {
            $fail('kind');
        }
        assert(is_array($given));

        if ($given['kind'] === 'text') {
            $text = trim(str_replace("\r\n", "\n", (string) ($given['text'] ?? '')));
            if ($text === '') {
                $fail('empty');
            }
            if (mb_strlen($text) > TextSource::TEXT_MAX) {
                $fail('too_long', ['max' => TextSource::TEXT_MAX]);
            }

            return TextSource::text($text);
        }

        if (! $this->quran->available()) {
            $fail('quran_missing');
        }
        [$surah, $from, $to] = [(int) ($given['surah'] ?? 0), (int) ($given['from'] ?? 0), (int) ($given['to'] ?? 0)];
        $known = $this->quran->surah($surah);
        if ($known === null || $from < 1 || $to < $from || $to > $known['verses']) {
            $fail('verses', ['max' => $known['verses'] ?? 0]);
        }
        if ($to - $from + 1 > TextSource::VERSES_MAX) {
            $fail('too_many', ['max' => TextSource::VERSES_MAX]);
        }

        return TextSource::quran($surah, $from, $to);
    }
}
