<?php

namespace Modules\Platform\Ai\Gateway;

use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\LanguageModel;

/** SharedKernel's LanguageModel as every module sees it: the gateway, by the capability's key. */
final class GatewayLanguageModel implements LanguageModel
{
    public function __construct(private readonly Gateway $gateway) {}

    public function json(AiFeature|string $capability, AiPrompt $prompt): AiAnswer
    {
        return $this->gateway->text(Capabilities::key($capability), $prompt);
    }
}
