<?php

/*
 * One way to a model (docs/architecture/02, section 22): modules ask
 * SharedKernel's LanguageModel and SpeechToText with their capability's key;
 * only Platform's gateway and its engine adapters reach a provider. The games
 * designer (Platform\Games\Designer) is the one other user of Claude's SDK:
 * it is the console's own tool, not a capability, and stays outside.
 */

const AI_ENGINE_ADAPTERS = [
    'Modules\Platform\Ai\ClaudeLanguageModel', 'Modules\Platform\Ai\OpenAiLanguageModel', 'Modules\Platform\Ai\GeminiLanguageModel',
    'Modules\Platform\Ai\OllamaLanguageModel', 'Modules\Platform\Ai\ElevenLabsSpeechToText', 'Modules\Platform\Ai\WhisperSpeechToText',
    'Modules\Platform\Ai\DemoLanguageModel', 'Modules\Platform\Ai\DemoSpeechToText',
];

arch('only the gateway\'s engine adapters (and the games designer) use Claude\'s SDK')
    ->expect('Anthropic')
    ->toOnlyBeUsedIn(['Modules\Platform\Ai\ClaudeLanguageModel', 'Modules\Platform\Ai\Faults', 'Modules\Platform\Games\Designer']);

arch('a provider request over HTTP is made only by the engine adapters')
    ->expect('Modules\Platform\Ai\Support\ModelCall')
    ->toOnlyBeUsedIn(['Modules\Platform\Ai\OpenAiLanguageModel', 'Modules\Platform\Ai\GeminiLanguageModel', 'Modules\Platform\Ai\OllamaLanguageModel']);

arch('the engine adapters are asked only by the gateway (and its own Platform AI classes)')
    ->expect(AI_ENGINE_ADAPTERS)
    ->toOnlyBeUsedIn(['Modules\Platform\Ai']);

arch('no module reaches Platform\'s AI: it asks the shared kernel\'s contracts with its capability')
    ->expect(['Modules\Curriculum', 'Modules\Learning', 'Modules\Quran', 'Modules\Games', 'Modules\Crm', 'Modules\Support', 'Modules\Messaging', 'App'])
    ->not->toUse(['Modules\Platform\Ai', 'Anthropic']);
