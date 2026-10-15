<?php

namespace Modules\SharedKernel\Ai;

/**
 * Words from the language model, held to the prompt's JSON schema. The
 * caller checks every value again: a schema says the shape, never the
 * bounds. Prompts and answers are never written to a log.
 */
interface LanguageModel
{
    /** @throws AiFailed */
    public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer;
}
