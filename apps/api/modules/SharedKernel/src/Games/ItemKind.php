<?php

namespace Modules\SharedKernel\Games;

/**
 * The shapes a practice item takes (docs/architecture/02, section 18): what
 * a lesson's practice items are written as in Curriculum, what a game says
 * it can play, and what the game page is handed. The words always come from
 * the academy's curriculum, never from the game.
 */
enum ItemKind: string
{
    /** A question with 2–4 answers, one right: {prompt, options, answer}. */
    case Choice = 'choice';

    /** Parts to put in order (a verse's words, a word's letters, steps): {prompt?, parts}. */
    case Order = 'order';

    /** Two things that go together (a word and its meaning): {left, right}. */
    case Pair = 'pair';

    public const PROMPT_MAX = 160;

    public const OPTION_MAX = 60;

    public const PART_MAX = 40;

    public const SIDE_MAX = 60;

    public function label(): string
    {
        return __('shared::games.kinds.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
