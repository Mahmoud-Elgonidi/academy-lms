<?php

namespace Modules\Platform\Ai\Engines;

use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiPrompt;

/**
 * An adapter that asks one engine for words (Claude, an OpenAI-compatible
 * server, Gemini, Ollama, the demo). Only the gateway (Ai\Gateway) and the
 * console's comparison call it; modules ask LanguageModel with their
 * capability's key. It notes the request in ai_usage and how the engine
 * answered in ServiceHealth; AiFailed('unavailable') or ('not_configured')
 * lets the gateway try the next engine, any other reason does not.
 */
interface TextEngine
{
    /** @throws AiFailed */
    public function ask(EngineSpec $engine, string $capability, AiPrompt $prompt, string $tier): AiAnswer;
}
