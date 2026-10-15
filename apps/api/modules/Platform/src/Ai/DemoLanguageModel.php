<?php

namespace Modules\Platform\Ai;

use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\LanguageModel;

/**
 * The demo academy's and the end-to-end tests' language model (AI_DRIVER=demo,
 * never in production): each prompt carries the answer it stands for, so the
 * screens show a real draft without a key or a request.
 */
final class DemoLanguageModel implements LanguageModel
{
    public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer
    {
        if ($prompt->demo === []) {
            throw new AiFailed('unreadable');
        }
        AiUsage::note($feature, 'demo', 'demo', [], 'ok');

        return new AiAnswer($prompt->demo, 'demo');
    }
}
