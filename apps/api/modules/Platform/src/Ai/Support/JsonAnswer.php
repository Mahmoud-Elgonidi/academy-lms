<?php

namespace Modules\Platform\Ai\Support;

use Modules\SharedKernel\Ai\AiFailed;

/**
 * A model's answer as the JSON object the prompt asked for: an object (or
 * nothing), never a list or prose; one ```json fence around it is forgiven.
 */
final class JsonAnswer
{
    /** @return array<string, mixed> */
    public static function object(string $text): array
    {
        $text = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $fenced) === 1) {
            $text = $fenced[1];
        }
        $data = json_decode($text, true);
        if (! is_array($data) || array_is_list($data) && $data !== []) {
            throw new AiFailed('unreadable');
        }

        return $data;
    }
}
