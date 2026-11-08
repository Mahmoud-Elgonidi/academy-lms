<?php

namespace Modules\Platform\Ai;

use Modules\Platform\Ai\Engines\EngineSpec;
use Modules\Platform\Ai\Engines\TextEngine;
use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\LanguageModel;

/**
 * The demo academy's and the end-to-end tests' language model (AI_DRIVER=demo,
 * never in production): each prompt carries the answer it stands for (else
 * its capability's own demo answer), so the screens show a real draft
 * without a key or a request.
 */
final class DemoLanguageModel implements LanguageModel, TextEngine
{
    public function json(AiFeature|string $capability, AiPrompt $prompt): AiAnswer
    {
        return $this->ask(EngineSpec::demo(), Capabilities::key($capability), $prompt, $prompt->tier);
    }

    public function ask(EngineSpec $engine, string $capability, AiPrompt $prompt, string $tier): AiAnswer
    {
        $answer = $prompt->demo !== [] ? $prompt->demo : (app(Capabilities::class)->get($capability)->demo ?? []);
        if ($answer === [] || array_key_exists('heard', $answer) && count($answer) === 1) {
            throw new AiFailed('unreadable');
        }
        AiUsage::note($capability, $engine->provider, 'demo', [], 'ok');

        return new AiAnswer($answer, 'demo');
    }
}
