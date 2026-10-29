<?php

namespace Modules\Learning\Digest;

use Modules\SharedKernel\Ai\AiPrompt;

/**
 * What the helper is asked for the weekly note: the week's facts alone (no
 * name: the child is {child}; no ids), and one short warm paragraph in each
 * language from them. WordingCheck reads what comes back.
 */
final class DigestPrompt
{
    private const SYSTEM = <<<'TXT'
        You write the weekly note an online tutoring academy sends a child's guardian about the child's week.
        Write from the facts you are given and nothing else: no number that is not in them, no promise, no comparison
        with other children, no advice the teacher did not give, no link, address, email or phone number.
        Call the child {child}, exactly so, every time; never a name. In Arabic, write warm, clear Modern Standard Arabic
        as a caring teacher would, and prefer words that do not guess whether the child is a boy or a girl.
        Each language gets one short paragraph of plain text (at most 450 characters), no line breaks, no emoji, no
        greeting with a name and no sign-off. Lesson titles and the teacher's words are quoted as given.
        When the week had little in it, say so kindly in one sentence.
        TXT;

    /** @param  array<string, mixed>  $facts  WeekFacts::for() */
    public static function for(array $facts): AiPrompt
    {
        $week = $facts;
        unset($week['voice']);

        return new AiPrompt(
            system: self::SYSTEM,
            user: "The week's facts (JSON):\n".json_encode($week, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            schema: [
                'type' => 'object',
                'properties' => ['ar' => ['type' => 'string'], 'en' => ['type' => 'string']],
                'required' => ['ar', 'en'],
                'additionalProperties' => false,
            ],
            tier: 'fast',
            effort: 'low',
            maxTokens: 1200,
            demo: ['ar' => FactsText::for($facts, 'ar'), 'en' => FactsText::for($facts, 'en')],
        );
    }
}
