<?php

namespace Modules\SharedKernel\Ai;

/**
 * Words from the language model, held to the prompt's JSON schema. The
 * caller checks every value again: a schema says the shape, never the
 * bounds. Prompts and answers are never written to a log.
 */
interface LanguageModel
{
    /**
     * @param  AiFeature|string  $capability  the helper, or a registered capability's key: the gateway routes by it
     *
     * @throws AiFailed
     */
    public function json(AiFeature|string $capability, AiPrompt $prompt): AiAnswer;
}
